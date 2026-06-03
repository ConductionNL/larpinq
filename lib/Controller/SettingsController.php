<?php

/**
 * LarpingApp SettingsController.
 *
 * Controller for managing LarpingApp application settings.
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-20
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-21
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-22
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-23
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-24
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-25
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-26
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\AppInfo\Application;
use OCA\LarpingApp\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Controller for LarpingApp settings.
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 */
class SettingsController extends Controller
{

    /**
     * The OpenRegister object service.
     *
     * @var object|null The OpenRegister object service.
     */
    private ?object $objectService = null;

    /**
     * Constructor.
     *
     * @param IRequest           $request         The request.
     * @param ContainerInterface $container       The container.
     * @param IAppManager        $appManager      The app manager.
     * @param SettingsService    $settingsService The settings service.
     * @param IGroupManager      $groupManager    The group manager.
     * @param IUserSession       $userSession     The user session.
     * @param LoggerInterface    $logger          The logger.
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private readonly ContainerInterface $container,
        private readonly IAppManager $appManager,
        private readonly SettingsService $settingsService,
        private readonly IGroupManager $groupManager,
        private readonly IUserSession $userSession,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(appName: Application::APP_ID, request: $request);

    }//end __construct()

    /**
     * Attempts to retrieve the OpenRegister service from the container.
     *
     * @return object|null The OpenRegister service if available, null otherwise.
     * @throws RuntimeException If the service is not available.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-22
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
     */
    public function getObjectService(): ?object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
            // @var object $service
            $service = $this->container->get('OCA\OpenRegister\Service\ObjectService');
            $this->objectService = $service;
            return $this->objectService;
        }

        throw new RuntimeException('OpenRegister service is not available.');

    }//end getObjectService()

    /**
     * Attempts to retrieve the Configuration service from the container.
     *
     * @return object|null The Configuration service if available, null otherwise.
     * @throws RuntimeException If the service is not available.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-22
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
     */
    public function getConfigurationService(): ?object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
            // @var object $configurationService
            $configurationService = $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
            return $configurationService;
        }

        throw new RuntimeException('Configuration service is not available.');

    }//end getConfigurationService()

    /**
     * Attempts to retrieve the OpenRegister RegisterMapper from the container.
     *
     * @return object|null The RegisterMapper if available, null otherwise.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-22
     */
    private function getRegisterMapper(): ?object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
            // @var object $registerMapper
            $registerMapper = $this->container->get('OCA\OpenRegister\Db\RegisterMapper');
            return $registerMapper;
        }

        return null;

    }//end getRegisterMapper()

    /**
     * Attempts to retrieve the OpenRegister SchemaMapper from the container.
     *
     * @return object|null The SchemaMapper if available, null otherwise.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-22
     */
    private function getSchemaMapper(): ?object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
            // @var object $schemaMapper
            $schemaMapper = $this->container->get('OCA\OpenRegister\Db\SchemaMapper');
            return $schemaMapper;
        }

        return null;

    }//end getSchemaMapper()

    /**
     * Enrich registers with full schema objects instead of just schema IDs.
     *
     * @param array       $registers    The registers to enrich.
     * @param object|null $schemaMapper The SchemaMapper, or null.
     *
     * @return array Registers serialized as arrays with full schema objects.
     */
    private function enrichRegistersWithSchemas(array $registers, ?object $schemaMapper): array
    {
        $result = [];

        foreach ($registers as $register) {
            // @psalm-suppress MixedMethodCall Register entity from OpenRegister.
            $registerArray = $register->jsonSerialize();
            $schemaIds     = $registerArray['schemas'] ?? [];
            $schemas       = [];

            if ($schemaMapper !== null && empty($schemaIds) === false) {
                foreach ($schemaIds as $schemaId) {
                    try {
                        // @psalm-suppress MixedMethodCall SchemaMapper from OpenRegister.
                        $schema    = $schemaMapper->find((int) $schemaId);
                        $schemas[] = $schema->jsonSerialize();
                    } catch (\Exception $e) {
                        $this->logger->debug(
                            'Schema not found while enriching register',
                            ['schemaId' => $schemaId, 'exception' => $e->getMessage()]
                        );
                    }
                }
            }

            $registerArray['schemas'] = $schemas;
            $result[] = $registerArray;
        }//end foreach

        return $result;

    }//end enrichRegistersWithSchemas()

    /**
     * Get current LarpingApp settings.
     *
     * @return JSONResponse The settings response.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-20
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-21
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-22
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-23
     */
    public function index(): JSONResponse
    {
        $user    = $this->userSession->getUser();
        $isAdmin = $user !== null && $this->groupManager->isAdmin($user->getUID());

        $openRegisters      = in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps());
        $availableRegisters = [];

        if ($isAdmin === true && $openRegisters === true) {
            try {
                $registerMapper = $this->getRegisterMapper();
                $schemaMapper   = $this->getSchemaMapper();
                if ($registerMapper !== null) {
                    // @psalm-suppress MixedMethodCall RegisterMapper from OpenRegister.
                    $registers          = $registerMapper->findAll(_rbac: false, _multitenancy: false);
                    $availableRegisters = $this->enrichRegistersWithSchemas(registers: $registers, schemaMapper: $schemaMapper);
                }
            } catch (\Exception $e) {
                $this->logger->warning(
                    'Failed to load available registers for settings',
                    ['exception' => $e->getMessage()]
                );
            }
        }

        $data = [
            'openRegisters'      => $openRegisters,
            'isAdmin'            => $isAdmin,
            'availableRegisters' => $availableRegisters,
            'objectTypes'        => [
                'ability',
                'character',
                'condition',
                'effect',
                'event',
                'item',
                'player',
                'setting',
                'skill',
            ],
        ];

        if ($isAdmin === true) {
            $data['configuration'] = $this->settingsService->getSettings();
        }

        return new JSONResponse($data);

    }//end index()

    /**
     * Update LarpingApp settings.
     *
     * CSRF protection is required — this is a state-mutating admin POST.
     *
     * @NoCSRFRequired removed to close the CSRF-forgery surface (closes #206).
     *
     * @return JSONResponse The updated settings response.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-24
     */
    public function create(): JSONResponse
    {
        try {
            $data   = $this->request->getParams();
            $config = $this->settingsService->updateSettings(data: $data);

            return new JSONResponse(
                [
                    'success' => true,
                    'config'  => $config,
                ]
            );
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }//end try

    }//end create()

    /**
     * Re-import the LarpingApp configuration from the JSON file.
     *
     * CSRF protection is required — this is a state-mutating admin POST.
     *
     * @NoCSRFRequired removed to close the CSRF-forgery surface (closes #206).
     *
     * @return JSONResponse The re-import result.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-25
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-26
     */
    public function reimport(): JSONResponse
    {
        try {
            $result = $this->settingsService->loadSettings(force: true);

            return new JSONResponse(
                [
                    'success' => true,
                    'message' => 'Configuration re-imported successfully',
                    'config'  => $this->settingsService->getSettings(),
                    'result'  => [
                        'registers' => count((array) ($result['registers'] ?? [])),
                        'schemas'   => count((array) ($result['schemas'] ?? [])),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return new JSONResponse(
                [
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
                500
            );
        }//end try

    }//end reimport()
}//end class
