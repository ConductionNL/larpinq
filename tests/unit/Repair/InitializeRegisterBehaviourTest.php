<?php

/**
 * Unit tests for the InitializeRegister repair step.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\Repair\InitializeRegister;
use OCA\Larpinq\Service\SettingsService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\Larpinq\Repair\InitializeRegister
 */
class InitializeRegisterBehaviourTest extends TestCase {

	/**
	 * Run the step over a settings service that does $load.
	 *
	 * @param callable             $load   What loadSettings does.
	 * @param LoggerInterface|null $logger The logger.
	 *
	 * @return array{info: array<int, string>, warning: array<int, string>}
	 */
	private function runStep(callable $load, ?LoggerInterface $logger = null): array {
		$lines    = ['info' => [], 'warning' => []];
		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->expects($this->once())->method('loadSettings')->willReturnCallback($load);
		$output = $this->createMock(originalClassName: IOutput::class);
		$output->method('info')->willReturnCallback(static function (string $message) use (&$lines): void {
			$lines['info'][] = $message;
		});
		$output->method('warning')->willReturnCallback(static function (string $message) use (&$lines): void {
			$lines['warning'][] = $message;
		});

		$step = new InitializeRegister(
			settingsService: $settings,
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
		$this->assertSame(expected: 'Initialize Larpinq register and schemas via ConfigurationService', actual: $step->getName());
		$step->run(output: $output);

		return $lines;
	}//end runStep()

	/**
	 * A successful import says so, and warns about nothing.
	 *
	 * @return void
	 */
	public function testASuccessfulImportIsReported(): void {
		$lines = $this->runStep(load: static fn (): array => []);

		$this->assertSame(
			expected: ['Initializing Larpinq configuration...', 'Larpinq configuration imported successfully.'],
			actual: $lines['info']
		);
		$this->assertSame(expected: [], actual: $lines['warning']);
	}//end testASuccessfulImportIsReported()

	/**
	 * Without OpenRegister the step is skipped with a warning, not failed.
	 *
	 * @return void
	 */
	public function testWithoutOpenRegisterTheStepIsSkipped(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'Larpinq: OpenRegister not available, skipping register initialization',
			['exception' => 'Configuration service is not available.']
		);
		$logger->expects($this->never())->method('error');

		$lines = $this->runStep(
			load: static function (): array {
				throw new RuntimeException('Configuration service is not available.');
			},
			logger: $logger
		);

		$this->assertSame(expected: ['Initializing Larpinq configuration...'], actual: $lines['info']);
		$this->assertSame(expected: ['OpenRegister is not installed or enabled. Skipping auto-configuration.'], actual: $lines['warning']);
	}//end testWithoutOpenRegisterTheStepIsSkipped()

	/**
	 * Any other failure is reported with its message and logged as an error, without stopping the upgrade.
	 *
	 * @return void
	 */
	public function testAnyOtherFailureIsReportedAndLogged(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			'Larpinq initialization failed',
			['exception' => 'schema invalid']
		);

		$lines = $this->runStep(
			load: static function (): array {
				throw new \InvalidArgumentException('schema invalid');
			},
			logger: $logger
		);

		$this->assertSame(expected: ['Could not auto-configure Larpinq: schema invalid'], actual: $lines['warning']);
	}//end testAnyOtherFailureIsReportedAndLogged()
}//end class
