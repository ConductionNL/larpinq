<?php

/**
 * Larpinq Migrate App Config Keys Repair Step
 *
 * Repair step that carries this app's stored `IAppConfig` values across the
 * `larpingapp` -> `larpinq` app-id rename.
 *
 * Nextcloud namespaces `IAppConfig` by app id at the storage layer
 * (`oc_appconfig.appid`), so renaming `<id>` does not rename the rows — it
 * makes every previously stored value unreachable, because the app now asks
 * for them under a different app id. There is no in-place app-id upgrade in
 * Nextcloud: the new id is simply a different app. This step therefore copies
 * each value from the old namespace to the new one.
 *
 * WHAT IS ACTUALLY AT STAKE HERE. This app is a THIN CLIENT on OpenRegister —
 * it declares no database tables of its own, and every character, player,
 * ability, skill, item, condition, effect, event and XP award is an
 * OpenRegister object. The only thing tying this app to those objects is the
 * bookkeeping written into `oc_appconfig`:
 *   - `register`, the numeric id of the imported OpenRegister register;
 *   - `<objectType>_schema` / `<objectType>_register` / `<objectType>_source`
 *     for each of the ten object types, written by `SettingsLoadService`;
 *   - `setup_completed_version`, the first-run setup wizard's marker.
 * Lose those and the app does not error — `SettingsService::get()` and
 * `RegisterObjectFetcher` both read with a `''` default, so the app comes up
 * looking freshly installed, pointed at nothing, with every existing object
 * still sitting in OpenRegister and invisible. The setup wizard reappears on
 * an instance that was configured months ago.
 *
 * WHY EVERY KEY RATHER THAN A FIXED LIST. `SettingsLoadService` writes one
 * `_schema` / `_register` / `_source` triple per object type, so the key set
 * grows every time a schema is added — a hardcoded list would silently fall
 * behind. `IAppConfig::getKeys()` is exhaustive by construction and cannot
 * drift.
 *
 * WHAT THIS STEP DOES NOT TOUCH. It moves nothing in OpenRegister. In
 * particular the REGISTER SLUG stays `larpingapp` (see
 * `lib/Settings/register.d/README.md`): the slug is how OpenRegister finds the
 * register holding every existing object, and renaming it would orphan them
 * all. This step copies the numeric register ID that POINTS at that register,
 * which is exactly the thing that must survive.
 *
 * SAFETY. Idempotent and non-destructive:
 *   - a key is copied only when the old value is non-empty AND the new
 *     namespace does not already hold a value, so an admin edit made after the
 *     rename is never clobbered and a second run is a no-op;
 *   - the old `larpingapp` rows are never deleted, so a rollback to the
 *     previous app id still finds its configuration intact;
 *   - values round-trip as raw strings. `IAppConfig` stores every value as a
 *     string and the typed accessors only coerce on read, so a string
 *     round-trip cannot lose or corrupt a value written by a typed setter;
 *   - every failure is logged and the loop continues.
 *
 * BOTH READS SIT INSIDE THE `try`, NOT JUST THE WRITE. Two earlier apps in
 * this programme shipped this class with `getValueString()` outside the `try`
 * that was meant to contain it, so an unreadable value propagated out of
 * `run()`. That is worse than it sounds: this step is registered under
 * `<install>`, so a repair step that throws does not merely fail an upgrade —
 * the app never enables, and every route goes with it. One unreadable key is
 * not worth an install.
 *
 * Registered under BOTH `<install>` and `<post-migration>` in
 * `appinfo/info.xml`, and BEFORE `InitializeRegister` in both — see the
 * ordering comment there.
 *
 * @category Repair
 * @package  OCA\Larpinq\Repair
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Larpinq\Repair;

use OCA\Larpinq\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Copy every stored IAppConfig value from the larpingapp namespace to larpinq.
 */
class MigrateAppConfigKeys implements IRepairStep {
	/**
	 * The app-config namespace this app used before the rename.
	 *
	 * Deliberately the OLD app id. This constant is one of the few places in
	 * the app that is supposed to still say `larpingapp`.
	 *
	 * @var string
	 */
	private const OLD_APP_ID = 'larpingapp';

