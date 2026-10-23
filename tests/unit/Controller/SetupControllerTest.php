<?php

/**
 * SetupControllerTest.
 *
 * The first-time setup contract as the wizard observes it: the status
 * document reports every manifest step, the example data cards load
 * themselves through `load-demo-data` with `{ dataset }`, and provisioning
 * is an admin action rather than a wizard step.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\SetupController;
use OCA\Larpinq\Service\DemoDataService;
use OCA\Larpinq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SetupControllerTest extends TestCase {
	private IAppConfig $appConfig;
	private DemoDataService $demoData;
	private SettingsService $settings;
	private IAppManager $appManager;

	/** @var array<string, string> What the controller wrote, by key. */
	private array $written = [];

	/** @var array<string, string> What the app config answers, by key. */
	private array $config = [];

	protected function setUp(): void {
		$this->written = [];
		$this->config = [];
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default = ''): string => ($this->config[$key] ?? $default));
		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $value): bool {
				$this->written[$key] = $value;

				return true;
			});
		$this->demoData = $this->createMock(DemoDataService::class);
		$this->demoData->method('listChoices')->willReturn([
			['id' => 'none', 'label' => 'None', 'description' => '', 'objectCount' => 0, 'icon' => ''],
			['id' => 'demo', 'label' => 'Example data', 'description' => '', 'objectCount' => 40, 'icon' => ''],
		]);
		$this->settings = $this->createMock(SettingsService::class);
		$this->appManager = $this->createMock(IAppManager::class);
	}

	/**
	 * Build a controller whose request carries the given body params.
	 *
	 * @param array<string, mixed> $params The posted body.
	 *
	 * @return SetupController
	 */
	private function controller(array $params = []): SetupController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);
		$request->method('getParam')
			->willReturnCallback(static fn (string $key, $default = null) => ($params[$key] ?? $default));

		return new SetupController(
			$request,
			$this->appConfig,
			$this->demoData,
			$this->settings,
			$this->appManager,
			$this->createMock(LoggerInterface::class)
		);
	}

	/**
	 * The manifest the wizard renders.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest(): array {
		return json_decode((string)file_get_contents(__DIR__ . '/../../../src/manifest.json'), true);
	}

	public function testStatusReportsEveryManifestStepId(): void {
		// A step the server never reports stays open, and an open step
		// reopens the wizard on every page.
		$steps = $this->controller()->status()->getData()['steps'];

		$declared = array_column($this->manifest()['setup']['steps'], 'id');
		$reported = array_keys($steps);
		sort($declared);
		sort($reported);
		$this->assertSame($declared, $reported);
		$this->assertFalse($steps['demo-data']['done']);
	}

	public function testTheDatasetStepLoadsFromItsCardsAndProvisioningLeftTheWizard(): void {
		$steps = array_column($this->manifest()['setup']['steps'], null, 'id');

		$this->assertSame('load-demo-data', $steps['demo-data']['loadAction'] ?? null);
		$this->assertArrayNotHasKey('load-demo-data', $steps);
		$this->assertArrayNotHasKey('provision', $steps);
	}

	public function testSetupIsCompleteWithoutProvisioningButSaysWhetherItRan(): void {
		// Provisioning is an admin action now, so it no longer gates the app.
		$data = $this->controller()->status()->getData();

		$this->assertTrue($data['completed']);
		$this->assertFalse($data['provisioned']);
	}

	public function testChoosingNoneClosesTheStep(): void {
		$this->config['demo_dataset'] = 'none';

		$steps = $this->controller()->status()->getData()['steps'];

		$this->assertTrue($steps['demo-data']['done']);
	}

	public function testTheCardPostsItsDatasetAndTheLoadRecordsTheChoice(): void {
		$this->demoData->expects($this->once())->method('install')
			->willReturn(['objects' => 40, 'registers' => 1, 'schemas' => 9]);

		$data = $this->controller(['dataset' => 'demo'])->runAction('load-demo-data')->getData();

		$this->assertTrue($data['success']);
		$this->assertStringContainsString('40', $data['message']);
		$this->assertSame(['demo_dataset' => 'demo', 'demo_data_decided' => 'installed'], $this->written);
	}

	public function testAnUnknownPostedDatasetIsRefusedAndNothingLoads(): void {
		$this->config['demo_dataset'] = 'demo';
		$this->demoData->expects($this->never())->method('install');

		$response = $this->controller(['dataset' => 'atlantis'])->runAction('load-demo-data');

		$this->assertSame(400, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
		$this->assertSame([], $this->written);
	}

	public function testAFailedCardLoadStoresNothing(): void {
		$this->demoData->method('install')->willThrowException(new RuntimeException('OpenRegister is not installed.'));

		$data = $this->controller(['dataset' => 'demo'])->runAction('load-demo-data')->getData();

		$this->assertFalse($data['success']);
		$this->assertSame([], $this->written);
	}

	public function testACallWithoutABodyLoadsTheStoredPick(): void {
		$this->config['demo_dataset'] = 'demo';
		$this->demoData->expects($this->once())->method('install')
			->willReturn(['objects' => 40, 'registers' => 1, 'schemas' => 9]);

		$data = $this->controller()->runAction('load-demo-data')->getData();

		$this->assertTrue($data['success']);
	}

	public function testLoadingWithoutAnyPickRefusesRatherThanGuessing(): void {
		$this->demoData->expects($this->never())->method('install');

		$data = $this->controller()->runAction('load-demo-data')->getData();

		$this->assertFalse($data['success']);
	}

	public function testTheProvisionActionStillRunsForTheAdminPage(): void {
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->settings->expects($this->once())->method('loadSettings')
			->willReturn(['registers' => [1], 'schemas' => [1, 2, 3]]);

		$data = $this->controller()->runAction('provision')->getData();

		$this->assertTrue($data['success']);
		$this->assertStringContainsString('3 schema', $data['message']);
	}
}
