<?php

/**
 * Larpinq first-time setup contract (ADR-042).
 *
 * Backs the abstract CnSetupWizard. Larpinq's single REQUIRED step is that
 * its OpenRegister register and schemas are provisioned — without them the app
 * cannot read or write characters, players, items or events. The `provision`
 * run-action (re)imports the register from the bundled JSON and is idempotent.
 * Reports per-step completion, persists config, and runs the privileged
 * server-side provisioning action.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\DemoDataService;
use OCA\Larpinq\Service\SettingsService;
use OCA\Larpinq\Settings\LarpinqAdmin;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * First-time setup status + actions for the abstract setup wizard.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec exclude First-time setup wizard backend (ADR-042); no per-app openspec change yet.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 */
class SetupController extends Controller {

	/**
	 * Setup contract version; matches manifest.setup.version.
	 *
	 * @var int
	 */
	private const SETUP_VERSION = 1;
	/**
	 * App-config key recording that the optional demo-data step has been dealt with.
	 *
	 * Records a DECISION, not a state: "installed" and "declined" both set it.
	 * A step that reports itself undone until demo objects exist can never be
	 * completed by an operator who does not want them.
	 *
	 * @var string
	 */
	private const DEMO_DATA_DECIDED_KEY = 'demo_data_decided';

