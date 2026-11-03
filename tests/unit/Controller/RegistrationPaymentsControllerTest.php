<?php

/**
 * Larpinq Registration Payments Controller Test
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
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Controller\RegistrationPaymentsController;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * The game masters' "Request payment" action and who sees it.
 */
class RegistrationPaymentsControllerTest extends TestCase {

	private const ANNA_REG = 'e0000000-0000-4000-8000-000000000001';

	private PaymentWorld $world;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->world = new PaymentWorld(test: $this);
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'pending');
		$this->world->signedIn = 'anna';
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);
	}//end setUp()

	/**
	 * Game masters see the action, players do not.
	 *
	 * @return void
	 */
	public function testOnlyGameMastersSeeTheAction(): void {
		$this->world->signedIn = 'gm';
		$this->assertSame(expected: ['allowed' => true], actual: $this->controller()->access()->getData());

		$this->world->signedIn = 'anna';
		$this->assertSame(expected: ['allowed' => false], actual: $this->controller()->access()->getData());
	}//end testOnlyGameMastersSeeTheAction()

	/**
	 * A game master's request opens the payment.
	 *
	 * @return void
	 */
	public function testAGameMasterRequestsThePayment(): void {
		$this->world->signedIn = 'gm';

		$response = $this->controller()->request(id: self::ANNA_REG);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'open', actual: $response->getData()['paymentState']);
	}//end testAGameMasterRequestsThePayment()

	/**
	 * A player's request is refused with the reason.
	 *
	 * @return void
	 */
	public function testAPlayersRequestIsRefused(): void {
		$response = $this->controller()->request(id: self::ANNA_REG);

		$this->assertSame(expected: 403, actual: $response->getStatus());
		$this->assertSame(expected: [], actual: $this->world->leaf->creates);
	}//end testAPlayersRequestIsRefused()

	/**
	 * Signed out: 401.
	 *
	 * @return void
	 */
	public function testSignedOutIsRefused(): void {
		$this->world->signedIn = '';

		$this->assertSame(expected: 401, actual: $this->controller()->request(id: self::ANNA_REG)->getStatus());
	}//end testSignedOutIsRefused()

	/**
	 * The controller.
	 *
	 * @return RegistrationPaymentsController The controller.
	 */
	private function controller(): RegistrationPaymentsController {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		return new RegistrationPaymentsController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			payments: $this->world->payments(),
			userSession: $this->world->session(),
			l10n: $l10n
		);
	}//end controller()
}//end class
