<?php

/**
 * LarpingApp application class.
 *
 * @category  Application
 * @package   OCA\LarpingApp\AppInfo
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-1
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-2
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-3
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-5
 *
 * @phpversion 8.2
 */

declare(strict_types=1);

namespace OCA\LarpingApp\AppInfo;

use OCA\LarpingApp\Listener\CharacterRequirementListener;
use OCA\LarpingApp\Listener\DeepLinkRegistrationListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Main application class for LarpingApp
 *
 * @category Application
 * @package  OCA\LarpingApp\AppInfo
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-1
 */
class Application extends App implements IBootstrap
{
    /**
     * Application ID
     */
    public const APP_ID = 'larpingapp';

    /**
     * Constructor for the application
     *
     * @param array<string,mixed> $urlParams URL parameters
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated by the Nextcloud framework.
     */
    public function __construct(array $urlParams=[])
    {
        parent::__construct(appName: self::APP_ID, urlParams: $urlParams);
    }//end __construct()

    /**
     * Register application services
     *
     * @param IRegistrationContext $context Registration context.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.StaticAccess) AppInfo\OpenRegisterAutoloader is
     * the ADR-040 load-order prelude and cannot be injected: this method IS the
     * composition root, so there is no container to resolve an adapter from
     * yet, and the prelude must run before any OCA\OpenRegister\ name is
     * resolved — which is the very thing an injected dependency would do too
     * early.
     *
     * @spec openspec/specs/skill-requirement-enforcement/spec.md
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-1
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-2
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-3
     */
    public function register(IRegistrationContext $context): void
    {
        // ADR-040 load-order prelude — MUST come before every class_exists()
        // probe below.
        //
        // Nextcloud registers apps in sorted order: OC_App::getEnabledApps()
        // does sort($apps) and Coordinator::registerApps() walks THAT sorted
        // list calling OC_App::registerAutoloading($appId, $path) and then
        // $app->register() for one app at a time. `larpingapp` sorts before
        // `openregister`, so OCA\OpenRegister\ is NOT autoloadable at this
        // point on a perfectly healthy instance with OpenRegister enabled.
        //
        // Without this call every probe below answers FALSE — not "not loaded
        // yet", just FALSE, indistinguishable from OpenRegister being absent —
        // and LarpingApp registers NO listeners at all: no deep links, and no
        // server-authoritative skill-requirement / XP-budget enforcement on
        // character writes. The app stays enabled and keeps serving, and
        // nothing reports the gap.
        //
        // registerAutoloading() touches only the autoloader and is idempotent.
        // IAppManager::loadApp() would NOT be correct: it marks OpenRegister
        // loaded and calls Coordinator::bootApp(), booting it before its own
        // register() has run. The prelude swallows every Throwable rather than
        // letting one escape, so when OpenRegister genuinely is absent the
        // guards below still do their job.
        OpenRegisterAutoloader::register();

        // Register the deep link listener for OpenRegister unified search.
        // The event class is only available when OpenRegister is installed.
        if (class_exists('OCA\OpenRegister\Event\DeepLinkRegistrationEvent') === true) {
            $context->registerEventListener(
                'OCA\OpenRegister\Event\DeepLinkRegistrationEvent',
                DeepLinkRegistrationListener::class
            );
        }

        // Server-authoritative skill-requirement / XP-budget enforcement on
        // character writes. The OR pre-write event classes only exist on
        // newer OpenRegister releases; guard so older deployments degrade to
        // data-only instead of fataling at boot (skill-requirement-enforcement).
        if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === true) {
            $context->registerEventListener(
                'OCA\OpenRegister\Event\ObjectCreatingEvent',
                CharacterRequirementListener::class
            );
        }

        if (class_exists('OCA\OpenRegister\Event\ObjectUpdatingEvent') === true) {
            $context->registerEventListener(
                'OCA\OpenRegister\Event\ObjectUpdatingEvent',
                CharacterRequirementListener::class
            );
        }
    }//end register()

    /**
     * Boot the application.
     *
     * Register/schema initialization has been moved to InitializeRegister repair step
     * (lib/Repair/InitializeRegister.php), which runs once on install and upgrade instead
     * of on every HTTP request. Closes the per-request overhead regression.
     *
     * @param IBootContext $context Boot context
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) $context is mandated by
     * OCP\AppFramework\Bootstrap\IBootstrap::boot(IBootContext $context) and
     * cannot be dropped from the signature. This app does no boot-time work —
     * register/schema initialisation moved to the InitializeRegister repair
     * step — so the parameter is genuinely unused. External constraint.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-5
     */
    public function boot(IBootContext $context): void
    {
        // Register/schema initialization is handled by the repair step.
        // See lib/Repair/InitializeRegister.php.
    }//end boot()
}//end class
