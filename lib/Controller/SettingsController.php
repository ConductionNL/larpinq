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
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\AppInfo\Application;
use OCA\LarpingApp\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for LarpingApp settings.
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class SettingsController extends Controller
{
    /**
     * Constructor.
     *
     * @param IRequest        $request         The request.
     * @param SettingsService $settingsService The settings service.
     *
     * @return void
     */
    public function __construct(
        IRequest $request,
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct(Application::APP_ID, $request);

    }//end __construct()

    /**
     * Get current LarpingApp settings.
     *
     * @return JSONResponse The settings response.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): JSONResponse
    {
        $data = [
            'objectTypes'   => [
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
            'configuration' => $this->settingsService->getSettings(),
        ];

        return new JSONResponse($data);

    }//end index()

    /**
     * Update LarpingApp settings.
     *
     * @return JSONResponse The updated settings response.
     *
     * @NoCSRFRequired
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
     * @return JSONResponse The re-import result.
     *
     * @NoCSRFRequired
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
                        'registers' => count($result['registers'] ?? []),
                        'schemas'   => count($result['schemas'] ?? []),
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
