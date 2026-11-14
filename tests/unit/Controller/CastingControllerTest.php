<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CastingController;
use OCA\Larpinq\Service\CastingRefusedException;
use OCA\Larpinq\Service\CastingService;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ECC-002 and REQ-ECC-003: game masters only.
 *
 * @covers \OCA\Larpinq\Controller\CastingController
 * @uses \OCA\Larpinq\Controller\CallerAccess
 */
class CastingControllerTest extends TestCase {

	private CastingService&MockObject $casting;

	/** @var array<string, mixed> */
	private array $params = [];

	private function controller(?string $uid, bool $isGm = false): CastingController {
		$this->casting ??= $this->createMock(CastingService::class);
		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturn($isGm);
		$groups->method('isAdmin')->willReturn(false);
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, mixed $default = null): mixed => ($this->params[$key] ?? $default));

		return new CastingController($request, $this->casting, new CallerAccess(userSession: $session, groupManager: $groups));
	}

	protected function setUp(): void {
		$this->casting = $this->createMock(CastingService::class);
	}

	public function testAPlayerCannotAskForTheProposal(): void {
		$this->casting->expects(self::never())->method('propose');

		self::assertSame(403, $this->controller('anna')->proposal('e-1')->getStatus());
	}

	public function testAPlayerCannotConfirm(): void {
		$this->params = ['pairs' => [['registration' => 'r-anna', 'character' => 'heir']]];
		$this->casting->expects(self::never())->method('confirm');

		self::assertSame(403, $this->controller('anna')->confirm('e-1')->getStatus());
	}

	public function testWithoutASessionNothingIsRead(): void {
		$this->casting->expects(self::never())->method('propose');

		self::assertSame(401, $this->controller(null)->proposal('e-1')->getStatus());
	}

	public function testAGameMasterGetsTheProposal(): void {
		$this->casting->expects(self::once())->method('propose')->with('e-1')->willReturn(['pairs' => [], 'unmatched' => [], 'characters' => []]);

		$response = $this->controller('gm', isGm: true)->proposal('e-1');

		self::assertSame(200, $response->getStatus());
		self::assertSame(['pairs' => [], 'unmatched' => [], 'characters' => []], $response->getData());
	}

	public function testATooLargeCastingIsRefused(): void {
		$this->casting->method('propose')->willThrowException(new CastingRefusedException('too large'));

		self::assertSame(422, $this->controller('gm', isGm: true)->proposal('e-1')->getStatus());
	}

	public function testAGameMasterConfirmsThePairs(): void {
		$this->params = ['pairs' => [['registration' => 'r-anna', 'character' => 'heir'], 'not a pair']];
		$this->casting->expects(self::once())->method('confirm')
			->with('e-1', [['registration' => 'r-anna', 'character' => 'heir']])
			->willReturn(['written' => [['registration' => 'r-anna', 'character' => 'heir']], 'refused' => []]);

		self::assertSame(200, $this->controller('gm', isGm: true)->confirm('e-1')->getStatus());
	}

	public function testConfirmingWithoutPairsIsABadRequest(): void {
		$this->casting->expects(self::never())->method('confirm');

		self::assertSame(400, $this->controller('gm', isGm: true)->confirm('e-1')->getStatus());
	}
}//end class
