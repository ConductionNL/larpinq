<?php

/**
 * Unit tests for CharactersController::gameMasterAccess(): who the character
 * page offers the requirement override action to.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Conduction <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CharactersController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The override action is offered to game masters and administrators only.
 */
class CharactersControllerGameMasterAccessTest extends TestCase {

	/**
	 * A controller for a caller.
	 *
	 * @param string|null $uid     The signed-in user, or null for nobody.
	 * @param bool        $gm      Whether the user is in the game master group.
	 * @param bool        $admin   Whether the user is an administrator.
	 *
	 * @return CharactersController
	 */
	private function controller(?string $uid, bool $gm = false, bool $admin = false): CharactersController {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(
			static fn (string $who, string $group): bool => $gm === true && $who === $uid && $group === 'gamemasters'
		);
		$groups->method('isAdmin')->willReturn($admin);

		return new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $this->createMock(originalClassName: RegisterObjectFetcher::class),
			pdfRenderer: $this->createMock(originalClassName: DocuDeskPdfRenderer::class),
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
			requirementService: $this->createMock(originalClassName: SkillRequirementService::class),
		);
	}//end controller()

	/**
	 * A game master gets the action.
	 *
	 * @return void
	 */
	public function testAGameMasterMayOverride(): void {
		$response = $this->controller(uid: 'joris', gm: true)->gameMasterAccess();

		self::assertSame(expected: 200, actual: $response->getStatus());
		self::assertSame(expected: ['allowed' => true], actual: $response->getData());
	}//end testAGameMasterMayOverride()

	/**
	 * An administrator gets it too.
	 *
	 * @return void
	 */
	public function testAnAdministratorMayOverride(): void {
		self::assertSame(expected: ['allowed' => true], actual: $this->controller(uid: 'admin', admin: true)->gameMasterAccess()->getData());
	}//end testAnAdministratorMayOverride()

	/**
	 * A player gets no override action.
	 *
	 * @return void
	 */
	public function testAPlayerHasNoOverride(): void {
		self::assertSame(expected: ['allowed' => false], actual: $this->controller(uid: 'sem')->gameMasterAccess()->getData());
	}//end testAPlayerHasNoOverride()

	/**
	 * Nobody signed in is answered 401.
	 *
	 * @return void
	 */
	public function testNobodySignedInIsUnauthorised(): void {
		self::assertSame(expected: 401, actual: $this->controller(uid: null)->gameMasterAccess()->getStatus());
	}//end testNobodySignedInIsUnauthorised()
}//end class
