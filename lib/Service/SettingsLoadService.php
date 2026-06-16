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
use RuntimeException;

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
     * Map of LarpingApp object type to its OpenRegister schema slug.
     *
     * The object type is LarpingApp's internal key — it drives the
     * `{type}_schema` / `{type}_register` IAppConfig keys read at runtime by
     * RegisterObjectFetcher AND the frontend object-type used by the SPA. The
     * schema slug is what OpenRegister stores and resolves by.
     *
     * Most types map identity (type === slug). Two are deliberately namespaced:
     * `item` and `event` are extremely common slugs that OpenRegister matches
     * GLOBALLY (ImportHandler::importSchema looks up an existing schema by slug
     * with `_multitenancy: false` and no application scope). On a shared
     * instance another app's `item`/`event` schema is created first, so the
     * bare-slug import bound LarpingApp's `item_schema` to a foreign schema
     * (e.g. Scholiq's QTI "Item", which hard-fails RPG item creation with a
     * 400). Namespacing the slug to `larping_item` / `larping_event` makes
     * LarpingApp own its schema regardless of import order or instance state,
     * while the `item` / `event` object-type keys (and `item_schema` /
     * `event_schema` config keys) stay unchanged for the runtime + frontend.
     * Closes the item-schema-collision bug.
     *
     * @var array<string, string>
     */
    private const OBJECT_TYPE_SCHEMA_SLUGS = [
        'character' => 'character',
        'player'    => 'player',
        'ability'   => 'ability',
        'skill'     => 'skill',
        'item'      => 'larping_item',
        'condition' => 'condition',
        'effect'    => 'effect',
        'event'     => 'larping_event',
        'setting'   => 'setting',
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

        // ADR-037: fold the merged register-fragment signature into the import
        // version so OpenRegister's version-gated import re-runs whenever any
        // lib/Settings/register.d/*.json fragment changes.
        $fragmentSignature = $this->fileLoader->getFragmentSignature();
        if ($fragmentSignature !== '') {
            $currentAppVersion .= '+frag.'.$fragmentSignature;
        }

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

        foreach (self::OBJECT_TYPE_SCHEMA_SLUGS as $objectType => $schemaSlug) {
            if (isset($schemaMap[$schemaSlug]) === true && $schemaMap[$schemaSlug] !== null) {
                $this->appConfig->setValueString(Application::APP_ID, "{$objectType}_schema", (string) $schemaMap[$schemaSlug]);
                $this->appConfig->setValueString(Application::APP_ID, "{$objectType}_source", 'openregister');
                if ($registerId !== null) {
                    $this->appConfig->setValueString(Application::APP_ID, "{$objectType}_register", (string) $registerId);
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
            throw new RuntimeException('Configuration service is not available.');
        }

        // @var object $service
        $service = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
        return $service;

    }//end getConfigurationService()
}//end class
