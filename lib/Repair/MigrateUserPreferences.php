<?php

/**
 * Larpinq Migrate User Preferences Repair Step
 *
 * Repair step that carries this app's per-user preferences across the
 * `larpingapp` -> `larpinq` app-id rename.
 *
 * WHY THIS EXISTS SEPARATELY FROM MigrateAppConfigKeys. `IAppConfig` and
 * `IConfig`'s user values are different stores: the former is `oc_appconfig`,
 * the latter `oc_preferences`. Both are namespaced by app id, so both are cut
 * off by the rename, but copying one does nothing for the other.
 *
 * WHY IT MATTERS MORE THAN IT LOOKS. Every reader of these preferences carries
 * a DEFAULT, so a lost value does not error — it reverts, silently, with no log
 * line to notice. **A default-valued read turns missing data into wrong
 * behaviour rather than into an error**, which is exactly why this needs a
 * migration instead of a release note. Concretely, for this app:
 *   - `pref_walkthrough_completed_version` is what `useWalkthrough` treats as
 *     AUTHORITATIVE for "which version of the guided tour has this user already
 *     seen". Lose it and the six-step "Welcome to LARPing" first-visit tour
 *     re-opens for every existing player and game master, over an app they
 *     have been using for months. It renders as a modal with a full-viewport
 *     dim layer that intercepts pointer events, so it is not a cosmetic
 *     nuisance — it is in the way.
 *   - `pref_setup_completed_version` is the first-run setup wizard's marker.
 *     Lose it and a fully-configured instance greets its users with the
 *     provisioning wizard again.
 *   - `pref_support-dialog-seen` is the shared `CnSupportDialog` dismissal.
 *     Lose it and a dialog every user already dismissed comes back.
 * None of those throw, none of them fail a test, and none of them appear in a
 * log. They just happen, to every user at once, the first time they open the
 * renamed app.
 *
 * WHY IT ENUMERATES BY USER RATHER THAN BY VALUE. The planninq pilot walked
 * `IConfig::getUsersForUserValue(app, key, value)` for each of a boolean key's
 * two possible values. That is exhaustive only for CLOSED value sets, and this
 * app does not have one. `PreferencesController` is a GENERIC key/value
 * endpoint: it stores whatever key the frontend asks for under a `pref_`
 * prefix, with an arbitrary string value (the walkthrough marker holds a
 * version string such as `0.1.27`). No finite value list can be written down,
 * so the value-enumerating call would migrate **nothing while reporting
 * success**. Enumerating users instead and asking `IConfig::getUserKeys()`
 * what that user actually stored is exhaustive by construction, for open and
 * closed value sets alike, and cannot drift when a future release adds a
 * preference. `LarpinqMigrateUserPreferencesTest` pins this choice with an
 * assertion that `getUsersForUserValue()` is never called.
 *
 * `callForSeenUsers()` rather than `callForAllUsers()`: a stored preference is
 * written from the app's own frontend, which requires a login, so a user with
 * something in `oc_preferences` for this app has necessarily been seen. The
 * seen-user walk reads the same table and avoids a full backend enumeration
 * (LDAP included) on every install.
 *
 * SAFETY. Idempotent and non-destructive, matching MigrateAppConfigKeys:
 *   - a value is copied only when the user has nothing stored under the new
 *     app id, so a preference changed after the rename is never clobbered and
 *     a second run is a no-op;
 *   - the old `larpingapp` rows are never deleted, so a rollback still finds
 *     them;
 *   - every failure is logged and the loop continues, because one unreadable
 *     preference is not worth aborting an install over.
 *
 * EVERY READ SITS INSIDE A `try`. This step is registered under `<install>`,
 * so a throw here does not merely fail an upgrade — the app never enables and
 * every route goes with it.
 *
 * Registered under BOTH `<install>` and `<post-migration>` in
 * `appinfo/info.xml` alongside MigrateAppConfigKeys — see the ordering comment
 * there. No other repair step reads or writes user values, so this one has no
 * ordering constraint of its own beyond running before anything that might.
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
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Copy per-user preferences from the larpingapp app id to larpinq.
 *
 * @spec exclude One-off larpingapp->larpinq app-id rename plumbing: the class
 *       moves oc_preferences rows between namespaces and adds no behaviour of
 *       its own, so it has no capability spec to link back to. The preferences
 *       it preserves are specified where they are read, in
 *       openspec/specs/preferences-api/spec.md and
 *       openspec/specs/user-settings/spec.md.
 */
class MigrateUserPreferences implements IRepairStep {
	/**
	 * The preferences namespace this app used before the rename.
	 *
	 * Deliberately the OLD app id — see MigrateAppConfigKeys::OLD_APP_ID.
	 *
	 * @var string
	 */
	private const OLD_APP_ID = 'larpingapp';

