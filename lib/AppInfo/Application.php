<?php

declare(strict_types=1);

/**
 * LarpingApp application class
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

namespace OCA\LarpingApp\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;

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
     */
    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    /**
     * Register application services
     *
     * @param IRegistrationContext $context Registration context
     * 
     * @return void
     */
    public function register(IRegistrationContext $context): void
    {
        // Register services here
    }

    /**
     * Boot the application
     *
     * @param IBootContext $context Boot context
     * 
     * @return void
     */
    public function boot(IBootContext $context): void
    {
        // Boot services here
    }
}
