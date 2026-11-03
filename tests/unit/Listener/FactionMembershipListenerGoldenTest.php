<?php

/**
 * Golden test for the faction, relationship and build veto of FactionMembershipListener.
 *
 * Each case sends one OpenRegister pre-write event through handle() with a
 * scripted connection guard, and records what the listener did: which guard
 * check it ran with which arguments, which user it asked the group manager
 * about, whether it stopped the event, the errors it set and whether it
 * logged an error. Compared with fixtures/faction-membership-listener.golden.json.
 * Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\FactionMembershipListener;
use OCA\Larpinq\Service\CharacterConnectionGuard;
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
class GoldenConnectionEntity extends ObjectEntity {
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
 * The connection veto, case by case.
 *
 * @covers \OCA\Larpinq\Listener\FactionMembershipListener
 * @uses   \OCA\Larpinq\Tests\Unit\Listener\GoldenConnectionEntity
 */
class FactionMembershipListenerGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/faction-membership-listener.golden.json';

	/**
	 * The schema id configured for each kind.
	 */
	private const SCHEMAS = [
		'faction' => 's-faction',
		'factionmember' => 's-member',
		'relationship' => 's-relation',
		'characterbuild' => 's-build',
	];

	/**
	 * The cases.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$cases = [
			'not a pre-write event' => ['event' => 'other'],
			'other schema' => ['schema' => 's-other'],
			'empty schema' => ['schema' => ''],
			'signed out' => ['uid' => null],
			'admin' => ['uid' => 'admin1'],
			'game master' => ['uid' => 'gm1'],
			'guard throws' => ['throws' => true],
			'guard throws on a build update' => ['schema' => 's-build', 'throws' => true],
		];
		foreach (self::SCHEMAS as $kind => $schema) {
			foreach (['create', 'update'] as $mode) {
				foreach (['allowed' => null, 'refused' => ['code' => 'no_'.$kind, 'message' => 'refused '.$kind]] as $label => $verdict) {
					$cases[$kind.', '.$mode.', player, '.$label] = ['schema' => $schema, 'mode' => $mode, 'verdict' => $verdict];
				}
			}

			$cases[$kind.', update, game master'] = ['schema' => $schema, 'uid' => 'gm1'];
			$cases[$kind.', create, signed out'] = ['schema' => $schema, 'mode' => 'create', 'uid' => null];
		}

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'event' => 'pre-write',
				'schema' => 's-member',
				'mode' => 'update',
				'uid' => 'player1',
				'verdict' => ['code' => 'refused', 'message' => 'refused'],
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
		$trace = ['guard' => [], 'groupChecks' => [], 'loggedErrors' => 0];

		$guard = $this->createMock(originalClassName: CharacterConnectionGuard::class);
		foreach (['checkFaction', 'checkMembership', 'checkRelationship', 'checkBuild'] as $method) {
			$guard->method($method)->willReturnCallback(
				function (...$args) use ($method, $case, &$trace): ?array {
					$trace['guard'][] = [$method => $args];
					if ($case['throws'] === true) {
						throw new \RuntimeException('unreadable');
					}

					return $case['verdict'];
				}
			);
		}

		$config = $this->createMock(originalClassName: IAppConfig::class);
		$map = [];
		foreach (self::SCHEMAS as $kind => $schema) {
			$map[] = ['larpinq', $kind.'_schema', '', $schema];
		}

		$config->method('getValueString')->willReturnMap($map);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($case['uid'] !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($case['uid']);
		}

		$session->method('getUser')->willReturn($user);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(
			function (string $uid) use (&$trace): bool {
				$trace['groupChecks'][] = $uid.'@admin';
				return $uid === 'admin1';
			}
		);
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

		$listener = new FactionMembershipListener(
			guard: $guard,
			config: $config,
			userSession: $session,
			groupManager: $groups,
			logger: $logger
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

		$new = new GoldenConnectionEntity($case['schema'], ['id' => 'new', 'faction' => 'f1', 'character' => 'c1']);
		if ($case['mode'] === 'create') {
			return new ObjectCreatingEvent($new);
		}

		return new ObjectUpdatingEvent($new, new GoldenConnectionEntity($case['schema'], ['id' => 'old', 'faction' => 'f0']));
	}//end event()
}//end class
