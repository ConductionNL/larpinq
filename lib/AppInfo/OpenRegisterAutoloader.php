<?php

/**
 * Larpinq OpenRegister autoload prelude
 *
 * Puts OpenRegister's PSR-4 prefix on the autoloader so this app can probe for
 * `OCA\OpenRegister\…` classes from its own `Application::register()`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category AppInfo
 * @package  OCA\Larpinq\AppInfo
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

namespace OCA\Larpinq\AppInfo;

/**
 * Registers OpenRegister's autoload prefix before any OpenRegister class is probed.
 *
 * ## Why this is needed (ADR-040)
 *
 * `OC_App::getEnabledApps()` does `sort($apps)`, and
 * `Coordinator::registerApps()` walks THAT sorted list calling
 * `OC_App::registerAutoloading($appId, $path)` and then `$app->register()` for
 * one app at a time. So every app's `register()` runs BEFORE the PSR-4 prefix
 * of every alphabetically-LATER app exists.
 *
 * `larpinq` sorts before `openregister`, so `OCA\OpenRegister\` is NOT
 * autoloadable inside `Application::register()` on a perfectly healthy instance
 * with OpenRegister enabled. Every `class_exists('OCA\OpenRegister\Event\…')`
 * probe in `register()` therefore answers FALSE — not "not loaded yet", just
 * FALSE, indistinguishable from OpenRegister being absent — and Larpinq
 * registers NO event listeners at all:
 *
 *   - the `DeepLinkRegistrationEvent` listener (unified-search deep links), and
 *   - the `ObjectCreatingEvent` / `ObjectUpdatingEvent` listeners that carry the
 *     SERVER-AUTHORITATIVE skill-requirement and XP-budget enforcement on
 *     character writes.
 *
 * The second one matters beyond features: the enforcement is server-side
 * precisely because the client cannot be trusted, and it was silently not
 * running. Nothing in the UI reported it; the app stayed enabled and kept
 * serving.
 *
 * The measured evidence for this load order comes from the sibling app
 * `openbuild` (also sorting before `openregister`), whose CI logged
 * `OpenRegister AppHost\Bootstrap is not autoloadable` on every occ call while
 * OpenRegister was installed and enabled the whole time.
 *
 * Lives in its own class rather than inline in `Application::register()` for
 * one reason: `Application` cannot be constructed without a Nextcloud DI
 * container, so an inline prelude is unreachable from a unit test. Here the
 * degraded-path contract — "this NEVER throws, whatever the instance looks
 * like" — is directly assertable, and it is asserted.
 *
 * @spec openspec/specs/apphost-autoload-prelude/spec.md
 */
final class OpenRegisterAutoloader {
	/**
	 * Register OpenRegister's PSR-4 prefix on the composer autoloader.
	 *
	 * MUST be called before any `OCA\OpenRegister\…` reference in
	 * `Application::register()`, including a `class_exists()` probe — the probe
	 * answers FALSE, not "not yet loaded", and a FALSE is indistinguishable
	 * from OpenRegister being absent.
	 *
	 * `OC_App::registerAutoloading()` touches only the autoloader and is
	 * idempotent: it early-returns on an `$alreadyRegistered` key, so calling
	 * this more than once is free.
	 *
	 * Deliberately NOT `IAppManager::loadApp('openregister')`: that marks
	 * OpenRegister loaded and calls `Coordinator::bootApp()`, booting it before
	 * its own `register()` has run.
	 *
	 * @param string|null $appId App id to register the autoloader for.
	 *                           Production callers pass nothing and get
	 *                           'openregister'. It exists so the degraded
	 *                           path below — the branch that must NEVER
	 *                           rethrow — is reachable from a test with an id
	 *                           that cannot resolve; without it that branch
	 *                           is dead on any instance where OpenRegister IS
	 *                           installed, which is every instance this app
	 *                           is tested on.
	 *
	 * @return void This never reports success or failure. The caller's own
	 *              `class_exists()` guard is the authoritative signal; a
	 *              return value here would only duplicate it, and would add a
	 *              `return true`/`return false` pair of which exactly one is
	 *              dead in any given run.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) OC_App is Nextcloud's legacy
	 * bootstrap class. There is no OCP interface for registering another app's
	 * autoloader, and this runs at the composition root where no container is
	 * available to resolve an adapter from.
	 *
	 * @spec openspec/specs/apphost-autoload-prelude/spec.md
	 */
	public static function register(?string $appId = null): void {
		try {
			// The app id is written as a literal at the call site rather than
			// defaulted in the signature, so it is visible where it is used —
			// to a reader, and to hydra gate-64, which reads
			// registerAutoloading()'s arguments. No return value: the caller's
			// class_exists() guard is the authoritative signal.
			$path = \OCP\Server::get(\OCP\App\IAppManager::class)->getAppPath($appId ?? 'openregister');
			\OC_App::registerAutoloading($appId ?? 'openregister', $path);
		} catch (\Throwable) {
			// OpenRegister absent, disabled, or the server container is not up
			// (unit tests). The caller's class_exists() guards then skip the
			// OpenRegister-dependent listeners exactly as they did before.
			// Never rethrow: an exception escaping here would abort the
			// caller's entire register(), which is the exact defect this
			// prelude exists to prevent.
		}

	}//end register()
}//end class
