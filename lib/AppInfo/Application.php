<?php

/**
 * LarpingApp application class.
 *
 * @category  Application
 * @package   OCA\LarpingApp\AppInfo
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

declare(strict_types=1);

namespace OCA\LarpingApp\AppInfo;

use OCA\LarpingApp\Listener\DeepLinkRegistrationListener;
use OCA\LarpingApp\Service\SettingsService;
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
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function register(IRegistrationContext $context): void
    {
        // Register the deep link listener for OpenRegister unified search.
        // The event class is only available when OpenRegister is installed.
        if (class_exists('OCA\OpenRegister\Event\DeepLinkRegistrationEvent') === true) {
            $context->registerEventListener(
                'OCA\OpenRegister\Event\DeepLinkRegistrationEvent',
                DeepLinkRegistrationListener::class
            );
        }
    }//end register()

    /**
     * Boot the application and import register configuration
     *
     * @param IBootContext $context Boot context
     *
     * @return void
     */
    public function boot(IBootContext $context): void
    {
        // @psalm-suppress DeprecatedInterface IServerContainer is deprecated but still used in boot().
        $server = $context->getServerContainer();

        try {
            // @var SettingsService $settingsService
            $settingsService = $server->get(SettingsService::class);
            $settingsService->loadSettings();
        } catch (\Exception $e) {
            // OpenRegister not available or import failed — skip silently.
        }//end try

    }//end boot()
}//end class