	/**
	 * Config keys Nextcloud owns for every app. These MUST NOT be copied.
	 *
	 * `AppManager::enableApp()` writes `enabled` through the deprecated
	 * `IAppConfig::setValue()`, which stores type MIXED. Copying it here with
	 * `setValueString()` stores type STRING, and the next `app:enable` then
	 * fails with an `AppConfigTypeConflictException` — permanently, because the
	 * conflict is hit before the app can run anything that would repair it.
	 * `installed_version` and `types` are Nextcloud's own bookkeeping for the
	 * app and copying the old app's values would misreport the new one.
	 *
	 * @var string[]
	 */
	private const RESERVED_KEYS = [
		'enabled',
		'installed_version',
		'types',
	];

	/**
	 * Constructor for MigrateAppConfigKeys.
	 *
	 * @param IAppConfig      $appConfig The app config interface
	 * @param LoggerInterface $logger    The logger interface
	 *
	 * @return void
	 */
	public function __construct(
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Get the name of this repair step.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Copy Larpinq app configuration from the larpingapp namespace to larpinq';
	}//end getName()

	/**
	 * Run the repair step to migrate the stored app configuration.
	 *
	 * @param IOutput $output The output interface for progress reporting
	 *
	 * @return void
	 *
	 * @spec exclude One-off larpingapp->larpinq app-id rename plumbing: it
	 *       moves IAppConfig rows between namespaces and adds no behaviour of
	 *       its own. The settings it preserves are specified where they are
	 *       read — the register/schema binding in
	 *       openspec/specs/admin-settings/spec.md and the first-run setup
	 *       marker in openspec/specs/admin-settings/spec.md.
	 */
	public function run(IOutput $output): void {
		$keys = $this->oldKeys();
		if ($keys === []) {
			$output->info(
				'MigrateAppConfigKeys: no stored larpingapp configuration on this install; nothing to do.'
			);
			return;
		}

		$migrated = 0;
		$alreadyPresent = 0;
		$emptySource = 0;
		$skippedReserved = 0;

		foreach ($keys as $key) {
			if (in_array($key, self::RESERVED_KEYS, strict: true) === true) {
				$skippedReserved++;
				continue;
			}

			/* The two READS belong inside the try as much as the write does.
			   A read that throws propagates out of run() and aborts the
			   repair — and because this step also runs under <install>, an
			   app that cannot finish its repair steps does not enable at all,
			   taking every route with it. That is the opposite of what this
			   class's docblock promises ("every failure is logged and the
			   loop continues"). One unreadable key is not worth an install. */
			try {
				$old = $this->appConfig->getValueString(self::OLD_APP_ID, $key, '');
				if ($old === '') {
					$emptySource++;
					continue;
				}

				$existing = $this->appConfig->getValueString(Application::APP_ID, $key, '');
				if ($existing !== '') {
					$alreadyPresent++;
					continue;
				}

				$this->appConfig->setValueString(Application::APP_ID, $key, $old);
				$migrated++;
			} catch (Throwable $e) {
				$this->logger->warning(
					'Larpinq: could not migrate one app config key; leaving it under the old namespace',
					['key' => $key, 'exception' => $e->getMessage()]
				);
			}//end try
		}//end foreach

		$output->info(
			'MigrateAppConfigKeys: ' . $migrated . ' key(s) migrated, ' . $alreadyPresent
			. ' already present, ' . $emptySource . ' had no value to migrate, '
			. $skippedReserved . ' skipped as Nextcloud-reserved.'
		);
	}//end run()

	/**
	 * Every key currently stored under the old app-config namespace.
	 *
	 * @return array<int, string> The stored key names, empty when unreadable
	 */
	private function oldKeys(): array {
		try {
			return $this->appConfig->getKeys(self::OLD_APP_ID);
		} catch (Throwable $e) {
			$this->logger->warning(
				'Larpinq: could not enumerate larpingapp app config keys; skipping the migration',
				['exception' => $e->getMessage()]
			);
			return [];
		}//end try
	}//end oldKeys()
}//end class
