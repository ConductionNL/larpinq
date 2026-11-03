<?php

/**
 * EffectRun for Larpinq
 *
 * The state of one pass of effects over a character's abilities: the
 * abilities as they change, and the non-cumulative effects already applied.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * One pass of effects over abilities.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */
final class EffectRun {
	/**
	 * The effect ids already applied in this pass.
	 *
	 * @var array<string, bool>
	 */
	public array $applied = [];

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $abilities The abilities, by id.
	 * @param array<string, array<string, mixed>> $effects   Every effect of the world, by id.
	 */
	public function __construct(
		public array $abilities,
		public readonly array $effects,
	) {
	}//end __construct()

	/**
	 * The audit length of every ability, to tell later which entries are new.
	 *
	 * @return array<string, int> Audit length by ability id.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function auditCounts(): array {
		return array_map(static fn (array $score): int => count($score['audit']), $this->abilities);
	}//end auditCounts()

	/**
	 * Set the tag keys on every audit entry added since the counts were taken.
	 *
	 * @param array<string, int>    $auditCounts Audit length by ability id, from auditCounts().
	 * @param array<string, string> $tag         The keys and values to set on each new entry.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function tagSince(array $auditCounts, array $tag): void {
		foreach ($this->abilities as $abilityId => $score) {
			$total = count($score['audit']);
			for ($index = ($auditCounts[$abilityId] ?? 0); $index < $total; $index++) {
				$this->abilities[$abilityId]['audit'][$index] = array_replace($this->abilities[$abilityId]['audit'][$index], $tag);
			}
		}
	}//end tagSince()

	/**
	 * Add an XP award to an ability and record it on its audit.
	 *
	 * @param string               $abilityId The XP ability.
	 * @param array<string, mixed> $award     The award; its amount must be numeric or absent.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function addXpAward(string $abilityId, array $award): void {
		$old = (int)$this->abilities[$abilityId]['value'];
		$amount = (int)($award['amount'] ?? 0);
		$this->abilities[$abilityId]['value'] = ($old + $amount);
		$this->abilities[$abilityId]['audit'][] = [
			'type' => 'xpAward',
			'award' => self::awardSummary(award: $award, amount: $amount),
			'old' => $old,
			'new' => ($old + $amount),
		];
	}//end addXpAward()

	/**
	 * The award fields an XP audit entry records.
	 *
	 * @param array<string, mixed> $award  The award.
	 * @param int                  $amount The award's amount as an integer.
	 *
	 * @return array<string, int|string> The id, event, amount and reason.
	 */
	private static function awardSummary(array $award, int $amount): array {
		return [
			'id' => (string)($award['id'] ?? ''),
			'event' => (string)($award['event'] ?? ''),
			'amount' => $amount,
			'reason' => (string)($award['reason'] ?? ''),
		];
	}//end awardSummary()
}//end class
