<?php

declare(strict_types=1);

/**
 * Dashboard controller implementation
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   Release: 0.1.0
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

/**
 * Controller for handling dashboard operations
 *
 * This controller provides endpoints for the main dashboard view
 * of the LarpingApp.
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class DashboardController extends Controller
{
    /**
     * DashboardController constructor
     *
     * @param string     $appName The name of the app
     * @param IRequest   $request The request object
     * @param IAppConfig $config  The app configuration service
     * 
     * @return void
     */
    public function __construct(
        $appName,
        IRequest $request,
    private readonly IAppConfig $config
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Render the main app's page
     *
     * This returns the template of the main app's page.
     * It adds some data to the template (app version).
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return TemplateResponse The template response for the main page
     * 
     * @psalm-return   TemplateResponse
     * @phpstan-return TemplateResponse
     */
    public function page(): TemplateResponse
    {            
        return new TemplateResponse(
            'larpingapp',
            'index',
            []
        );
    }
}