	/**
	 * Representative schema config key proving the schemas resolved, not just
	 * the register id. SettingsLoadService writes `<objectType>_schema` for
	 * each provisioned schema; `character` is a core Larpinq type.
	 *
	 * @var string
	 */
	private const SCHEMA_MARKER_KEY = 'character_schema';

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param IAppConfig $appConfig App-config reader/writer.
	 * @param DemoDataService $demoDataService Demo dataset import (ADR-111 rule 4).
	 * @param SettingsService $settingsService Register/schema provisioning.
	 * @param IAppManager $appManager App installed/enabled lookup.
	 * @param LoggerInterface $logger Logger.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly IAppConfig $appConfig,
		private readonly DemoDataService $demoDataService,
		private readonly SettingsService $settingsService,
		private readonly IAppManager $appManager,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);

	}//end __construct()

	/**
	 * Report per-step setup status for the wizard.
	 *
	 * `provision.done` is computed from Larpinq's ACTUAL OpenRegister state:
	 * the `register` id config is set AND a representative schema key resolves.
	 * On a fresh install both are empty, so the required `provision` step gates
	 * the app. `completed` is true once every required step is done; when so we
	 * persist `setup_completed_version` so the wizard does not re-trigger.
	 *
	 * @return DataResponse `{ version, completed, steps: { <id>: { done } } }`.
	 *
	 * @spec exclude First-time setup wizard backend (ADR-042); no per-app openspec change yet.
	 */
	#[AuthorizedAdminSetting(LarpinqAdmin::class)]
	public function status(): DataResponse {
		$provisionDone = $this->isProvisioned();

		// The only required step is `provision`; completion mirrors it.
		$completed = $provisionDone;

		if ($completed === true) {
			$this->appConfig->setValueString(
				Application::APP_ID,
				'setup_completed_version',
				(string)self::SETUP_VERSION
			);
		}

		// DEALT WITH, not "demo objects exist". An operator who declines demo
		// data has finished the step; re-offering it every visit would make
		// "no thanks" impossible to express.
		$demoDecided = ($this->appConfig->getValueString(Application::APP_ID, self::DEMO_DATA_DECIDED_KEY, '') !== '');

		return new DataResponse(
			[
				'version' => self::SETUP_VERSION,
				'completed' => $completed,
				'steps' => [
					'demo-data' => ['done' => $demoDecided],
					'welcome' => ['done' => true],
					'provision' => ['done' => $provisionDone],
					'done' => ['done' => $completed],
				],
			]
		);

	}//end status()

	/**
	 * Persist app-config values from a `choice` / `config-fields` step.
	 *
	 * Larpinq's wizard has no config-only steps today, but the endpoint is
	 * provided for parity with the abstract wizard contract so future steps can
	 * write config without a new controller.
	 *
	 * @return DataResponse `{ success }`.
	 *
	 * @spec exclude First-time setup wizard backend (ADR-042); no per-app openspec change yet.
	 */
	#[AuthorizedAdminSetting(LarpinqAdmin::class)]
	public function saveConfig(): DataResponse {
		foreach ($this->request->getParams() as $key => $value) {
			if ($key === '_route') {
				continue;
			}

			$stored = (string)json_encode($value);
			if (is_scalar($value) === true) {
				$stored = (string)$value;
			}

			$this->appConfig->setValueString(Application::APP_ID, (string)$key, $stored);
		}

		return new DataResponse(['success' => true]);
	}//end saveConfig()

	/**
	 * Run a privileged server-side setup action.
	 *
	 * @param string $actionId The action id.
	 *
	 * @return DataResponse `{ success, message }`.
	 *
	 * @spec exclude First-time setup wizard backend (ADR-042); no per-app openspec change yet.
	 */
	#[AuthorizedAdminSetting(LarpinqAdmin::class)]
	public function runAction(string $actionId): DataResponse {
		if ($actionId === 'install-demo-data') {
			return $this->installDemoData();
		}

		if ($actionId === 'skip-demo-data') {
			return $this->skipDemoData();
		}

		if ($actionId === 'provision') {
			return $this->provisionRegister();
		}

		return new DataResponse(
			['success' => false, 'message' => 'Unknown setup action: ' . $actionId],
			Http::STATUS_NOT_FOUND,
		);

	}//end runAction()

	/**
	 * Install the shipped demo dataset (ADR-111 rule 4).
	 *
	 * @return DataResponse The outcome, carrying the counts.
	 *
	 * @spec exclude Demo-data install action (ADR-111 rule 4); no per-app openspec change yet.
	 */
	private function installDemoData(): DataResponse {
		try {
			$imported = $this->demoDataService->install();
		} catch (\Throwable $e) {
			$this->logger->error('Setup install-demo-data failed: ' . $e->getMessage());
			return new DataResponse(['success' => false, 'message' => $e->getMessage()]);
		}

		// The decision is recorded only after the import actually returned.
		// Marking it first would let a failed install present as a finished step.
		$this->appConfig->setValueString(Application::APP_ID, self::DEMO_DATA_DECIDED_KEY, 'installed');

		// 🔴 THE COUNTS, ALWAYS. "Demo data installed" with no numbers cannot be
		// told apart from an import that wrote nothing.
		return new DataResponse(
			[
				'success' => true,
				'message' => sprintf(
					'Demo data installed: %d objects across %d schemas.',
					$imported['objects'],
					$imported['schemas']
				),
				'detail'  => $imported,
			]
		);
	}//end installDemoData()

	/**
	 * Record that the operator declined the demo dataset.
	 *
	 * Its own action so "no thanks" is a decision the wizard can record. Without
	 * it the only way past the step would be to install demo data, which is
	 * wrong on a production instance.
	 *
	 * @return DataResponse The outcome.
	 *
	 * @spec exclude Demo-data skip action (ADR-111 rule 4); no per-app openspec change yet.
	 */
	private function skipDemoData(): DataResponse {
		$this->appConfig->setValueString(Application::APP_ID, self::DEMO_DATA_DECIDED_KEY, 'skipped');

		return new DataResponse(
			[
				'success' => true,
				'message' => 'Demo data skipped.',
			]
		);
	}//end skipDemoData()

	/**
	 * Import the Larpinq register + schemas from the bundled JSON.
	 *
	 * Mirrors the InitializeRegister repair step that runs on install, but is
	 * invokable on demand from the wizard so an admin who only enabled
	 * OpenRegister AFTER Larpinq (when the install-time repair skipped
	 * provisioning) can complete setup without a CLI repair run. Idempotent —
	 * loadSettings resolves existing registers/schemas by slug and is a no-op
	 * when the data already exists.
	 *
	 * @return DataResponse `{ success, message }`.
	 */
	private function provisionRegister(): DataResponse {
		if ($this->appManager->isInstalled('openregister') === false) {
			return new DataResponse(
				[
					'success' => false,
					'message' => 'OpenRegister is not installed — install and enable it, then run this step.',
				],
				Http::STATUS_PRECONDITION_FAILED,
			);
		}

		try {
			$result = $this->settingsService->loadSettings();
			$registerCount = count((array)($result['registers'] ?? []));
			$schemaCount = count((array)($result['schemas'] ?? []));

			$message = sprintf(
				'Provisioned %d register(s) and %d schema(s); Larpinq is ready to use.',
				$registerCount,
				$schemaCount,
			);

			return new DataResponse(['success' => true, 'message' => $message]);
		} catch (\Throwable $e) {
			$this->logger->error('Larpinq setup provisioning failed', ['exception' => $e->getMessage()]);
			return new DataResponse(
				['success' => false, 'message' => 'Provisioning failed: ' . $e->getMessage()],
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}//end try

	}//end provisionRegister()

	/**
	 * Whether Larpinq's OpenRegister register + schemas are provisioned.
	 *
	 * Requires BOTH the `register` id config (written by SettingsLoadService
	 * after a successful import) AND a representative `<type>_schema` key so a
	 * stale register id without resolved schemas does not read as done.
	 *
	 * @return bool True when the register id and a representative schema resolve.
	 */
	private function isProvisioned(): bool {
		$registerId = $this->appConfig->getValueString(Application::APP_ID, 'register', '');
		$schemaMarker = $this->appConfig->getValueString(Application::APP_ID, self::SCHEMA_MARKER_KEY, '');

		return $registerId !== '' && $schemaMarker !== '';
	}//end isProvisioned()
}//end class
