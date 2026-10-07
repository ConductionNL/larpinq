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
	 * App-config key holding the dataset the operator picked.
	 *
	 * The wizard's `choice` step writes it through `POST /api/setup/config`.
	 * Each card's Load button posts `{ dataset }` to the `load-demo-data`
	 * action, which stores the same key once the load succeeds, so both routes
	 * land in one place (`loadAction` on the step, wizard-dataset-card-load).
	 *
	 * @var string
	 */
	private const DATASET_KEY = 'demo_dataset';

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
	 * The wizard has no required step any more: provisioning moved to the
	 * admin settings page (wizard-dataset-card-load), where the `provision`
	 * action still runs. `provisioned` travels as information, not as a step,
	 * so the admin page and a support check can read it.
	 *
	 * @return DataResponse `{ version, completed, provisioned, steps: { <id>: { done } } }`.
	 *
	 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
	 */
	#[AuthorizedAdminSetting(LarpinqAdmin::class)]
	public function status(): DataResponse {
		$provisionDone = $this->isProvisioned();

		// No step is required, so setup is complete by definition. A missing
		// OpenRegister is the manifest's dependency gate, not a wizard step.
		$this->appConfig->setValueString(
			Application::APP_ID,
			'setup_completed_version',
			(string)self::SETUP_VERSION
		);

		// DEALT WITH, not "demo objects exist". An operator who declines demo
		// data has finished the step; re-offering it every visit would make
		// "no thanks" impossible to express.
		$demoDecided = ($this->appConfig->getValueString(Application::APP_ID, self::DEMO_DATA_DECIDED_KEY, '') !== '');
		$pickedDataset = $this->appConfig->getValueString(Application::APP_ID, self::DATASET_KEY, '');

		return new DataResponse(
			[
				'version' => self::SETUP_VERSION,
				'completed' => true,
				'provisioned' => $provisionDone,
				// The choice step reads its options from here: it declares
				// `optionsSource: datasets` and no options of its own, so a
				// dataset missing from this list is a dataset nobody can pick.
				'datasets' => $this->demoDataService->listChoices(),
				// Exactly the ids of `manifest.setup.steps`: a step the server
				// never reports stays open and reopens the wizard.
				'steps' => [
					'welcome' => ['done' => true],
					// A pick without a load still counts: a wizard that
					// predates `loadAction` can only record the pick.
					'demo-data' => ['done' => ($demoDecided === true || $pickedDataset !== '')],
					'done' => ['done' => true],
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
		// 🔴 THE DATASET IS VALIDATED BEFORE IT IS STORED. Everything else here
		// is written as posted, because a `config-fields` step declares its own
		// keys and this endpoint cannot know them. The dataset is different:
		// the load step reads it back and hands it to the importer, so an
		// unknown value would surface a step later as a failed import with no
		// clue why.
		$dataset = $this->request->getParam(self::DATASET_KEY);
		if ($dataset !== null) {
			$named = 'that';
			if (is_scalar($dataset) === true) {
				$named = (string)$dataset;
			}

			$known = array_column($this->demoDataService->listChoices(), 'id');
			if (in_array($named, $known, true) === false) {
				return new DataResponse(['success' => false, 'message' => 'No dataset is called "' . $named . '".']);
			}
		}

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
		// `install-demo-data` is the id the step used before it asked WHICH
		// dataset, and it still means "import the one this app ships". Kept so
		// an older manifest, a runbook or a script that posts it keeps working.
		if ($actionId === 'load-demo-data' || $actionId === 'install-demo-data') {
			return $this->loadDataset(actionId: $actionId);
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
	 * Import the dataset a card's Load button posted as `dataset`, or the
	 * stored pick when nothing is posted (ADR-111 rule 4).
	 *
	 * @param string $actionId The action that asked, which decides whether an
	 *                         unanswered choice is refused or means the shipped set.
	 *
	 * @return DataResponse The outcome, carrying the counts.
	 *
	 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
	 */
	private function loadDataset(string $actionId): DataResponse {
		$picked = $this->appConfig->getValueString(Application::APP_ID, self::DATASET_KEY, '');

		// The card's Load button names its dataset in the body. An older wizard
		// posts nothing and relies on the pick stored a step earlier. Nothing is
		// stored before the load succeeds: a failed load must leave the step
		// open for an operator who asked for data and got none.
		$posted = $this->request->getParam('dataset');
		if ($posted !== null) {
			$refusal = $this->refuseDataset(value: $posted);
			if ($refusal !== null) {
				return $refusal;
			}

			$picked = (string)$posted;
		}

		// The legacy id carries no answer, so it means the shipped dataset. A
		// caller that posts it has said which one by posting it.
		if ($actionId === 'install-demo-data' && $picked === '') {
			$picked = DemoDataService::DEMO_DATASET;
		}

		// 🔴 NO SILENT DEFAULT. Importing here because the operator clicked Run
		// one step early would plant example objects nobody asked for.
		if ($picked === '') {
			return new DataResponse(['success' => false, 'message' => 'Pick a dataset first.']);
		}

		if ($picked === DemoDataService::NONE_DATASET) {
			$this->appConfig->setValueString(Application::APP_ID, self::DATASET_KEY, DemoDataService::NONE_DATASET);
			$this->appConfig->setValueString(Application::APP_ID, self::DEMO_DATA_DECIDED_KEY, 'skipped');

			return new DataResponse(['success' => true, 'message' => 'No example data was loaded.']);
		}

		try {
			$imported = $this->demoDataService->install();
		} catch (\Throwable $e) {
			$this->logger->error('Setup install-demo-data failed: ' . $e->getMessage());
			return new DataResponse(['success' => false, 'message' => $e->getMessage()]);
		}

		// The decision is recorded only after the import actually returned.
		// Marking it first would let a failed install present as a finished step.
		// Loading IS choosing the set, so the pick is recorded too.
		$this->appConfig->setValueString(Application::APP_ID, self::DATASET_KEY, $picked);
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
	}//end loadDataset()

	/**
	 * Refuse a posted dataset id no dataset answers to.
	 *
	 * @param mixed $value The posted value.
	 *
	 * @return DataResponse|null The refusal, or null when the dataset is known.
	 *
	 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
	 */
	private function refuseDataset(mixed $value): ?DataResponse {
		$named = 'that';
		if (is_scalar($value) === true) {
			$named = (string)$value;
		}

		$known = array_column($this->demoDataService->listChoices(), 'id');
		if (is_scalar($value) === true && in_array($named, $known, true) === true) {
			return null;
		}

		return new DataResponse(
			['success' => false, 'message' => 'No dataset is called "' . $named . '".'],
			Http::STATUS_BAD_REQUEST,
		);

	}//end refuseDataset()

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
		// Skipping IS choosing "None", so both keys are written: an older
		// runbook may read either one.
		$this->appConfig->setValueString(Application::APP_ID, self::DATASET_KEY, DemoDataService::NONE_DATASET);
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
	 * invokable on demand from the admin settings page so an admin who only enabled
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
