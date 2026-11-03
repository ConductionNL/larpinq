<?php

/**
 * Golden test for the character-write veto of CharacterRequirementListener.
 *
 * Each case sends one OpenRegister pre-write event through handle() with a
 * scripted custom-field guard and requirement result, and records what the
 * listener did: whether it stopped the event, the errors it set, which user
 * it asked the group manager about, whether it ran the requirement check and
 * whether it logged an error. Compared with
 * fixtures/character-requirement-listener.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\CharacterRequirementListener;
use OCA\Larpinq\Service\CustomFieldGuard;
use OCA\Larpinq\Service\SkillRequirementService;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An object entity with a schema id and a payload.
 */
class GoldenCharacterEntity extends ObjectEntity {
	/**
	 * Build an entity for a schema id and payload.
	 *
	 * @param string              $schema The schema id.
	 * @param array<string,mixed> $data   The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}//end __construct()
}//end class

/**
 * The character-write veto, case by case.
 *
 * @covers \OCA\Larpinq\Listener\CharacterRequirementListener
 * @uses   \OCA\Larpinq\Tests\Unit\Listener\GoldenCharacterEntity
 */
class CharacterRequirementListenerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/character-requirement-listener.golden.json';

	private const SCHEMA = 'char-schema';

	/**
	 * The cases, built from a few axes.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$base = ['name' => 'Mirela', 'skills' => ['s1'], 'items' => [], 'conditions' => []];
		$withReason = $base + ['requirementOverrides' => [['skill' => 's2', 'reason' => 'story']]];
		$noReason = $base + ['requirementOverrides' => [['skill' => 's2', 'reason' => '  ']]];
		$missingReason = $base + ['requirementOverrides' => [['skill' => 's2']]];
		$scalarOverrides = $base + ['requirementOverrides' => 'yes'];
		$scalarEntry = $base + ['requirementOverrides' => ['plain']];
		$moreSkills = array_replace($base, ['skills' => ['s1', 's9']]);
		$unmet = [
			'valid' => false,
			'requirements' => [
				['skill' => 'a', 'status' => 'met'],
				['skill' => 'b', 'status' => 'unmet'],
				['skill' => 'c', 'status' => 'unresolvable'],
				'not-an-entry',
				['skill' => 'd'],
			],
			'budget' => ['ok' => true, 'spent' => 3],
		];
		$overBudget = array_replace($unmet, ['budget' => ['ok' => false, 'spent' => 12, 'available' => 10]]);

		$cases = [
			'not a pre-write event' => ['event' => 'other'],
			'schema not configured' => ['schema' => '', 'new' => $moreSkills],
			'other schema' => ['entitySchema' => 'other', 'new' => $moreSkills],
			'create, valid' => ['old' => null, 'new' => $base],
			'create, unmet' => ['old' => null, 'new' => $base, 'result' => $unmet],
			'create, over budget' => ['old' => null, 'new' => $base, 'result' => $overBudget],
			'update, nothing relevant changed' => ['new' => array_replace($base, ['name' => 'Other']), 'result' => $unmet],
			'update, skills changed, valid' => ['new' => $moreSkills],
			'update, skills changed, unmet' => ['new' => $moreSkills, 'result' => $unmet],
			'update, items changed, over budget' => ['new' => array_replace($base, ['items' => ['i1']]), 'result' => $overBudget],
			'update, conditions changed, unmet' => ['new' => array_replace($base, ['conditions' => ['c1']]), 'result' => $unmet],
			'update, budget not an array' => ['new' => $moreSkills, 'result' => ['valid' => false, 'budget' => 'x']],
			'update, budget without ok' => ['new' => $moreSkills, 'result' => ['valid' => false, 'budget' => ['spent' => 1]]],
			'update, no requirements key' => ['new' => $moreSkills, 'result' => ['valid' => false]],
			'custom field error wins' => ['new' => $moreSkills, 'fields' => ['customFields' => ['scars' => 'number']], 'result' => $unmet],
			'custom field error, nothing changed' => ['new' => $base, 'fields' => ['customFields' => ['x' => 'y']]],
			'override by game master' => ['new' => $withReason],
			'override by game master, unmet' => ['new' => $withReason, 'result' => $unmet],
			'override by player' => ['new' => $withReason, 'uid' => 'player1', 'result' => $unmet],
			'override signed out' => ['new' => $withReason, 'uid' => null],
			'override without reason, game master' => ['new' => $noReason],
			'override without reason key, player' => ['new' => $missingReason, 'uid' => 'player1'],
			'override not an array, player' => ['new' => $scalarOverrides, 'uid' => 'player1'],
			'override not an array, game master' => ['new' => $scalarOverrides, 'result' => $unmet],
			'override entry not an array, player' => ['new' => $scalarEntry, 'uid' => 'player1'],
			'override unchanged, player' => ['old' => $withReason, 'new' => array_replace($withReason, ['skills' => ['s1', 's9']]), 'uid' => 'player1'],
			'override removed, player' => ['old' => $withReason, 'new' => $moreSkills, 'uid' => 'player1'],
			'create with override, player' => ['old' => null, 'new' => $withReason, 'uid' => 'player1'],
			'create with override, game master' => ['old' => null, 'new' => $withReason, 'result' => $overBudget],
			'validator throws' => ['new' => $moreSkills, 'throws' => true],
			'empty old object counts as changed' => ['old' => [], 'new' => $base, 'result' => $unmet],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'event' => 'pre-write',
				'schema' => self::SCHEMA,
				'entitySchema' => self::SCHEMA,
				'old' => $base,
				'new' => $base,
				'fields' => null,
				'result' => ['valid' => true],
				'uid' => 'gm1',
				'throws' => false,
			];
		}

		return $cases;
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $case) {
			$actual[$name] = json_encode(value: $this->outcome(case: $case), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Run one case through the listener.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> What the listener did.
	 */
	private function outcome(array $case): array {
		$trace = ['groupChecks' => [], 'validated' => 0, 'fieldChecks' => 0, 'loggedErrors' => 0];

		$fields = $this->createMock(originalClassName: CustomFieldGuard::class);
		$fields->method('check')->willReturnCallback(
			function () use ($case, &$trace): ?array {
				$trace['fieldChecks']++;
				return $case['fields'];
			}
		);

		$requirements = $this->createMock(originalClassName: SkillRequirementService::class);
		$requirements->method('validate')->willReturnCallback(
			function (array $candidate, array $oldCharacter) use ($case, &$trace): array {
				$trace['validated']++;
				$trace['validatedOld'] = $oldCharacter;
				if ($case['throws'] === true) {
					throw new \RuntimeException('boom');
				}

				return $case['result'];
			}
		);

		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'character_schema', '', $case['schema']]]);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($case['uid'] !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($case['uid']);
		}

		$session->method('getUser')->willReturn($user);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(
			function (string $uid, string $group) use (&$trace): bool {
				$trace['groupChecks'][] = $uid.'@'.$group;
				return $uid === 'gm1' && $group === 'gamemasters';
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function () use (&$trace): void {
				$trace['loggedErrors']++;
			}
		);

		$listener = new CharacterRequirementListener(
			requirementService: $requirements,
			config: $config,
			userSession: $session,
			groupManager: $groups,
			logger: $logger,
			customFields: $fields
		);

		$event = $this->event(case: $case);
		$listener->handle(event: $event);

		$trace['stopped'] = false;
		$trace['errors'] = null;
		if ($event instanceof ObjectCreatingEvent || $event instanceof ObjectUpdatingEvent) {
			$trace['stopped'] = $event->isPropagationStopped();
			$trace['errors'] = $event->getErrors();
		}

		return $trace;
	}//end outcome()

	/**
	 * The event a case sends.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return Event The event.
	 */
	private function event(array $case): Event {
		if ($case['event'] === 'other') {
			return new Event();
		}

		$new = new GoldenCharacterEntity($case['entitySchema'], $case['new']);
		if ($case['old'] === null) {
			return new ObjectCreatingEvent($new);
		}

		return new ObjectUpdatingEvent($new, new GoldenCharacterEntity($case['entitySchema'], $case['old']));
	}//end event()
}//end class
