<?php

/**
 * Tests for EventFeedbackJob.
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
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\BackgroundJob;

use DateTimeImmutable;
use OCA\Larpinq\BackgroundJob\EventFeedbackJob;
use OCA\Larpinq\Service\EventFeedbackService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The job runs the daily round at the clock's now, logs the counts, and is listed in info.xml.
 *
 * @covers \OCA\Larpinq\BackgroundJob\EventFeedbackJob
 */
class EventFeedbackJobTest extends TestCase {

	public function testARunPassesTheClockAndLogsTheCounts(): void {
		$now = new DateTimeImmutable('2025-08-18T03:00:00+00:00');
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn($now);
		$feedback = $this->createMock(EventFeedbackService::class);
		$feedback->expects($this->once())->method('daily')->with($now)->willReturn(['events' => 1, 'invited' => 2, 'reminded' => 0]);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info')->with(
			'Larpinq: post-event feedback for {events} events: {invited} invited, {reminded} reminded.',
			['events' => 1, 'invited' => 2, 'reminded' => 0]
		);

		$job = new EventFeedbackJob(time: $time, feedback: $feedback, logger: $logger);
		$run = new ReflectionMethod(EventFeedbackJob::class, 'run');
		$run->setAccessible(true);
		$run->invoke($job, null);
	}

	public function testTheJobIsListedInInfoXml(): void {
		$xml = (string)file_get_contents(dirname(__DIR__, 3) . '/appinfo/info.xml');
		self::assertStringContainsString('<job>OCA\Larpinq\BackgroundJob\EventFeedbackJob</job>', $xml);
	}
}
