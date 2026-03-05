<?php

/**
 * LarpingApp SettingsLoadService.
 *
 * Service for loading and importing LarpingApp configuration from JSON into OpenRegister.
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
use OCP\IAppConfig;
use Psr\Container\ContainerInterface;

/**
 * Service for loading and importing LarpingApp configuration.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class SettingsLoadService
{

    /**
     * Schema slugs to map.
     *
     * @var string[]
     */
    private const SCHEMA_SLUGS = [
        'character',
        'player',
        'ability',
        'skill',
        'item',
        'condition',
        'effect',
        'event',
        'setting',
    ];

    /**
     * Constructor.
     *
     * @param IAppConfig              $appConfig  The app config.
     * @param IAppManager             $appManager The app manager.
     * @param ContainerInterface      $container  The container.
     * @param SettingsMapBuilder      $mapBuilder The map builder.
     * @param ConfigFileLoaderService $fileLoader The file loader.
     *
     * @return void
     */
    public function __construct(
        private readonly IAppConfig $appConfig,
        private readonly IAppManager $appManager,
        private readonly ContainerInterface $container,
        private readonly SettingsMapBuilder $mapBuilder,
        private readonly ConfigFileLoaderService $fileLoader,
    ) {

    }//end __construct()

    /**
     * Load settings by importing the register JSON via ConfigurationService.
     *
     * @param bool $force Whether to force re-import.
     *
     * @return array The import result.
     */
    public function loadSettings(bool $force=false): array
    {
        $data = $this->fileLoader->loadConfigurationFile();
        $data = $this->fileLoader->ensureSourceType(data: $data);

        $configurationService = $this->getConfigurationService();
        $currentAppVersion    = $this->appManager->getAppVersion(Application::APP_ID);

        $result = $configurationService->importFromApp(
            appId: Application::APP_ID,
            data: $data,
            version: $currentAppVersion,
            force: $force
        );

        $this->updateObjectTypeConfiguration(importResult: $result);

        return $result;

    }//end loadSettings()

    /**
     * Update IAppConfig with imported register and schema IDs.
     *
     * @param array $importResult The import result from ConfigurationService.
     *
     * @return void
     */
    private function updateObjectTypeConfiguration(array $importResult): void
    {
        $schemaMap = $this->mapBuilder->buildSchemaSlugMap(
            schemas: ($importResult['schemas'] ?? [])
        );

        $registerId = $this->mapBuilder->findRegisterIdBySlug(
            registers: ($importResult['registers'] ?? [])
        );

        if ($registerId !== null) {
            $this->appConfig->setValueString(Application::APP_ID, 'register', (string) $registerId);
        }

        foreach (self::SCHEMA_SLUGS as $slug) {
            if (isset($schemaMap[$slug]) === true && $schemaMap[$slug] !== null) {
                $this->appConfig->setValueString(Application::APP_ID, "{$slug}_schema", (string) $schemaMap[$slug]);
                $this->appConfig->setValueString(Application::APP_ID, "{$slug}_source", 'openregister');
                $this->appConfig->setValueString(Application::APP_ID, "{$slug}_register", (string) $registerId);
            }
        }

    }//end updateObjectTypeConfiguration()

    /**
     * Get the OpenRegister ConfigurationService via the container.
     *
     * @return object The configuration service.
     */
    private function getConfigurationService(): object
    {
        return $this->container->get('OCA\OpenRegister\Service\ConfigurationService');

    }//end getConfigurationService()
}//end class
