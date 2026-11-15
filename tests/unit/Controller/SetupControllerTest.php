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
	/** @var IAppConfig The app config. */
	private IAppConfig $appConfig;
	/** @var DemoDataService The demo data. */
	private DemoDataService $demoData;
	/** @var SettingsService The settings. */
	private SettingsService $settings;
	/** @var IAppManager The app manager. */
	private IAppManager $appManager;

	/** @var array<string, string> What the controller wrote, by key. */
	private array $written = [];

	/** @var array<string, string> What the app config answers, by key. */
	private array $config = [];

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->written = [];
		$this->config = [];
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default = ''): string => ($this->config[$key] ?? $default));
		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $value): bool {
				$this->written[$key] = $value;

				return true;
			});
		$this->demoData = $this->createMock(originalClassName: DemoDataService::class);
		$this->demoData->method('listChoices')->willReturn([
			['id' => 'none', 'label' => 'None', 'description' => '', 'objectCount' => 0, 'icon' => ''],
			['id' => 'demo', 'label' => 'Example data', 'description' => '', 'objectCount' => 40, 'icon' => ''],
		]);
		$this->settings = $this->createMock(originalClassName: SettingsService::class);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
	}

	/**
	 * Build a controller whose request carries the given body params.
	 *
	 * @param array<string, mixed> $params The posted body.
	 *
	 * @return SetupController
	 */
	private function controller(array $params = []): SetupController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn($params);
		$request->method('getParam')
			->willReturnCallback(static fn (string $key, $default = null) => ($params[$key] ?? $default));

		return new SetupController(
			request: $request,
			appConfig: $this->appConfig,
			demoDataService: $this->demoData,
			settingsService: $this->settings,
			appManager: $this->appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
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

	/**
	 * Status reports every manifest step id.
	 *
	 * @return void
	 */
	public function testStatusReportsEveryManifestStepId(): void {
		// A step the server never reports stays open, and an open step
		// reopens the wizard on every page.
		$steps = $this->controller()->status()->getData()['steps'];

		$declared = array_column($this->manifest()['setup']['steps'], 'id');
		$reported = array_keys($steps);
		sort($declared);
		sort($reported);
		$this->assertSame(expected: $declared, actual: $reported);
		$this->assertFalse(condition: $steps['demo-data']['done']);
	}

	/**
	 * The dataset step loads from its cards and provisioning left the wizard.
	 *
	 * @return void
	 */
	public function testTheDatasetStepLoadsFromItsCardsAndProvisioningLeftTheWizard(): void {
		$steps = array_column($this->manifest()['setup']['steps'], null, 'id');

		$this->assertSame(expected: 'load-demo-data', actual: $steps['demo-data']['loadAction'] ?? null);
		$this->assertArrayNotHasKey(key: 'load-demo-data', array: $steps);
		$this->assertArrayNotHasKey(key: 'provision', array: $steps);
	}

	/**
	 * Setup is complete without provisioning but says whether it ran.
	 *
	 * @return void
	 */
	public function testSetupIsCompleteWithoutProvisioningButSaysWhetherItRan(): void {
		// Provisioning is an admin action now, so it no longer gates the app.
		$data = $this->controller()->status()->getData();

		$this->assertTrue(condition: $data['completed']);
		$this->assertFalse(condition: $data['provisioned']);
	}

	/**
	 * Choosing none closes the step.
	 *
	 * @return void
	 */
	public function testChoosingNoneClosesTheStep(): void {
		$this->config['demo_dataset'] = 'none';

		$steps = $this->controller()->status()->getData()['steps'];

		$this->assertTrue(condition: $steps['demo-data']['done']);
	}

	/**
	 * The card posts its dataset and the load records the choice.
	 *
	 * @return void
	 */
	public function testTheCardPostsItsDatasetAndTheLoadRecordsTheChoice(): void {
		$this->demoData->expects($this->once())->method('install')
			->willReturn(['objects' => 40, 'registers' => 1, 'schemas' => 9]);

		$data = $this->controller(params: ['dataset' => 'demo'])->runAction('load-demo-data')->getData();

		$this->assertTrue(condition: $data['success']);
		$this->assertStringContainsString(needle: '40', haystack: $data['message']);
		$this->assertSame(expected: ['demo_dataset' => 'demo', 'demo_data_decided' => 'installed'], actual: $this->written);
	}

	/**
	 * An unknown posted dataset is refused and nothing loads.
	 *
	 * @return void
	 */
	public function testAnUnknownPostedDatasetIsRefusedAndNothingLoads(): void {
		$this->config['demo_dataset'] = 'demo';
		$this->demoData->expects($this->never())->method('install');

		$response = $this->controller(params: ['dataset' => 'atlantis'])->runAction('load-demo-data');

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertFalse(condition: $response->getData()['success']);
		$this->assertSame(expected: [], actual: $this->written);
	}

	/**
	 * A failed card load stores nothing.
	 *
	 * @return void
	 */
	public function testAFailedCardLoadStoresNothing(): void {
		$this->demoData->method('install')->willThrowException(new RuntimeException('OpenRegister is not installed.'));

		$data = $this->controller(params: ['dataset' => 'demo'])->runAction('load-demo-data')->getData();

		$this->assertFalse(condition: $data['success']);
		$this->assertSame(expected: [], actual: $this->written);
	}

	/**
	 * A call without a body loads the stored pick.
	 *
	 * @return void
	 */
	public function testACallWithoutABodyLoadsTheStoredPick(): void {
		$this->config['demo_dataset'] = 'demo';
		$this->demoData->expects($this->once())->method('install')
			->willReturn(['objects' => 40, 'registers' => 1, 'schemas' => 9]);

		$data = $this->controller()->runAction('load-demo-data')->getData();

		$this->assertTrue(condition: $data['success']);
	}

	/**
	 * Loading without any pick refuses rather than guessing.
	 *
	 * @return void
	 */
	public function testLoadingWithoutAnyPickRefusesRatherThanGuessing(): void {
		$this->demoData->expects($this->never())->method('install');

		$data = $this->controller()->runAction('load-demo-data')->getData();

		$this->assertFalse(condition: $data['success']);
	}

	/**
	 * The provision action still runs for the admin page.
	 *
	 * @return void
	 */
	public function testTheProvisionActionStillRunsForTheAdminPage(): void {
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->settings->expects($this->once())->method('loadSettings')
			->willReturn(['registers' => [1], 'schemas' => [1, 2, 3]]);

		$data = $this->controller()->runAction('provision')->getData();

		$this->assertTrue(condition: $data['success']);
		$this->assertStringContainsString(needle: '3 schema', haystack: $data['message']);
	}
	/**
	 * Skipping demo data records "none" as a decided skip, so the step closes.
	 *
	 * @return void
	 */
	public function testSkippingDemoDataRecordsNone(): void {
		$this->demoData->expects($this->never())->method('install');

		$data = $this->controller()->runAction('skip-demo-data')->getData();

		$this->assertSame(expected: ['success' => true, 'message' => 'Demo data skipped.'], actual: $data);
		$this->assertSame(expected: ['demo_dataset' => 'none', 'demo_data_decided' => 'skipped'], actual: $this->written);
		$this->config = $this->written;
		$this->assertTrue(condition: $this->controller()->status()->getData()['steps']['demo-data']['done']);
	}

	/**
	 * Provisioning without OpenRegister is refused as a failed precondition, and nothing is imported.
	 *
	 * @return void
	 */
	public function testProvisioningWithoutOpenRegisterIsRefused(): void {
		$this->appManager->method('isInstalled')->with('openregister')->willReturn(false);
		$this->settings->expects($this->never())->method('loadSettings');

		$response = $this->controller()->runAction('provision');

		$this->assertSame(expected: 412, actual: $response->getStatus());
		$this->assertSame(
			expected: ['success' => false, 'message' => 'OpenRegister is not installed — install and enable it, then run this step.'],
			actual: $response->getData()
		);
	}

	/**
	 * A failed import answers 500 with its reason.
	 *
	 * @return void
	 */
	public function testAFailedProvisioningAnswersWithItsReason(): void {
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->settings->method('loadSettings')->willThrowException(new RuntimeException('schema invalid'));

		$response = $this->controller()->runAction('provision');

		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['success' => false, 'message' => 'Provisioning failed: schema invalid'], actual: $response->getData());
	}
}
