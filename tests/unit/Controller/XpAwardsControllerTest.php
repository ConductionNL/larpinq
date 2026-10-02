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

	private bool $gameMaster = true;

	private bool $signedIn = true;

	private bool $eventExists = true;

	/** @var array<int, array<string, mixed>> Every save. */
	private array $saved = [];

	private XpAwardsController $controller;

	protected function setUp(): void {
		parent::setUp();

		$fetcher = $this->createMock(RegisterObjectFetcher::class);
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

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function (): ?IUser {
				if ($this->signedIn === false) {
					return null;
				}

				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn('joris');
				return $user;
			}
		);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(fn (): bool => $this->gameMaster);
		$groups->method('isAdmin')->willReturn(false);

		$roster = new EventRosterService($fetcher);
		$this->controller = new XpAwardsController(
			'larpinq',
			$this->createMock(IRequest::class),
			new XpAwardBatchService($roster, $fetcher),
			$roster,
			$session,
			$groups,
		);
	}//end setUp()

	/**
	 * A game master reads the roster and awards; the award names the game master.
	 *
	 * @return void
	 */
	public function testAGameMasterAwards(): void {
		$this->assertTrue($this->controller->access()->getData()['allowed']);
		$this->assertSame(self::MIRELA, $this->controller->roster(self::EVENT)->getData()['rows'][0]['character']);

		$response = $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]]);
		$this->assertSame(200, $response->getStatus());
		$this->assertCount(1, $response->getData()['created']);
		$this->assertSame('joris', $this->saved[0]['awardedBy']);
	}//end testAGameMasterAwards()

	/**
	 * A player is refused and nothing is written.
	 *
	 * @return void
	 */
	public function testAPlayerIsRefused(): void {
		$this->gameMaster = false;

		$this->assertFalse($this->controller->access()->getData()['allowed']);
		$this->assertSame(403, $this->controller->roster(self::EVENT)->getStatus());
		$this->assertSame(403, $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]])->getStatus());
		$this->assertSame([], $this->saved);

		$this->signedIn = false;
		$this->assertSame(401, $this->controller->access()->getStatus());
		$this->assertSame(401, $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]])->getStatus());
	}//end testAPlayerIsRefused()

	/**
	 * An unknown event is a 404, and an empty batch a 400.
	 *
	 * @return void
	 */
	public function testUnknownEventAndEmptyBatch(): void {
		$this->assertSame(400, $this->controller->award(self::EVENT, [])->getStatus());

		$this->eventExists = false;
		$this->assertSame(404, $this->controller->roster(self::EVENT)->getStatus());
		$this->assertSame(404, $this->controller->award(self::EVENT, [['character' => self::MIRELA, 'amount' => 5]])->getStatus());
		$this->assertSame([], $this->saved);
	}//end testUnknownEventAndEmptyBatch()
}//end class
