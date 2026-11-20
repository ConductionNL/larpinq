<?php

/**
 * CharacterXpHistory for Larpinq
 *
 * The XP history of a character: each skill removed from it, with the XP the
 * removal returned, when and by whom. Read from the character's OpenRegister
 * audit trail, so showing it never changes the XP: the engine already stops
 * counting a removed skill's cost on the next calculation (REQ-CSP-005).
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Lists the skill removals of a character with the XP each one returned.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
 */
class CharacterXpHistory {

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher   $objectFetcher      Reads the audit trail and the removed skills.
	 * @param CharacterService        $characterService   The stat engine, for what a skill costs.
	 * @param SkillRequirementService $requirementService Resolves the XP ability.
	 * @param LoggerInterface         $logger             Records an unreadable history.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly CharacterService $characterService,
		private readonly SkillRequirementService $requirementService,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The skill removals of a character, newest first.
	 *
	 * Each removal is a `skills` id present before an update and gone after
	 * it. The XP it returned is what the skill costs the character today: XP
	 * left without the skill minus XP left with it, from the same engine the
	 * stats page reads. An unreadable history is an empty one, never an error
	 * on the stats page.
	 *
	 * @param array<string, mixed> $character The character, as read by the caller.
	 *
	 * @return array<int, array{type: string, skillId: string, skillName: string, xp: int, date: string, user: string}> The refund lines.
	 *
	 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
	 */
	public function refunds(array $character): array {
		$characterId = (string)($character['id'] ?? '');
		if ($characterId === '') {
			return [];
		}

		try {
			$logs = $this->objectFetcher->getObjectLogs(id: $characterId);
		} catch (Throwable $exception) {
			$this->logger->warning(
				'[CharacterXpHistory] the audit trail of a character could not be read; no XP history shown',
				['character' => $characterId, 'exception' => $exception->getMessage()]
			);
			return [];
		}

		$lines = [];
		foreach ($logs as $log) {
			foreach ($this->removedSkills(log: (array)$log) as $skillId) {
				$lines[] = [
					'type' => 'refund',
					'skillId' => $skillId,
					'skillName' => $this->skillName(skillId: $skillId),
					'xp' => $this->costOf(character: $character, skillId: $skillId),
					'date' => $this->dateOf(log: (array)$log),
					'user' => (string)($log['userName'] ?? ($log['user'] ?? '')),
				];
			}
		}

		usort($lines, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

		return $lines;
	}//end refunds()

	/**
	 * The skill ids one audit row removed from the character.
	 *
	 * @param array<string, mixed> $log An audit-trail row.
	 *
	 * @return array<int, string> The removed ids.
	 */
	private function removedSkills(array $log): array {
		$change = ($log['changed']['skills'] ?? null);
		if (is_array($change) === false || array_key_exists('old', $change) === false) {
			return [];
		}

		return array_values(array_diff($this->ids(value: $change['old']), $this->ids(value: ($change['new'] ?? []))));
	}//end removedSkills()

	/**
	 * The ids in a stored `skills` value: a list of UUIDs, or of objects carrying one.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return array<int, string> The ids.
	 */
	private function ids(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		$ids = [];
		foreach ($value as $entry) {
			if (is_array($entry) === true) {
				$entry = ($entry['id'] ?? ($entry['uuid'] ?? null));
			}

			if (is_string($entry) === true && $entry !== '') {
				$ids[] = $entry;
			}
		}

		return $ids;
	}//end ids()

	/**
	 * What a skill costs the character in XP: XP left without it minus XP left with it.
	 *
	 * @param array<string, mixed> $character The character.
	 * @param string               $skillId   The skill.
	 *
	 * @return int The XP the skill costs, 0 when it costs none or no XP ability exists.
	 */
	private function costOf(array $character, string $skillId): int {
		$skills = $this->ids(value: ($character['skills'] ?? []));
		$without = array_values(array_diff($skills, [$skillId]));
		$with = array_merge($without, [$skillId]);

		$left = $this->xpLeft(character: array_merge($character, ['skills' => $without]));
		$spent = $this->xpLeft(character: array_merge($character, ['skills' => $with]));
		if ($left === null || $spent === null) {
			return 0;
		}

		return max(0, ($left - $spent));
	}//end costOf()

	/**
	 * XP left of a character as the engine computes it.
	 *
	 * @param array<string, mixed> $character The character.
	 *
	 * @return int|null XP left, or null when no XP ability exists.
	 */
	private function xpLeft(array $character): ?int {
		$stats = (array)($this->characterService->calculateCharacter(character: $character)['stats'] ?? []);
		$xpAbilityId = $this->requirementService->resolveXpAbility(stats: $stats);
		if ($xpAbilityId === null) {
			return null;
		}

		return (int)($stats[$xpAbilityId]['value'] ?? 0);
	}//end xpLeft()

	/**
	 * The name of a skill, or an empty string when it can no longer be read.
	 *
	 * @param string $skillId The skill.
	 *
	 * @return string The name.
	 */
	private function skillName(string $skillId): string {
		try {
			return (string)($this->objectFetcher->getObject(objectType: 'skill', id: $skillId)['name'] ?? '');
		} catch (Throwable) {
			return '';
		}
	}//end skillName()

	/**
	 * When an audit row was written, as an ISO 8601 string.
	 *
	 * @param array<string, mixed> $log An audit-trail row.
	 *
	 * @return string The moment, or an empty string.
	 */
	private function dateOf(array $log): string {
		$created = ($log['created'] ?? '');
		if ($created instanceof DateTimeInterface) {
			return $created->format(DATE_ATOM);
		}

		return (string)$created;
	}//end dateOf()
}//end class
