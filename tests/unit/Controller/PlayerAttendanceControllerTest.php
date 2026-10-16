<?php

/**
 * GET /api/players/{id}/attendance: the events a player was checked in at,
 * newest first, for game masters and for the player themself
 * (players-attendance-history REQ-PAH-001, REQ-PAH-002).
 *
 * attendance is read by game masters and the record's owner only (DECISIONS
 * row 30), and the owner is the game master who checked the character in. So
 * the player's own list comes from this endpoint, which reads with the app's
 * authority after larpinq's own check that the stored player is the caller's.
 * Driven through the real controller, service, guard and fetcher over
 * OpenRegister's real read evaluator.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/events-players/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

require_once dirname(__DIR__) . '/Service/RealEvaluatorRegister.php';

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Controller\PlayerAttendanceController;
use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\PlayerAttendanceService;
use OCA\Larpinq\Tests\Unit\Service\RealEvaluatorRegister;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Anna's two seasons, seen by Anna, by a game master and by Karel.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class PlayerAttendanceControllerTest extends TestCase {
	use RealEvaluatorRegister;

	private const ANNA = '00000000-0000-4000-8000-0000000a0001';
	private const KAREL = '00000000-0000-4000-8000-0000000a0002';
	private const MIRELA = '00000000-0000-4000-8000-0000000c0001';
	private const HARROW = '00000000-0000-4000-8000-0000000c0002';
	private const OSWIN = '00000000-0000-4000-8000-0000000c0003';
	private const SPRING = '00000000-0000-4000-8000-0000000e0001';
	private const SUMMER = '00000000-0000-4000-8000-0000000e0002';

	/**
	 * The fake OpenRegister of the last controller built.
	 *
	 * @var object
	 */
	private object $store;

	/**
	 * The controller as the signed-in user.
	 *
	 * @param string $uid The user.
	 * @param bool $gameMaster Whether the user is in the game masters group.
	 *
	 * @return PlayerAttendanceController The controller.
	 */
	private function controllerAs(string $uid, bool $gameMaster = false): PlayerAttendanceController {
		$this->requireOpenRegister();
		$groups = ($gameMaster === true ? [Application::GM_GROUP, 'larpers'] : ['larpers']);
		$this->store = $this->openRegister($this->permissionHandler($uid, $groups), $uid, $this->authorizationBlocks());

		$this->store->seed('player', 'admin', ['id' => self::ANNA, 'name' => 'Anna de Vries', 'userUid' => 'anna']);
		$this->store->seed('player', 'admin', ['id' => self::KAREL, 'name' => 'Karel Jansen', 'userUid' => 'karel']);
		$this->store->seed('character', 'anna', ['id' => self::MIRELA, 'name' => 'Mirela the Wanderer', 'ocName' => self::ANNA, 'ownerUid' => 'anna']);
		$this->store->seed('character', 'anna', ['id' => self::HARROW, 'name' => 'Old Captain Harrow', 'ocName' => self::ANNA, 'ownerUid' => 'anna']);
		$this->store->seed('character', 'karel', ['id' => self::OSWIN, 'name' => 'Oswin', 'ocName' => self::KAREL, 'ownerUid' => 'karel']);
		$this->store->seed('event', 'gerrit', ['id' => self::SPRING, 'name' => 'Spring Moot 2025', 'startDate' => '2025-04-12T10:00:00+00:00']);
		$this->store->seed('event', 'gerrit', ['id' => self::SUMMER, 'name' => 'Summer Siege 2025', 'startDate' => '2025-07-20T10:00:00+00:00']);
		// The game master gerrit checked everyone in, so he owns every record.
		$this->store->seed('attendance', 'gerrit', ['id' => '00000000-0000-4000-8000-0000000d0001', 'event' => self::SPRING, 'character' => self::HARROW, 'status' => 'checked-in', 'checkedInAt' => '2025-04-12T09:40:00+00:00']);
		$this->store->seed('attendance', 'gerrit', ['id' => '00000000-0000-4000-8000-0000000d0002', 'event' => self::SUMMER, 'character' => self::MIRELA, 'status' => 'checked-in', 'checkedInAt' => '2025-07-20T09:30:00+00:00']);
		$this->store->seed('attendance', 'gerrit', ['id' => '00000000-0000-4000-8000-0000000d0003', 'event' => self::SPRING, 'character' => self::MIRELA, 'status' => 'no-show']);
		$this->store->seed('attendance', 'gerrit', ['id' => '00000000-0000-4000-8000-0000000d0004', 'event' => self::SUMMER, 'character' => self::OSWIN, 'status' => 'checked-in']);

		$fetcher = $this->fetcherOver($this->store);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isInGroup')->willReturn($gameMaster);
		$groupManager->method('isAdmin')->willReturn(false);

		return new PlayerAttendanceController(
			'larpinq',
			$this->createMock(IRequest::class),
			new PlayerAttendanceService($fetcher),
			new CharacterConnectionGuard($fetcher),
			$this->sessionOf($uid),
			$groupManager,
		);
	}//end controllerAs()

	/**
	 * The event names of a response, in order.
	 *
	 * @param array<string, mixed> $data The response body.
	 *
	 * @return list<string> The names.
	 */
	private function eventNames(array $data): array {
		return array_map(static fn (array $row): string => $row['event']['name'], $data['events']);
	}//end eventNames()

	/**
	 * Control: the real evaluator refuses Anna the attendance record through
	 * the object API, so her list below comes from larpinq's own read.
	 *
	 * @return void
	 */
	public function testTheEvaluatorHidesAttendanceFromThePlayer(): void {
		$this->controllerAs('anna');
		$this->assertFalse($this->store->readable('attendance', $this->store->objects['attendance']['00000000-0000-4000-8000-0000000d0002'], true));
	}//end testTheEvaluatorHidesAttendanceFromThePlayer()

	/**
	 * Anna sees her own two seasons, newest first, each with its character.
	 *
	 * @return void
	 */
	public function testAPlayerSeesHerOwnHistoryNewestFirst(): void {
		$response = $this->controllerAs('anna')->index(self::ANNA);

		$this->assertSame(200, $response->getStatus());
		$data = $response->getData();
		$this->assertSame(['Summer Siege 2025', 'Spring Moot 2025'], $this->eventNames($data));
		$this->assertSame(['Mirela the Wanderer', 'Old Captain Harrow'], array_map(static fn (array $row): string => $row['character']['name'], $data['events']));
		$this->assertSame('2025-07-20T10:00:00+00:00', $data['events'][0]['eventStartDate']);
		$this->assertSame(2, $data['count']);
	}//end testAPlayerSeesHerOwnHistoryNewestFirst()

	/**
	 * A game master opening Anna's page sees the same list.
	 *
	 * @return void
	 */
	public function testAGameMasterSeesAPlayersHistory(): void {
		$data = $this->controllerAs('gerrit', gameMaster: true)->index(self::ANNA)->getData();

		$this->assertSame(['Summer Siege 2025', 'Spring Moot 2025'], $this->eventNames($data));
		$this->assertSame(2, $data['count']);
	}//end testAGameMasterSeesAPlayersHistory()

	/**
	 * Karel is refused Anna's history, and nothing is read with the app's
	 * authority on his behalf.
	 *
	 * @return void
	 */
	public function testAnotherPlayerIsRefused(): void {
		$response = $this->controllerAs('karel')->index(self::ANNA);

		$this->assertSame(403, $response->getStatus());
		$this->assertArrayNotHasKey('events', $response->getData());
		$this->assertSame([], array_values(array_filter($this->store->reads, static fn (array $read): bool => $read['rbac'] === false)));
	}//end testAnotherPlayerIsRefused()

	/**
	 * Karel sees his own one event, not Anna's.
	 *
	 * @return void
	 */
	public function testKarelSeesOnlyHisOwn(): void {
		$data = $this->controllerAs('karel')->index(self::KAREL)->getData();

		$this->assertSame(['Summer Siege 2025'], $this->eventNames($data));
		$this->assertSame(['Oswin'], array_map(static fn (array $row): string => $row['character']['name'], $data['events']));
		$this->assertSame(1, $data['count']);
	}//end testKarelSeesOnlyHisOwn()
}//end class
