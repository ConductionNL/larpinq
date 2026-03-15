<?php

/**
 * LarpingApp Settings Service
 *
 * Service for managing LarpingApp application configuration and settings.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use OCA\LarpingApp\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Service for managing LarpingApp application configuration and settings.
 */
class SettingsService
{
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
     * @param IAppConfig $appConfig The app config service.
     *
     * @return void
     */
    public function __construct(
        private readonly IAppConfig $appConfig,
    ) {
    }//end __construct()

    /**
     * Get all current settings.
     *
     * @return array<string, string> The settings as key-value pairs.
     */
    public function getSettings(): array
    {
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $value = $this->appConfig->getValueString(Application::APP_ID, $key, '');
            if ($value !== '') {
                $config[$key] = $value;
            }
        }

        return $config;
    }//end getSettings()

    /**
     * Update settings from request data.
     *
     * @param array $data The settings data to update.
     *
     * @return array<string, string> The updated settings.
     */
    public function updateSettings(array $data): array
    {
        foreach (self::CONFIG_KEYS as $key) {
            if (isset($data[$key])) {
                $this->appConfig->setValueString(Application::APP_ID, $key, (string) $data[$key]);
            }
        }

        return $this->getSettings();
    }//end updateSettings()

    /**
     * Load settings from JSON configuration file.
     *
     * @param bool $force Force reload even if settings exist.
     *
     * @return array The loaded configuration result.
     */
    public function loadSettings(bool $force = false): array
    {
        // Check if already configured.
        $existingRegister = $this->appConfig->getValueString(Application::APP_ID, 'register', '');
        if ($existingRegister !== '' && !$force) {
            return ['registers' => [], 'schemas' => []];
        }

        return ['registers' => [], 'schemas' => []];
    }//end loadSettings()
}//end class
