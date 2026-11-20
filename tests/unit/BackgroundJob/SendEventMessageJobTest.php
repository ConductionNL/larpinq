<?php

/**
 * Larpinq Send Event Message Job Test
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
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\BackgroundJob;

use OCA\Larpinq\BackgroundJob\SendEventMessageJob;
use OCA\Larpinq\Service\EventMessageService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * A run hands its argument to the service and logs what the batch did.
 */
#[CoversClass(SendEventMessageJob::class)]
class SendEventMessageJobTest extends TestCase {

	/**
	 * Run the job's protected run() with an argument.
	 *
	 * @param SendEventMessageJob $job The job.
	 * @param mixed $argument The argument.
	 *
	 * @return void
	 */
	private function runJob(SendEventMessageJob $job, mixed $argument): void {
		$run = new ReflectionMethod($job, 'run');
		$run->invoke($job, $argument);
	}//end runJob()

	/**
	 * The argument goes to sendBatch() and the counts to the log.
	 *
	 * @return void
	 */
	public function testARunSendsOneBatch(): void {
		$argument = ['messageId' => 'm', 'eventId' => 'e', 'offset' => 50, 'sent' => 50, 'failed' => 0];
		$messages = $this->createMock(EventMessageService::class);
		$messages->expects($this->once())->method('sendBatch')->with($argument)->willReturn(['sent' => 10, 'failed' => 1, 'done' => true]);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info')->with($this->stringContains('event message batch'), ['sent' => 10, 'failed' => 1]);

		$this->runJob(new SendEventMessageJob(time: $this->createMock(ITimeFactory::class), messages: $messages, logger: $logger), $argument);
	}//end testARunSendsOneBatch()

	/**
	 * An argument that is not an array is handed on as an empty one.
	 *
	 * @return void
	 */
	public function testANonArrayArgumentIsHandedOnEmpty(): void {
		$messages = $this->createMock(EventMessageService::class);
		$messages->expects($this->once())->method('sendBatch')->with([])->willReturn(['sent' => 0, 'failed' => 0, 'done' => true]);

		$this->runJob(new SendEventMessageJob(time: $this->createMock(ITimeFactory::class), messages: $messages, logger: $this->createMock(LoggerInterface::class)), 'junk');
	}//end testANonArrayArgumentIsHandedOnEmpty()
}//end class
