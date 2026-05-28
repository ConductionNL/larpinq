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
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-27
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-28
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-29
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-30
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-31
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-32
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
 *
 * @psalm-suppress UnusedProperty Container used in getConfigurationService().
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
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly IAppConfig $appConfig,
        private readonly IAppManager $appManager,
        // @psalm-suppress UnusedProperty Used in getConfigurationService().
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
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-27
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-28
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-29
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-30
     */
    public function loadSettings(bool $force=false): array
    {
        $data = $this->fileLoader->loadConfigurationFile();
        $data = $this->fileLoader->ensureSourceType(data: $data);

        $configurationService = $this->getConfigurationService();
        $currentAppVersion    = $this->appManager->getAppVersion(Application::APP_ID);

        // @psalm-suppress MixedMethodCall ConfigurationService is from OpenRegister.
        // @var array $result
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
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-31
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-32
     */
    private function updateObjectTypeConfiguration(array $importResult): void
    {
        // @var array $schemas
        $schemas   = $importResult['schemas'] ?? [];
        $schemaMap = $this->mapBuilder->buildSchemaSlugMap(
            schemas: $schemas
        );

        // @var array $registers
        $registers = $importResult['registers'] ?? [];

        // @var string|int|null $registerId
        $registerId = $this->mapBuilder->findRegisterIdBySlug(
            registers: $registers
        );

        if ($registerId !== null) {
            $this->appConfig->setValueString(Application::APP_ID, 'register', (string) $registerId);
        }

        foreach (self::SCHEMA_SLUGS as $slug) {
            if (isset($schemaMap[$slug]) === true && $schemaMap[$slug] !== null) {
                $this->appConfig->setValueString(Application::APP_ID, "{$slug}_schema", (string) $schemaMap[$slug]);
                $this->appConfig->setValueString(Application::APP_ID, "{$slug}_source", 'openregister');
                if ($registerId !== null) {
                    $this->appConfig->setValueString(Application::APP_ID, "{$slug}_register", (string) $registerId);
                }
            }
        }

    }//end updateObjectTypeConfiguration()

    /**
     * Get the OpenRegister ConfigurationService via the container.
     *
     * Mirrors the OR-availability guard in SettingsController::getConfigurationService
     * so that a missing openregister app produces a clean RuntimeException rather
     * than an opaque container-not-found error. Closes #214.
     *
     * @return object The configuration service.
     *
     * @throws \RuntimeException If OpenRegister is not installed.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-27
     */
    private function getConfigurationService(): object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === false) {
            throw new \RuntimeException('Configuration service is not available.');
        }

        // @var object $service
        $service = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
        return $service;

    }//end getConfigurationService()
}//end class
