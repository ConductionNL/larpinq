<?php

/**
 * Unit tests for CharacterResubmissionController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CharacterResubmissionController;
use OCA\Larpinq\Service\CharacterResubmissionService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The resubmit endpoint: the caller, the game master check and the refusals as HTTP answers.
 */
class CharacterResubmissionControllerTest extends TestCase {

	private const MIRELA = 'c1000000-0000-4000-8000-000000000001';

	/** @var RegisterObjectFetcher&MockObject The fetcher double. */
	private RegisterObjectFetcher&MockObject $fetcher;

	/**
	 * A controller for a caller over the real service.
	 *
	 * @param string|null $uid The signed-in user, or null for nobody.
	 * @param bool        $gm  Whether the user is a game master.
	 *
	 * @return CharacterResubmissionController
	 */
	private function controller(?string $uid, bool $gm = false): CharacterResubmissionController {
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturn($gm);
		$groups->method('isAdmin')->willReturn(false);

		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectOfType')->willReturn(
			['id' => self::MIRELA, 'ownerUid' => 'anna', 'approved' => 'rejected', 'rejectionReason' => 'Pick one bloodline, not two']
		);

		return new CharacterResubmissionController(
			request: $this->createMock(IRequest::class),
			resubmission: new CharacterResubmissionService(fetcher: $this->fetcher),
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
		);
	}//end controller()

	/**
	 * The owner's resubmission answers the saved character.
	 *
	 * @return void
	 */
	public function testTheOwnerGetsTheSavedCharacter(): void {
		$controller = $this->controller(uid: 'anna');
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->willReturnArgument(1);

		$response = $controller->resubmit(id: self::MIRELA);

		self::assertSame(expected: 200, actual: $response->getStatus());
		self::assertSame(expected: 'no', actual: $response->getData()['approved']);
	}//end testTheOwnerGetsTheSavedCharacter()

	/**
	 * A game master who does not own it may submit it too.
	 *
	 * @return void
	 */
	public function testAGameMasterMaySubmit(): void {
		$controller = $this->controller(uid: 'joris', gm: true);
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->willReturnArgument(1);

		self::assertSame(expected: 200, actual: $controller->resubmit(id: self::MIRELA)->getStatus());
	}//end testAGameMasterMaySubmit()

	/**
	 * Another player is answered 403 with the reason, and nothing is written.
	 *
	 * @return void
	 */
	public function testAnotherPlayerIsForbidden(): void {
		$controller = $this->controller(uid: 'sem');
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$response = $controller->resubmit(id: self::MIRELA);

		self::assertSame(expected: 403, actual: $response->getStatus());
		self::assertSame(expected: ['error' => 'Only the owner and game masters submit this character'], actual: $response->getData());
	}//end testAnotherPlayerIsForbidden()

	/**
	 * Nobody signed in is answered 401 before anything is read.
	 *
	 * @return void
	 */
	public function testNobodySignedInIsUnauthorised(): void {
		$controller = $this->controller(uid: null);
		$this->fetcher->expects($this->never())->method('getObjectOfType');

		self::assertSame(expected: 401, actual: $controller->resubmit(id: self::MIRELA)->getStatus());
	}//end testNobodySignedInIsUnauthorised()
}//end class
