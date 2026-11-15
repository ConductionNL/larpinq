<?php

/**
 * InitializeRegisterTest.
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
use RuntimeException;
use TypeError;

/**
 * The install/upgrade step imports the register and never aborts the upgrade.
 *
 * @covers \OCA\Larpinq\Repair\InitializeRegister
 */
class InitializeRegisterTest extends TestCase {

	/**
	 * Settings service mock.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService $settingsService;

	/**
	 * Logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface $logger;

	/**
	 * Output mock.
	 *
	 * @var IOutput&MockObject
	 */
	private IOutput $output;

	/**
	 * Build the mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->settingsService = $this->createMock(originalClassName: SettingsService::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->output = $this->createMock(originalClassName: IOutput::class);
	}//end setUp()

	/**
	 * The step under test.
	 *
	 * @return InitializeRegister The step.
	 */
	private function step(): InitializeRegister {
		return new InitializeRegister(settingsService: $this->settingsService, logger: $this->logger);
	}//end step()

	/**
	 * The step names what it does.
	 *
	 * @return void
	 */
	public function testName(): void {
		$this->assertSame(
			expected: 'Initialize Larpinq register and schemas via ConfigurationService',
			actual: $this->step()->getName()
		);
	}//end testName()

	/**
	 * A successful import reports start and success and logs nothing.
	 *
	 * @return void
	 */
	public function testSuccessfulImportReportsSuccess(): void {
		$this->settingsService->expects($this->once())->method('loadSettings')->willReturn([]);
		$messages = [];
		$this->output->method('info')->willReturnCallback(
			static function (string $message) use (&$messages): void {
				$messages[] = $message;
			}
		);
		$this->output->expects($this->never())->method('warning');
		$this->logger->expects($this->never())->method('warning');
		$this->logger->expects($this->never())->method('error');

		$this->step()->run(output: $this->output);

		$this->assertSame(
			expected: ['Initializing Larpinq configuration...', 'Larpinq configuration imported successfully.'],
			actual: $messages
		);
	}//end testSuccessfulImportReportsSuccess()

	/**
	 * Without OpenRegister the step skips with a warning instead of failing.
	 *
	 * @return void
	 */
	public function testMissingOpenRegisterIsSkippedWithAWarning(): void {
		$this->settingsService->method('loadSettings')->willThrowException(new RuntimeException('OpenRegister missing'));
		$this->output->expects($this->once())->method('info')->with('Initializing Larpinq configuration...');
		$this->output->expects($this->once())->method('warning')
			->with('OpenRegister is not installed or enabled. Skipping auto-configuration.');
		$this->logger->expects($this->once())->method('warning')->with(
			'Larpinq: OpenRegister not available, skipping register initialization',
			['exception' => 'OpenRegister missing']
		);
		$this->logger->expects($this->never())->method('error');

		$this->step()->run(output: $this->output);
	}//end testMissingOpenRegisterIsSkippedWithAWarning()

	/**
	 * Any other failure is reported and logged as an error, and never escapes.
	 *
	 * @return void
	 */
	public function testOtherFailuresAreLoggedAndSwallowed(): void {
		$this->settingsService->method('loadSettings')->willThrowException(new TypeError('bad config'));
		$this->output->expects($this->once())->method('warning')->with('Could not auto-configure Larpinq: bad config');
		$this->logger->expects($this->never())->method('warning');
		$this->logger->expects($this->once())->method('error')->with(
			'Larpinq initialization failed',
			['exception' => 'bad config']
		);

		$this->step()->run(output: $this->output);
	}//end testOtherFailuresAreLoggedAndSwallowed()
}//end class
