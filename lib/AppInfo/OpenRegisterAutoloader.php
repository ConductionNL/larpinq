<?php

/**
 * LarpingApp OpenRegister autoload prelude
 *
 * Puts OpenRegister's PSR-4 prefix on the autoloader so this app can probe for
 * `OCA\OpenRegister\…` classes from its own `Application::register()`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category AppInfo
 * @package  OCA\LarpingApp\AppInfo
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

namespace OCA\LarpingApp\AppInfo;

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
 * `larpingapp` sorts before `openregister`, so `OCA\OpenRegister\` is NOT
 * autoloadable inside `Application::register()` on a perfectly healthy instance
 * with OpenRegister enabled. Every `class_exists('OCA\OpenRegister\Event\…')`
 * probe in `register()` therefore answers FALSE — not "not loaded yet", just
 * FALSE, indistinguishable from OpenRegister being absent — and LarpingApp
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
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
final class OpenRegisterAutoloader
{
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
     * @return bool True when the prefix is registered, false when OpenRegister
     *              is absent, disabled, or otherwise unresolvable — in which
     *              case the caller's guards correctly skip the OR-dependent
     *              listeners.
     *
     * @SuppressWarnings(PHPMD.StaticAccess) OC_App is Nextcloud's legacy
     * bootstrap class. There is no OCP interface for registering another app's
     * autoloader, and this runs at the composition root where no container is
     * available to resolve an adapter from.
     *
     * @spec openspec/specs/skill-requirement-enforcement/spec.md
     */
    public static function register(): bool
    {
        try {
            $appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
            $path       = $appManager->getAppPath('openregister');
            \OC_App::registerAutoloading('openregister', $path);
            return true;
        } catch (\Throwable) {
            // OpenRegister absent, disabled, or the server container is not up
            // (unit tests). The caller's class_exists() guards then skip the
            // OR-dependent listeners. Never rethrow: an exception escaping here
            // would abort the caller's entire register(), which is the exact
            // defect this prelude exists to prevent.
            return false;
        }

    }//end register()
}//end class
