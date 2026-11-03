<?php

/**
 * Larpinq Registration Changes Controller Test
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
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use DateTimeImmutable;
use OCA\Larpinq\Controller\RegistrationChangesController;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * The endpoints behind cancel, add participant and transfer on the
 * registration page, as the signed-in user, with the real services.
 */
class RegistrationChangesControllerTest extends TestCase {

	private const SANNE_REG = 'e0000000-0000-4000-8000-000000000003';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld(test: $this);
		$this->world->store->objects['event'][PaymentWorld::EVENT]['cancellationPolicy'] = ['cancelBy' => '2026-11-25T00:00:00+00:00'];
		$this->world->seedRegistration(id: self::SANNE_REG, player: PaymentWorld::SANNE, status: 'accepted');
		$this->world->now = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
	}//end setUp()

	/**
	 * Sanne offers her place to Pieter, Pieter accepts; each step as the signed-in user.
	 *
	 * @return void
	 */
	public function testTheTransferRunsAsTheSignedInUser(): void {
		$this->world->signedIn = 'sanne';
		$this->assertTrue(condition: $this->controller()->changes(id: self::SANNE_REG)->getData()['canOfferTransfer']);
		$offered = $this->controller()->offerTransfer(id: self::SANNE_REG, account: 'pieter');
		$this->assertSame(expected: 200, actual: $offered->getStatus());
		$this->assertSame(expected: 'offered', actual: $offered->getData()['transferStatus']);

		$this->world->signedIn = 'anna';
		$this->assertSame(expected: 403, actual: $this->controller()->acceptTransfer(id: self::SANNE_REG)->getStatus());

		$this->world->signedIn = 'pieter';
		$this->assertTrue(condition: $this->controller()->changes(id: self::SANNE_REG)->getData()['canAcceptTransfer']);
		$taken = $this->controller()->acceptTransfer(id: self::SANNE_REG);
		$this->assertSame(expected: PaymentWorld::PIETER, actual: $taken->getData()['player']);
	}//end testTheTransferRunsAsTheSignedInUser()

	/**
	 * A refusal answers with its status and the reason; signed out is 401; unknown is 404.
	 *
	 * @return void
	 */
	public function testRefusalsCarryTheirStatusAndReason(): void {
		$this->world->signedIn = 'anna';
		$refused = $this->controller()->cancel(id: self::SANNE_REG);
		$this->assertSame(expected: 403, actual: $refused->getStatus());
		$this->assertSame(expected: 'Only the player, the person who booked or a game master can cancel this registration.', actual: $refused->getData()['error']);

		$this->assertSame(expected: 404, actual: $this->controller()->withdrawTransfer(id: 'e0000000-0000-4000-8000-00000000ffff')->getStatus());
		$this->assertSame(expected: 403, actual: $this->controller()->addParticipant(id: self::SANNE_REG, name: 'Mila')->getStatus(), message: 'Anna adds nobody to Sanne\'s booking');

		$this->world->signedIn = 'sanne';
		$this->assertSame(expected: 422, actual: $this->controller()->addParticipant(id: self::SANNE_REG)->getStatus());
		$this->assertSame(expected: 'cancelled', actual: $this->controller()->cancel(id: self::SANNE_REG)->getData()['status']);

		$this->world->signedIn = '';
		$this->assertSame(expected: 401, actual: $this->controller()->changes(id: self::SANNE_REG)->getStatus());
	}//end testRefusalsCarryTheirStatusAndReason()

	/**
	 * The controller.
	 *
	 * @return RegistrationChangesController The controller.
	 */
	private function controller(): RegistrationChangesController {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		return new RegistrationChangesController(appName: 'larpinq', request: $this->createMock(originalClassName: IRequest::class), changes: $this->world->changes(), transfers: $this->world->transfers(), userSession: $this->world->session(), l10n: $l10n);
	}//end controller()
}//end class
