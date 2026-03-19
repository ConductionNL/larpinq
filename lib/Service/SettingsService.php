<?php

/**
 * LarpingApp SettingsService.
 *
 * Service for managing LarpingApp application settings and configuration.
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use OCA\LarpingApp\AppInfo\Application;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Service for managing LarpingApp settings.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class SettingsService
{

    /**
     * Configuration keys to expose.
     *
     * @var string[]
     */
    private const CONFIG_KEYS = [
        'register',
        'character_schema',
        'player_schema',
        'ability_schema',
        'skill_schema',
        'item_schema',
        'condition_schema',
        'effect_schema',
        'event_schema',
        'setting_schema',
    ];

    /**
     * Constructor.
     *
     * @param IAppConfig          $appConfig           The app config.
     * @param SettingsLoadService $settingsLoadService The settings load service.
     * @param LoggerInterface     $logger              The logger.
     *
     * @return void
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly IAppConfig $appConfig,
        private readonly SettingsLoadService $settingsLoadService,
        private readonly LoggerInterface $logger,
    ) {

    }//end __construct()

    /**
     * Get all LarpingApp settings.
     *
     * @return array The settings as key-value pairs.
     */
    public function getSettings(): array
    {
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $config[$key] = $this->appConfig->getValueString(Application::APP_ID, $key, '');
        }

        return $config;

    }//end getSettings()

    /**
     * Update LarpingApp settings with the given data.
     *
     * @param array $data The settings data to update.
     *
     * @return array The updated settings.
     */
    public function updateSettings(array $data): array
    {
        foreach (self::CONFIG_KEYS as $key) {
            if (isset($data[$key]) === true) {
                $this->appConfig->setValueString(Application::APP_ID, $key, (string) $data[$key]);
            }
        }

        $this->logger->info('LarpingApp settings updated', ['keys' => array_keys($data)]);

        return $this->getSettings();

    }//end updateSettings()

    /**
     * Load settings by importing the register JSON via ConfigurationService.
     * Delegates to SettingsLoadService.
     *
     * @param bool $force Whether to force re-import.
     *
     * @return array The import result.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function loadSettings(bool $force=false): array
    {
        return $this->settingsLoadService->loadSettings(force: $force);

    }//end loadSettings()

    /**
     * Get a config value by key.
     *
     * @param string $key     The config key.
     * @param string $default The default value.
     *
     * @return string The config value.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for reading individual config values.
     */
    public function getConfigValue(string $key, string $default=''): string
    {
        return $this->appConfig->getValueString(Application::APP_ID, $key, $default);

    }//end getConfigValue()

    /**
     * Set a config value by key.
     *
     * @param string $key   The config key.
     * @param string $value The config value.
     *
     * @return void
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for writing individual config values.
     */
    public function setConfigValue(string $key, string $value): void
    {
        $this->appConfig->setValueString(Application::APP_ID, $key, $value);

    }//end setConfigValue()
}//end class
