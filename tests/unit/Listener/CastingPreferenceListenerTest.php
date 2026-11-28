<?php

/**
 * CastingPreferenceListenerTest: the real OpenRegister event classes and the
 * real guard over a mocked object reader (events-casting-and-crew-roles REQ-ECC-001).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use DateTimeImmutable;
use OCA\Larpinq\Listener\CastingPreferenceListener;
use OCA\Larpinq\Service\CastingPreferenceGuard;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The real OpenRegister entity, with a schema id and a payload.
 */
class CastingObjectEntity extends ObjectEntity {
	/**
	 * @param string              $schema The schema id.
	 * @param array<string,mixed> $data   The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * The casting deadline holds on every write of a ranking.
 *
 * @covers \OCA\Larpinq\Listener\CastingPreferenceListener
 * @uses \OCA\Larpinq\Service\CastingPreferenceGuard
 * @uses \OCA\Larpinq\Listener\ConfiguredSchema
 * @uses \OCA\Larpinq\Listener\PreWriteEvent
 */
class CastingPreferenceListenerTest extends TestCase {

	private const SCHEMA_ID = 'pref-schema-uuid';

	private const RANKING = ['event' => 'e-winter', 'registration' => 'r-anna', 'character' => 'heir', 'rank' => 1];

	/**
	 * The listener for a signed-in account at a moment.
	 *
	 * @param string|null $uid The signed-in account, or null for none.
	 * @param string      $now The moment.
	 *
	 * @return CastingPreferenceListener The listener.
	 */
	private function listener(?string $uid, string $now): CastingPreferenceListener {
		$objects = [
			'event' => ['e-winter' => ['castingDeadline' => '2026-11-01T00:00:00+00:00']],
			'registration' => ['r-anna' => ['event' => 'e-winter', 'playerUid' => 'anna']],
			'character' => ['heir' => ['castingEvent' => 'e-winter']],
		];
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjectOfType')->willReturnCallback(static fn (string $type, string $id): array => $objects[$type][$id]);
		$fetcher->method('getObjectsWithAppAuthority')->willReturn([]);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willReturn(false);
		$groups->method('isAdmin')->willReturn(false);

		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key): string => $key === 'castingpreference_schema' ? self::SCHEMA_ID : ''
		);

		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable($now));

		return new CastingPreferenceListener(
			guard: new CastingPreferenceGuard(fetcher: $fetcher, groups: $groups),
			config: $config,
			userSession: $session,
			time: $time
		);
	}

	public function testAnnaRanksBeforeTheDeadline(): void {
		$event = new ObjectCreatingEvent(new CastingObjectEntity(self::SCHEMA_ID, self::RANKING));

		$this->listener(uid: 'anna', now: '2026-10-15')->handle($event);

		$this->assertFalse(condition: $event->isPropagationStopped());
	}

	public function testARankingAfterTheDeadlineIsRefusedOnCreateAndUpdate(): void {
		$create = new ObjectCreatingEvent(new CastingObjectEntity(self::SCHEMA_ID, self::RANKING));
		$this->listener(uid: 'anna', now: '2026-11-02')->handle($create);

		$this->assertTrue(condition: $create->isPropagationStopped());
		$this->assertSame(expected: 'casting_closed', actual: $create->getErrors()['code']);

		$update = new ObjectUpdatingEvent(
			new CastingObjectEntity(self::SCHEMA_ID, array_merge(self::RANKING, ['rank' => 2])),
			new CastingObjectEntity(self::SCHEMA_ID, self::RANKING)
		);
		$this->listener(uid: 'anna', now: '2026-11-02')->handle($update);

		$this->assertTrue(condition: $update->isPropagationStopped());
	}

	public function testOtherSchemasSystemWritesAndOtherEventsPass(): void {
		$other = new ObjectCreatingEvent(new CastingObjectEntity('other-schema', self::RANKING));
		$this->listener(uid: 'anna', now: '2026-11-02')->handle($other);
		$this->assertFalse(condition: $other->isPropagationStopped(), message: 'other schema');

		$system = new ObjectCreatingEvent(new CastingObjectEntity(self::SCHEMA_ID, self::RANKING));
		$this->listener(uid: null, now: '2026-11-02')->handle($system);
		$this->assertFalse(condition: $system->isPropagationStopped(), message: 'no signed-in user');

		$plain = new Event();
		$this->listener(uid: 'anna', now: '2026-11-02')->handle($plain);
		$this->assertFalse(condition: $plain->isPropagationStopped(), message: 'plain event');
	}
}
