<?php

/**
 * Larpinq Registration Payment Job Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\BackgroundJob
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

namespace OCA\Larpinq\Tests\Unit\BackgroundJob;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use DateTimeImmutable;
use OCA\Larpinq\BackgroundJob\RegistrationPaymentJob;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;

/**
 * The daily job reminds, expires and reconciles open registrations
 * (REQ-RPS-002, REQ-RPS-003, REQ-RPS-004), with a fixed clock.
 */
class RegistrationPaymentJobTest extends TestCase {

	private const ANNA_REG = 'e0000000-0000-4000-8000-000000000001';

	private const PIETER_REG = 'e0000000-0000-4000-8000-000000000004';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
		$this->world->seedRegistration(id: self::ANNA_REG, player: PaymentWorld::ANNA, status: 'pending');
		$this->world->update(id: self::ANNA_REG, data: ['status' => 'accepted']);
		$this->world->seedRegistration(id: self::PIETER_REG, player: PaymentWorld::PIETER, status: 'waitlisted', more: ['submittedAt' => '2026-10-02T10:00:00+00:00']);
	}//end setUp()

	/**
	 * The job is daily.
	 *
	 * @return void
	 */
	public function testTheJobRunsDaily(): void {
		$job = $this->job();

		$this->assertSame(86400, $job->getInterval());
	}//end testTheJobRunsDaily()

	/**
	 * Scenario "Anna forgot to pay": on 2026-11-17 the reminder moment is set
	 * once, which is what the notification rule sends on.
	 *
	 * @return void
	 */
	public function testThreeDaysBeforeThePlayerIsReminded(): void {
		$this->runOn(moment: '2026-11-16T09:00:00+00:00');
		$this->assertArrayNotHasKey('paymentReminderAt', $this->world->registration(self::ANNA_REG), 'four days before: no reminder yet');

		$this->runOn(moment: '2026-11-17T09:00:00+00:00');
		$this->assertSame('2026-11-17T09:00:00+00:00', $this->world->registration(self::ANNA_REG)['paymentReminderAt']);

		$this->runOn(moment: '2026-11-18T09:00:00+00:00');
		$this->assertSame('2026-11-17T09:00:00+00:00', $this->world->registration(self::ANNA_REG)['paymentReminderAt'], 'one reminder');
	}//end testThreeDaysBeforeThePlayerIsReminded()

	/**
	 * Scenario "The place goes to Pieter": still pending on 2026-11-21, the
	 * registration is cancelled as unpaid and Pieter moves up.
	 *
	 * @return void
	 */
	public function testADayAfterThePayByDateTheUnpaidPlaceGoesToTheWaitingList(): void {
		$this->runOn(moment: '2026-11-20T09:00:00+00:00');
		$this->assertSame('accepted', $this->world->registration(self::ANNA_REG)['status'], 'on the day itself the place stands');

		$this->runOn(moment: '2026-11-21T09:00:00+00:00');

		$anna = $this->world->registration(self::ANNA_REG);
		$this->assertSame('cancelled', $anna['status']);
		$this->assertSame('unpaid', $anna['cancelReason']);
		$this->assertSame('expired', $anna['paymentState']);
		$this->assertSame('accepted', $this->world->registration(self::PIETER_REG)['status']);
	}//end testADayAfterThePayByDateTheUnpaidPlaceGoesToTheWaitingList()

	/**
	 * A capture the listener missed is read back from the leaf, and a paid
	 * registration is never expired.
	 *
	 * @return void
	 */
	public function testAMissedCaptureIsReconciledBeforeExpiry(): void {
		$id = (string)$this->world->registration(self::ANNA_REG)['paymentRequestId'];
		$this->world->leaf->requests[$id]['state'] = 'captured';

		$this->runOn(moment: '2026-11-21T09:00:00+00:00');

		$anna = $this->world->registration(self::ANNA_REG);
		$this->assertSame('paid', $anna['paymentState']);
		$this->assertSame('accepted', $anna['status']);
	}//end testAMissedCaptureIsReconciledBeforeExpiry()

	/**
	 * Without shillinq nobody can tell whether the money came: no expiry.
	 *
	 * @return void
	 */
	public function testWithoutShillinqNothingExpires(): void {
		$this->world->shillinq = false;

		$this->runOn(moment: '2026-11-21T09:00:00+00:00');

		$this->assertSame('accepted', $this->world->registration(self::ANNA_REG)['status']);
	}//end testWithoutShillinqNothingExpires()

	/**
	 * Run the job at a moment.
	 *
	 * @param string $moment The moment.
	 *
	 * @return void
	 */
	private function runOn(string $moment): void {
		$this->world->now = new DateTimeImmutable($moment);
		$this->world->signedIn = '';
		$run = new ReflectionMethod(RegistrationPaymentJob::class, 'run');
		$run->setAccessible(true);
		$run->invoke($this->job(), null);
	}//end runOn()

	/**
	 * The job.
	 *
	 * @return RegistrationPaymentJob The job.
	 */
	private function job(): RegistrationPaymentJob {
		return new RegistrationPaymentJob($this->world->clock(), $this->world->followUp(), new NullLogger());
	}//end job()
}//end class
