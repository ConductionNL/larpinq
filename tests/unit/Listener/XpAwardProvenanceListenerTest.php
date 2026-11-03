<?php

/**
 * Award provenance is stamped by the server (events-xp-batch-award, REQ-EXB-002).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\XpAwardProvenanceListener;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * An ObjectEntity with a schema and a payload.
 */
class AwardObjectEntity extends ObjectEntity {
	/**
	 * Build an entity for a schema id and payload.
	 *
	 * @param string $schema The schema id.
	 * @param array<string,mixed> $data The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * The listener over the real OpenRegister pre-write events.
 */
class XpAwardProvenanceListenerTest extends TestCase {

	private const SCHEMA_ID = 'xp-award-schema-id';

	private ?string $signedIn = 'joris';

	/**
	 * The listener with the configured xpAward schema and the signed-in user.
	 *
	 * @return XpAwardProvenanceListener The listener.
	 */
	private function listener(): XpAwardProvenanceListener {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($key === 'xpaward_schema' ? self::SCHEMA_ID : $default)
		);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function (): ?IUser {
				if ($this->signedIn === null) {
					return null;
				}

				$user = $this->createMock(originalClassName: IUser::class);
				$user->method('getUID')->willReturn($this->signedIn);
				return $user;
			}
		);

		return new XpAwardProvenanceListener(userSession: $session, config: $config);
	}//end listener()

	/**
	 * A client that sends its own provenance gets the server's.
	 *
	 * @return void
	 */
	public function testACreatedAwardGetsTheActingUserAndTheTime(): void {
		$event = new ObjectCreatingEvent(
			new AwardObjectEntity(self::SCHEMA_ID, ['amount' => 5, 'awardedBy' => 'anna', 'awardedAt' => '2020-01-01T00:00:00+00:00'])
		);
		$before = time();
		$this->listener()->handle($event);

		$modified = $event->getModifiedData();
		$this->assertSame(expected: 'joris', actual: $modified['awardedBy']);
		$this->assertGreaterThanOrEqual(expected: $before, actual: strtotime($modified['awardedAt']));
		$this->assertFalse(condition: $event->isPropagationStopped());
	}//end testACreatedAwardGetsTheActingUserAndTheTime()

	/**
	 * An update keeps the original provenance, whatever the client sent.
	 *
	 * @return void
	 */
	public function testAnUpdateKeepsTheOriginalProvenance(): void {
		$old = new AwardObjectEntity(self::SCHEMA_ID, ['amount' => 3, 'awardedBy' => 'joris', 'awardedAt' => '2026-09-01T10:00:00+00:00']);
		$new = new AwardObjectEntity(
			self::SCHEMA_ID,
			['amount' => 1, 'reason' => 'Day guest', 'awardedBy' => 'anna', 'awardedAt' => '2026-09-30T10:00:00+00:00']
		);
		$event = new ObjectUpdatingEvent($new, $old);
		$this->listener()->handle($event);

		$this->assertSame(expected: ['awardedBy' => 'joris', 'awardedAt' => '2026-09-01T10:00:00+00:00'], actual: $event->getModifiedData());
	}//end testAnUpdateKeepsTheOriginalProvenance()

	/**
	 * Other schemas, and a write with nobody signed in, are left alone.
	 *
	 * @return void
	 */
	public function testOtherObjectsAndSystemWritesAreLeftAlone(): void {
		$other = new ObjectCreatingEvent(new AwardObjectEntity('character-schema', ['awardedBy' => 'anna']));
		$this->listener()->handle($other);
		$this->assertSame(expected: [], actual: $other->getModifiedData());

		$this->signedIn = null;
		$seed = new ObjectCreatingEvent(new AwardObjectEntity(self::SCHEMA_ID, ['awardedBy' => 'joris']));
		$this->listener()->handle($seed);
		$this->assertSame(expected: [], actual: $seed->getModifiedData());
	}//end testOtherObjectsAndSystemWritesAreLeftAlone()
}//end class
