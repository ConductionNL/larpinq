<?php

/**
 * Larpinq AppHost engine registrar.
 *
 * Owns the single call into OpenRegister's published AppHost entry point
 * (ADR-040), and the statement of exactly how much of the engine Larpinq wants.
 *
 * Split out of Application::register() because that method reached 113 lines and
 * tripped phpmd's ExcessiveMethodLength, and because the scoping decisions below
 * need somewhere to be explained and somewhere to be TESTED. dossiq made the same
 * split for the same reason (lib/AppInfo/Registrar/AppHostRegistrar.php).
 *
 * @category AppInfo
 * @package  OCA\Larpinq\AppInfo\Registrar
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>.
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\AppInfo\Registrar;

use OCA\Larpinq\AppInfo\Application;
use OCA\OpenRegister\AppHost\Bootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Registers the OpenRegister AppHost engine for Larpinq.
 *
 * @psalm-suppress UnusedClass
 *
 * @spec openspec/specs/apphost-autoload-prelude/spec.md
 */
class AppHostRegistrar {

	/**
	 * FQCN of the AppHost entry point.
	 *
	 * Referenced as a string, never imported: the class only exists when
	 * openregister is installed.
	 *
	 * @var string
	 */
	public const BOOTSTRAP = 'OCA\\OpenRegister\\AppHost\\Bootstrap';

	/**
	 * The options Larpinq passes to Bootstrap::register().
	 *
	 * Exposed as a constant so the scoping decisions below are assertable by a
	 * test rather than only readable in a comment. Both entries are load-bearing:
	 *
	 * `serviceNamespace` points AppHost's three service ids at a namespace
	 * Larpinq does not use. `registerServices()` claims
	 * `<serviceNs>\SettingsService` UNCONDITIONALLY — unlike the controller
	 * aliases there is no "unless the leaf defines it" guard — so with the
	 * default it replaces Larpinq's own SettingsService with the AppHost generic,
	 * and `Repair\InitializeRegister::__construct()`, which type-hints the
	 * Larpinq class, dies with a TypeError at app-enable time. The generic health
	 * and metrics controllers do not read those ids: their factories take
	 * IRequest and OpenRegister's own observability collaborators, nothing from
	 * the leaf.
	 *
	 * `deepLinks` is false because Larpinq registers its own
	 * DeepLinkRegistrationListener, and AppHost binds the same event under the
	 * same class name.
	 *
	 * Nothing else needs narrowing: `aliasControllerUnlessLeafDefinesIt` skips
	 * any controller this app ships, and Larpinq ships Dashboard, Preferences and
	 * Settings — so Health and Metrics are the only aliases that take effect,
	 * which is the entire point of making the call.
	 *
	 * @var array<string, mixed>
	 */
	public const OPTIONS = [
		'namespace' => 'OCA\\Larpinq',
		'serviceNamespace' => 'OCA\\Larpinq\\AppHost\\Service',
		'deepLinks' => false,
	];

	/**
	 * Register the OpenRegister AppHost engine (ADR-040).
	 *
	 * `appinfo/routes.php` is built through `AppHost\Routes::standard()`, which
	 * declares `health#index` and `metrics#index`. Those resolve to
	 * `OCA\Larpinq\Controller\HealthController` / `MetricsController`, classes
	 * this app does not ship on purpose. This call is what binds them, aliasing
	 * OpenRegister's generic controllers under those names. Without it the routes
	 * are declared and nothing answers them, so every request to `/api/health` or
	 * `/api/metrics` is a dispatch-time 500.
	 *
	 * ⚠️ The `class_exists()` guard is required. This runs inside
	 * `Application::register()`, which Nextcloud executes on EVERY request, so an
	 * unguarded static call to a class in another app would fatal the whole
	 * instance-wide request, not merely Larpinq's AppHost features.
	 *
	 * StaticAccess is suppressed rather than decomposed: `Bootstrap::register()`
	 * IS OpenRegister's published entry point. It is a stateless registration
	 * façade with no instance to inject, so wrapping it in a local collaborator
	 * would have to make the very same static call.
	 *
	 * ⚠️ The two steps are protected methods rather than inline code, and that is
	 * a seam with a reason: without it only the openregister-ABSENT branch is
	 * reachable from a unit test, since the bare unit job has no openregister to
	 * load. The two branches that actually run in production — the engine wiring,
	 * and the swallow when it is present but unloadable — would be permanently
	 * uncovered, which is exactly the shape of untested code a silent failure
	 * hides in. Only the test overrides them.
	 *
	 * ⚠️ The call below is written as a LITERAL `Bootstrap::register(...)` on
	 * purpose. hydra-gate-14 exempts an app from "controller class not found" on
	 * the AppHost routes by looking for `Bootstrap::register(` and `AppHost\Bootstrap`
	 * in the non-comment code of one file under lib/. Calling it through a
	 * variable (`$bootstrap::register(...)`) is identical to PHP and INVISIBLE to
	 * that grep, so gate-14 goes back to reporting two findings for routes that
	 * are correctly bound. Measured on larpinq#665: PASS before this method was
	 * extracted, FAIL after, with no behavioural change in between.
	 *
	 * @param IRegistrationContext $context The leaf app's registration context.
	 *
	 * @return bool True when the engine was registered, false when openregister
	 *              is absent or unloadable.
	 *
	 * @spec openspec/specs/apphost-autoload-prelude/spec.md
	 */
	public function register(IRegistrationContext $context): bool {
		if ($this->appHostIsLoadable() === false) {
			return false;
		}

		try {
			$this->callAppHost(context: $context);
			return true;
		} catch (\Throwable) {
			// AppHost present but unloadable: skip the generic plumbing.
			// Larpinq's own listeners and services MUST still register. No
			// logger is resolvable this early, so the skip is silent, and
			// /api/health reports the degraded AppHost state instead.
			return false;
		}//end try
	}//end register()

	/**
	 * Whether OpenRegister's AppHost entry point can be loaded.
	 *
	 * Overridden by tests to exercise both sides without an openregister install.
	 *
	 * @return bool True when the class is loadable.
	 */
	protected function appHostIsLoadable(): bool {
		return class_exists(self::BOOTSTRAP);
	}//end appHostIsLoadable()

	/**
	 * Hand Larpinq's scoping options to the AppHost engine.
	 *
	 * Overridden by tests. Kept as its own method so the production call site is
	 * one literal statement, for the reason given on register() above.
	 *
	 * @param IRegistrationContext $context The leaf app's registration context.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 */
	protected function callAppHost(IRegistrationContext $context): void {
		Bootstrap::register($context, Application::APP_ID, self::OPTIONS);
	}//end callAppHost()
}//end class
