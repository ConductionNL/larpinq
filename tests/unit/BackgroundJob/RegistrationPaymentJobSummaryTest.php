<?php

/**
 * The daily payment job's summary log line.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\BackgroundJob
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\BackgroundJob;

use DateTimeImmutable;
use OCA\Larpinq\BackgroundJob\RegistrationPaymentJob;
use OCA\Larpinq\Service\PaymentFollowUp;
use OCA\Larpinq\Service\TransferOffers;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * One run follows up payments and lapses transfer offers at the same moment,
 * and logs both counts in one line.
 *
 * @covers \OCA\Larpinq\BackgroundJob\RegistrationPaymentJob
 */
class RegistrationPaymentJobSummaryTest extends TestCase {

	/**
	 * Both services get the clock's moment, and the counts are logged together.
	 *
	 * @return void
	 */
	public function testARunLogsTheFollowUpAndLapseCounts(): void {
		$now = new DateTimeImmutable('2026-11-21T09:00:00+00:00');
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn($now);

		$followUp = $this->createMock(originalClassName: PaymentFollowUp::class);
		$followUp->expects($this->once())->method('daily')->with($now)
			->willReturn(['paid' => 2, 'reminded' => 3, 'expired' => 1]);
		$transfers = $this->createMock(originalClassName: TransferOffers::class);
		$transfers->expects($this->once())->method('lapse')->with($now)->willReturn(4);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('info')->with(
			'Larpinq: payments followed up: {paid} paid, {reminded} reminded, {expired} expired; {lapsed} transfer offers lapsed.',
			['paid' => 2, 'reminded' => 3, 'expired' => 1, 'lapsed' => 4]
		);

		$job = new RegistrationPaymentJob(time: $time, followUp: $followUp, transfers: $transfers, logger: $logger);
		$run = new ReflectionMethod(RegistrationPaymentJob::class, 'run');
		$run->setAccessible(true);
		$run->invoke($job, null);
	}//end testARunLogsTheFollowUpAndLapseCounts()
}//end class
