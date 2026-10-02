<?php

/**
 * Larpinq Event Check-in Controller Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Controller\EventCheckinController;
use OCA\Larpinq\Service\CheckinCodes;
use OCA\Larpinq\Service\CodeCheckin;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Only a game master checks in by code, through a rate-limited endpoint.
 */
class EventCheckinControllerTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000001';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->store->seed('character', ['id' => 'd0000000-0000-4000-8000-000000000001', 'name' => 'Mirela the Wanderer']);
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'pending', more: ['character' => 'd0000000-0000-4000-8000-000000000001']);
	}//end setUp()

	/**
	 * A game master checks Anna in; a second scan reads "already".
	 *
	 * @return void
	 */
	public function testAGameMasterChecksInByCode(): void {
		$code = (string)$this->world->update(id: self::REG, data: ['status' => 'accepted'])['checkinCode'];

		$first = $this->controller(code: $code)->checkinByCode(id: PaymentWorld::EVENT);
		$this->assertSame(200, $first->getStatus());
		$this->assertSame(['status' => 'checked-in', 'name' => 'Anna', 'character' => 'Mirela the Wanderer'], $first->getData());

		$this->assertSame('already', $this->controller(code: $code)->checkinByCode(id: PaymentWorld::EVENT)->getData()['status']);
		$this->assertSame(404, $this->controller(code: 'ZZZZZZZZZZZZZZZZZZZZZZZZZZ')->checkinByCode(id: PaymentWorld::EVENT)->getStatus());
	}//end testAGameMasterChecksInByCode()

	/**
	 * A player gets 403 and nobody signed in gets 401, and neither checks anyone in.
	 *
	 * @return void
	 */
	public function testAPlayerCannotCheckIn(): void {
		$code = (string)$this->world->update(id: self::REG, data: ['status' => 'accepted'])['checkinCode'];

		$this->world->signedIn = 'anna';
		$this->assertSame(403, $this->controller(code: $code)->checkinByCode(id: PaymentWorld::EVENT)->getStatus());
		$this->world->signedIn = '';
		$this->assertSame(401, $this->controller(code: $code)->checkinByCode(id: PaymentWorld::EVENT)->getStatus());
		$this->assertSame([], $this->world->store->objects['attendance'] ?? []);
	}//end testAPlayerCannotCheckIn()

	/**
	 * The endpoint is open to signed-in users (the guard decides) and rate limited per user.
	 *
	 * @return void
	 */
	public function testTheEndpointIsRateLimited(): void {
		$method = new ReflectionMethod(EventCheckinController::class, 'checkinByCode');
		$this->assertCount(1, $method->getAttributes(NoAdminRequired::class));
		$limits = $method->getAttributes(UserRateLimit::class);
		$this->assertCount(1, $limits);
		$this->assertSame(['limit' => 120, 'period' => 60], $limits[0]->getArguments());
	}//end testTheEndpointIsRateLimited()

	/**
	 * The controller with a request carrying a code.
	 *
	 * @param string $code The code in the body.
	 *
	 * @return EventCheckinController The controller.
	 */
	private function controller(string $code): EventCheckinController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => ($key === 'code' ? $code : $default));
		$checkin = new CodeCheckin($this->world->fetcher(), new EventRosterService($this->world->fetcher()), new CheckinCodes());

		return new EventCheckinController('larpinq', $request, $this->world->session(), $this->world->groups(), $checkin);
	}//end controller()
}//end class
