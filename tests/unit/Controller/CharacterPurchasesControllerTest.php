<?php

/**
 * Tests for the character purchases endpoint (REQ-RPG-001).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CharacterPurchasesController;
use OCA\Larpinq\Service\PurchaseService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Only the owner or a game master buys for a character; refusals keep their status.
 *
 * @covers \OCA\Larpinq\Controller\CharacterPurchasesController
 * @uses   \OCA\Larpinq\Controller\CallerAccess
 * @uses   \OCA\Larpinq\Service\PurchaseService
 * @uses   \OCA\Larpinq\Service\PurchaseRefusedException
 */
class CharacterPurchasesControllerTest extends TestCase {

	private const MIRELA = 'c1000000-0000-4000-8000-000000000001';
	private const ROPE = 'e1000000-0000-4000-8000-000000000001';

	/** @var RegisterObjectFetcher&MockObject The fetcher double. */
	private RegisterObjectFetcher&MockObject $fetcher;

	protected function setUp(): void {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectOfType')->willReturnCallback(
			static function (string $objectType, string $id): array {
				return match ([$objectType, $id]) {
					['character', self::MIRELA] => ['id' => self::MIRELA, 'ownerUid' => 'anna', 'silver' => 5, 'items' => []],
					['item', self::ROPE] => ['id' => self::ROPE, 'price' => ['silver' => 2]],
					default => throw new DoesNotExistException('none'),
				};
			}
		);
	}//end setUp()

	/**
	 * The controller for a caller.
	 *
	 * @param string|null $uid        The signed-in user, or null for none.
	 * @param bool        $gameMaster Whether the caller is in the game master group.
	 *
	 * @return CharacterPurchasesController The controller.
	 */
	private function controller(?string $uid, bool $gameMaster = false): CharacterPurchasesController {
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturn($gameMaster);
		$groups->method('isAdmin')->willReturn(false);

		return new CharacterPurchasesController(
			request: $this->createMock(IRequest::class),
			purchases: new PurchaseService(fetcher: $this->fetcher),
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
		);
	}//end controller()

	/**
	 * The owner buys the rope and gets the saved character back.
	 */
	public function testTheOwnerBuys(): void {
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->willReturnCallback(static fn (string $type, array $data): array => $data);

		$response = $this->controller(uid: 'anna')->buy(id: self::MIRELA, itemId: self::ROPE);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(3, $response->getData()['silver']);
		$this->assertSame([self::ROPE], $response->getData()['items']);
	}//end testTheOwnerBuys()

	/**
	 * A game master buys for a character they do not own.
	 */
	public function testAGameMasterBuys(): void {
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')
			->willReturnCallback(static fn (string $type, array $data): array => $data);

		$response = $this->controller(uid: 'gm', gameMaster: true)->buy(id: self::MIRELA, itemId: self::ROPE);

		$this->assertSame(200, $response->getStatus());
	}//end testAGameMasterBuys()

	/**
	 * Another player is refused with 403 before anything is bought, and an anonymous caller with 401.
	 */
	public function testOthersAreRefused(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$other = $this->controller(uid: 'sanne')->buy(id: self::MIRELA, itemId: self::ROPE);
		$anon  = $this->controller(uid: null)->buy(id: self::MIRELA, itemId: self::ROPE);

		$this->assertSame(403, $other->getStatus());
		$this->assertSame(['error' => 'Only the owner and game masters buy for this character'], $other->getData());
		$this->assertSame(401, $anon->getStatus());
	}//end testOthersAreRefused()

	/**
	 * A missing item id answers 400, an unknown item or character 404.
	 */
	public function testBadRequestsKeepTheirStatus(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$controller = $this->controller(uid: 'anna');

		$this->assertSame(400, $controller->buy(id: self::MIRELA)->getStatus());
		$unknownItem = $controller->buy(id: self::MIRELA, itemId: 'e1000000-0000-4000-8000-000000000099');
		$this->assertSame(404, $unknownItem->getStatus());
		$this->assertSame(['error' => 'Item not found'], $unknownItem->getData());
		$this->assertSame(404, $controller->buy(id: self::ROPE, itemId: self::ROPE)->getStatus());
	}//end testBadRequestsKeepTheirStatus()
}//end class