	/**
	 * Number of preferences copied during this run.
	 *
	 * Held as state rather than passed around because the walk happens inside
	 * a closure handed to IUserManager::callForSeenUsers(), which returns
	 * nothing and cannot thread a running total back out.
	 *
	 * @var int
	 */
	private int $migrated = 0;

	/**
	 * Number of preferences already present under the new app id.
	 *
	 * @var int
	 */
	private int $alreadyPresent = 0;

	/**
	 * Constructor for MigrateUserPreferences.
	 *
	 * @param IConfig $config The user-value store to read and write
	 * @param IUserManager $userManager The user enumeration backend
	 * @param LoggerInterface $logger Logger for preferences that fail to copy
	 *
	 * @return void
	 */
	public function __construct(
		private IConfig $config,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Get the name of this repair step.
	 *
	 * @return string
	 *
	 * @spec exclude IRepairStep boilerplate: returns the label Nextcloud prints
	 *       while running the one-off app-id rename migration. No capability
	 *       behaviour of its own.
	 */
	public function getName(): string {
		return 'Copy Larpinq per-user preferences from the larpingapp app id';
	}//end getName()

	/**
	 * Copy every stored per-user preference from the old app id to the new one.
	 *
	 * @param IOutput $output The output interface for progress reporting
	 *
	 * @return void
	 *
	 * @spec exclude One-off larpingapp->larpinq app-id rename plumbing: it
	 *       moves oc_preferences rows between namespaces and adds no behaviour
	 *       of its own. The preferences it preserves are specified where they
	 *       are read — the generic per-user preference endpoint in
	 *       openspec/specs/preferences-api/spec.md, and the settings it backs
	 *       in openspec/specs/user-settings/spec.md.
	 */
	public function run(IOutput $output): void {
		$this->migrated = 0;
		$this->alreadyPresent = 0;

		try {
			// The callback returns null rather than void: IUserManager treats a
			// `false` return as "stop iterating", so the contract is
			// Closure(IUser): (bool|null) and null means "keep going".
			$this->userManager->callForSeenUsers(
				function (IUser $user): ?bool {
					$this->migrateUser(userId: $user->getUID());
					return null;
				}
			);
		} catch (Throwable $e) {
			$this->logger->warning(
				'Larpinq: could not enumerate users; per-user preferences were not migrated',
				['exception' => $e->getMessage()]
			);
			$output->warning(
				'MigrateUserPreferences: user enumeration failed; preferences left under the larpingapp app id.'
			);
			return;
		}//end try

		if ($this->migrated === 0 && $this->alreadyPresent === 0) {
			$output->info(
				'MigrateUserPreferences: no stored larpingapp user preferences on this install; nothing to do.'
			);
			return;
		}

		$output->info(
			'MigrateUserPreferences: migrated ' . $this->migrated . ' preference(s); '
			. $this->alreadyPresent . ' already set under larpinq.'
		);
	}//end run()

	/**
	 * Copy one user's stored preferences from the old app id to the new one.
	 *
	 * @param string $userId The Nextcloud user ID
	 *
	 * @return void
	 */
	private function migrateUser(string $userId): void {
		foreach ($this->oldKeysFor(userId: $userId) as $key) {
			try {
				$old = $this->config->getUserValue($userId, self::OLD_APP_ID, $key, '');
				if ($old === '') {
					continue;
				}

				$existing = $this->config->getUserValue($userId, Application::APP_ID, $key, '');
				if ($existing !== '') {
					$this->alreadyPresent++;
					continue;
				}

				$this->config->setUserValue($userId, Application::APP_ID, $key, $old);
				$this->migrated++;
			} catch (Throwable $e) {
				$this->logger->warning(
					'Larpinq: could not migrate one user preference; leaving it under the old app id',
					['key' => $key, 'exception' => $e->getMessage()]
				);
			}//end try
		}//end foreach
	}//end migrateUser()

	/**
	 * Every preference key this user has stored under the old app id.
	 *
	 * @param string $userId The Nextcloud user ID
	 *
	 * @return array<int, string> The stored key names, empty when unreadable
	 */
	private function oldKeysFor(string $userId): array {
		try {
			return $this->config->getUserKeys($userId, self::OLD_APP_ID);
		} catch (Throwable $e) {
			$this->logger->warning(
				'Larpinq: could not enumerate larpingapp preference keys for a user; skipping that user',
				['exception' => $e->getMessage()]
			);
			return [];
		}//end try
	}//end oldKeysFor()
}//end class
