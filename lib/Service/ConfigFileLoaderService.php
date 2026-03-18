<?php

/**
 * LarpingApp ConfigFileLoaderService.
 *
 * Service for loading and parsing the LarpingApp register configuration JSON file.
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
use OCP\App\IAppManager;
use RuntimeException;

/**
 * Service for loading and parsing configuration JSON files.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class ConfigFileLoaderService
{

    /**
     * Path to the register config file.
     *
     * @var string
     */
    private const REGISTER_FILE = '/lib/Settings/larpingapp_register.json';

    /**
     * Constructor.
     *
     * @param IAppManager $appManager The app manager.
     *
     * @return void
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly IAppManager $appManager,
    ) {

    }//end __construct()

    /**
     * Load and parse the configuration JSON file.
     *
     * @return array<string, mixed> The parsed configuration data.
     *
     * @throws RuntimeException If the file cannot be read or parsed.
     */
    public function loadConfigurationFile(): array
    {
        $appPath          = $this->appManager->getAppPath(Application::APP_ID);
        $absoluteFilePath = $appPath.self::REGISTER_FILE;

        if (file_exists($absoluteFilePath) === false) {
            throw new RuntimeException("Configuration file not found: {$absoluteFilePath}");
        }

        $jsonContent = file_get_contents($absoluteFilePath);
        if ($jsonContent === false) {
            throw new RuntimeException("Failed to read configuration file: {$absoluteFilePath}");
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE || is_array($data) === false) {
            throw new RuntimeException('Invalid JSON in configuration file: '.json_last_error_msg());
        }

        return $data;

    }//end loadConfigurationFile()

    /**
     * Ensure the x-openregister sourceType is set on configuration data.
     *
     * @param array<string, mixed> $data The configuration data.
     *
     * @return array<string, mixed> The data with sourceType ensured.
     */
    public function ensureSourceType(array $data): array
    {
        if (isset($data['x-openregister']) === false || is_array($data['x-openregister']) === false) {
            $data['x-openregister'] = [];
        }

        /** @var array<string, mixed> $openRegister */
        $openRegister = $data['x-openregister'];
        if (isset($openRegister['sourceType']) === false) {
            $openRegister['sourceType'] = 'local';
            $data['x-openregister']     = $openRegister;
        }

        return $data;

    }//end ensureSourceType()
}//end class
