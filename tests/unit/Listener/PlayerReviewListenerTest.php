<?php

/**
 * Game masters review new players (players-self-signup, REQ-PSS-004): who
 * reviewed and when is stamped by the server.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-self-signup/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\PlayerReviewListener;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * An ObjectEntity with a schema and a payload.
 */
class ReviewObjectEntity extends ObjectEntity {
	/**
	 * Build an entity.
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
 * The listener over the real OpenRegister pre-update event.
 */
class PlayerReviewListenerTest extends TestCase {

	private const PLAYER_SCHEMA = 'player-schema-id';

	private ?string $signedIn = 'joris';

	/**
	 * The listener with the configured player schema and the signed-in user.
	 *
	 * @return PlayerReviewListener The listener.
	 */
	private function listener(): PlayerReviewListener {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($key === 'player_schema' ? self::PLAYER_SCHEMA : $default)
		);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function (): ?IUser {
				if ($this->signedIn === null) {
					return null;
				}

				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($this->signedIn);
				return $user;
			}
		);

		return new PlayerReviewListener($session, $config);
	}//end listener()

	/**
	 * Scenario "A game master welcomes Lotte": marking her reviewed stamps who
	 * and when, whatever the client sent.
	 *
	 * @return void
	 */
	public function testMarkingReviewedStampsWhoAndWhen(): void {
		$old = new ReviewObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Lotte Bakker', 'awaitingReview' => true]);
		$new = new ReviewObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Lotte Bakker', 'awaitingReview' => false, 'reviewedBy' => 'anna']);
		$event = new ObjectUpdatingEvent($new, $old);
		$event->setModifiedData(['kept' => 1]);
		$before = time();

		$this->listener()->handle($event);

		$modified = $event->getModifiedData();
		$this->assertSame(1, $modified['kept'], 'What other listeners changed is kept');
		$this->assertSame('joris', $modified['reviewedBy']);
		$this->assertGreaterThanOrEqual($before, strtotime($modified['reviewedAt']));
	}//end testMarkingReviewedStampsWhoAndWhen()

	/**
	 * Any other update keeps the stored review stamp.
	 *
	 * @return void
	 */
	public function testAnotherUpdateKeepsTheStoredStamp(): void {
		$old = new ReviewObjectEntity(self::PLAYER_SCHEMA, ['awaitingReview' => false, 'reviewedBy' => 'joris', 'reviewedAt' => '2026-09-30T10:00:00+00:00']);
		$new = new ReviewObjectEntity(self::PLAYER_SCHEMA, ['awaitingReview' => false, 'description' => 'Plays healers', 'reviewedBy' => 'anna', 'reviewedAt' => '2020-01-01T00:00:00+00:00']);
		$event = new ObjectUpdatingEvent($new, $old);

		$this->listener()->handle($event);

		$this->assertSame(['reviewedBy' => 'joris', 'reviewedAt' => '2026-09-30T10:00:00+00:00'], $event->getModifiedData());
	}//end testAnotherUpdateKeepsTheStoredStamp()

	/**
	 * A system write, or another object, is left alone.
	 *
	 * @return void
	 */
	public function testSystemWritesAndOtherObjectsAreLeftAlone(): void {
		$other = new ObjectUpdatingEvent(
			new ReviewObjectEntity('character-schema', ['awaitingReview' => false]),
			new ReviewObjectEntity('character-schema', ['awaitingReview' => true])
		);
		$this->listener()->handle($other);
		$this->assertSame([], $other->getModifiedData());

		$this->signedIn = null;
		$system = new ObjectUpdatingEvent(
			new ReviewObjectEntity(self::PLAYER_SCHEMA, ['awaitingReview' => false]),
			new ReviewObjectEntity(self::PLAYER_SCHEMA, ['awaitingReview' => true])
		);
		$this->listener()->handle($system);
		$this->assertSame([], $system->getModifiedData());
	}//end testSystemWritesAndOtherObjectsAreLeftAlone()
}//end class
