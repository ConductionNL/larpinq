<?php

/**
 * Only game masters award XP in a batch (events-xp-batch-award, REQ-EXB-001).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\XpAwardsController;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\XpAwardBatchService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The HTTP boundary: who may call, and the event must exist.
 */
class XpAwardsControllerTest extends TestCase {

	private const EVENT = '5a5a5a5a-0000-4000-8000-000000000001';
	private const MIRELA = 'c1c1c1c1-0000-4000-8000-000000000001';

	/** @var bool Whether game master. */
	private bool $gameMaster = true;

	/** @var bool Whether signed in. */
	private bool $signedIn = true;

	/** @var bool Whether event exists. */
	private bool $eventExists = true;

	/** @var array<int, array<string, mixed>> Every save. */
	private array $saved = [];

	/** @var XpAwardsController The controller. */
	private XpAwardsController $controller;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id): array {
				if ($this->eventExists === false) {
					throw new \Exception('not found');
				}

				return ['id' => $id, 'name' => 'Summer Siege 2025'];
			}
		);
		$fetcher->method('getObjects')->willReturnCallback(
			static fn (string $objectType): array => ($objectType === 'character' ? [['id' => self::MIRELA, 'name' => 'Mirela', 'events' => [self::EVENT]]] : [])
		);
		$fetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data): array {
				$this->saved[] = $data;
				return ['id' => 'award-1'] + $data;
			}
		);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function (): ?IUser {
				if ($this->signedIn === false) {
					return null;
				}

				$user = $this->createMock(originalClassName: IUser::class);
				$user->method('getUID')->willReturn('joris');
				return $user;
			}
		);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(fn (): bool => $this->gameMaster);
		$groups->method('isAdmin')->willReturn(false);

		$roster = new EventRosterService(objectFetcher: $fetcher);
		$this->controller = new XpAwardsController(
			request: $this->createMock(originalClassName: IRequest::class),
			batch: new XpAwardBatchService(rosterService: $roster, objectFetcher: $fetcher),
			rosterService: $roster,
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
		);
	}//end setUp()

	/**
	 * A game master reads the roster and awards; the award names the game master.
	 *
	 * @return void
	 */
	public function testAGameMasterAwards(): void {
		$this->assertTrue(condition: $this->controller->access()->getData()['allowed']);
		$this->assertSame(expected: self::MIRELA, actual: $this->controller->roster(self::EVENT)->getData()['rows'][0]['character']);

		$response = $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]]);
		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertCount(expectedCount: 1, haystack: $response->getData()['created']);
		$this->assertSame(expected: 'joris', actual: $this->saved[0]['awardedBy']);
	}//end testAGameMasterAwards()

	/**
	 * A player is refused and nothing is written.
	 *
	 * @return void
	 */
	public function testAPlayerIsRefused(): void {
		$this->gameMaster = false;

		$this->assertFalse(condition: $this->controller->access()->getData()['allowed']);
		$this->assertSame(expected: 403, actual: $this->controller->roster(self::EVENT)->getStatus());
		$this->assertSame(expected: 403, actual: $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]])->getStatus());
		$this->assertSame(expected: [], actual: $this->saved);

		$this->signedIn = false;
		$this->assertSame(expected: 401, actual: $this->controller->access()->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]])->getStatus());
	}//end testAPlayerIsRefused()

	/**
	 * An unknown event is a 404, and an empty batch a 400.
	 *
	 * @return void
	 */
	public function testUnknownEventAndEmptyBatch(): void {
		$this->assertSame(expected: 400, actual: $this->controller->award(self::EVENT, [])->getStatus());

		$this->eventExists = false;
		$this->assertSame(expected: 404, actual: $this->controller->roster(self::EVENT)->getStatus());
		$this->assertSame(expected: 404, actual: $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]])->getStatus());
		$this->assertSame(expected: [], actual: $this->saved);
	}//end testUnknownEventAndEmptyBatch()
}//end class
