<?php

/**
 * InitializeRegister imports the configuration on install and upgrade and never fails the upgrade.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\Repair\InitializeRegister;
use OCA\Larpinq\Service\SettingsService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Larpinq\Repair\InitializeRegister
 */
class InitializeRegisterTest extends TestCase {

	/** @var SettingsService&MockObject */
	private SettingsService&MockObject $settings;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface&MockObject $logger;

	/** @var IOutput&MockObject */
	private IOutput&MockObject $output;

	/** @var list<array{string,string}> What the step told the admin. */
	private array $said = [];

	/**
	 * Build the doubles and record the output.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->settings = $this->createMock(originalClassName: SettingsService::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->output = $this->createMock(originalClassName: IOutput::class);
		$this->output->method('info')->willReturnCallback(function (string $m): void {
			$this->said[] = ['info', $m];
		});
		$this->output->method('warning')->willReturnCallback(function (string $m): void {
			$this->said[] = ['warning', $m];
		});
	}

	/**
	 * The step under test.
	 *
	 * @return InitializeRegister
	 */
	private function step(): InitializeRegister {
		return new InitializeRegister(settingsService: $this->settings, logger: $this->logger);
	}

	/**
	 * The step names what it does.
	 *
	 * @return void
	 */
	public function testTheStepHasItsName(): void {
		$this->assertSame(expected: 'Initialize Larpinq register and schemas via ConfigurationService', actual: $this->step()->getName());
	}

	/**
	 * A successful import loads the settings once and reports success, logging nothing.
	 *
	 * @return void
	 */
	public function testASuccessfulImportReportsSuccess(): void {
		$this->settings->expects($this->once())->method('loadSettings')->willReturn([]);
		$this->logger->expects($this->never())->method($this->anything());

		$this->step()->run(output: $this->output);

		$this->assertSame(
			expected: [['info', 'Initializing Larpinq configuration...'], ['info', 'Larpinq configuration imported successfully.']],
			actual: $this->said
		);
	}

	/**
	 * A RuntimeException means OpenRegister is missing: a warning, logged at warning level.
	 *
	 * @return void
	 */
	public function testAMissingOpenRegisterIsSkippedWithAWarning(): void {
		$this->settings->method('loadSettings')->willThrowException(new \RuntimeException('no openregister'));
		$this->logger->expects($this->once())->method('warning')->with(
			'Larpinq: OpenRegister not available, skipping register initialization',
			['exception' => 'no openregister']
		);
		$this->logger->expects($this->never())->method('error');

		$this->step()->run(output: $this->output);

		$this->assertSame(
			expected: [
				['info', 'Initializing Larpinq configuration...'],
				['warning', 'OpenRegister is not installed or enabled. Skipping auto-configuration.'],
			],
			actual: $this->said
		);
	}

	/**
	 * Any other failure is reported with its message and logged as an error, without throwing.
	 *
	 * @return void
	 */
	public function testAnyOtherFailureIsLoggedAsAnError(): void {
		$this->settings->method('loadSettings')->willThrowException(new \TypeError('bad schema'));
		$this->logger->expects($this->once())->method('error')->with(
			'Larpinq initialization failed',
			['exception' => 'bad schema']
		);
		$this->logger->expects($this->never())->method('warning');

		$this->step()->run(output: $this->output);

		$this->assertSame(
			expected: [['info', 'Initializing Larpinq configuration...'], ['warning', 'Could not auto-configure Larpinq: bad schema']],
			actual: $this->said
		);
	}
}
