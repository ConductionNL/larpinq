<?php

/**
 * Larpinq Backfill Check-in Codes Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
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

namespace OCA\Larpinq\Tests\Unit\Repair;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Repair\BackfillCheckinCodes;
use OCA\Larpinq\Service\CheckinCodes;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Registrations accepted before this change get a code from the repair step,
 * through the real registration listener; others are left alone.
 */
class BackfillCheckinCodesTest extends TestCase {

	/**
	 * Accepted registrations without a code get one; a kept code and a pending registration are untouched; a second run changes nothing.
	 *
	 * @return void
	 */
	public function testAcceptedRegistrationsGetACode(): void {
		$world = new PaymentWorld($this);
		$world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000001', player: PaymentWorld::ANNA, status: 'accepted');
		$world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000002', player: PaymentWorld::SANNE, status: 'accepted', more: ['checkinCode' => 'KEEPKEEPKEEPKEEPKEEPKEEPKE']);
		$world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000003', player: PaymentWorld::PIETER, status: 'pending');

		$step = new BackfillCheckinCodes($world->fetcher(), new CheckinCodes(), new NullLogger());
		$this->assertNotSame('', $step->getName());
		$step->run($this->createMock(IOutput::class));

		$anna = (string)($world->registration(id: 'e0000000-0000-4000-8000-000000000001')['checkinCode'] ?? '');
		$this->assertMatchesRegularExpression('/^[A-Z2-7]{26}$/', $anna);
		$this->assertSame('KEEPKEEPKEEPKEEPKEEPKEEPKE', $world->registration(id: 'e0000000-0000-4000-8000-000000000002')['checkinCode']);
		$this->assertArrayNotHasKey('checkinCode', $world->registration(id: 'e0000000-0000-4000-8000-000000000003'));

		$step->run($this->createMock(IOutput::class));
		$this->assertSame($anna, $world->registration(id: 'e0000000-0000-4000-8000-000000000001')['checkinCode']);
	}//end testAcceptedRegistrationsGetACode()
}//end class
