<?php
/**
 * Dashboard controller for LarpingApp
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-98
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-99
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-100
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-101
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use OCA\LarpingApp\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

/**
 * Dashboard controller for LarpingApp main page
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later
 * @link      https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 */
class DashboardController extends Controller
{
    /**
     * Constructor for DashboardController
     *
     * @param string     $appName Application name
     * @param IRequest   $request HTTP request object
     * @param IAppConfig $config  Application configuration service
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly IAppConfig $config
    ) {
        parent::__construct(appName: $appName, request: $request);
    }//end __construct()

    /**
     * This returns the template of the main app's page
     * It adds some data to the template (app version)
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return TemplateResponse
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-98
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-99
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-100
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-101
     */
    public function page(): TemplateResponse
    {
        return new TemplateResponse(
            Application::APP_ID,
            'index',
            []
        );
    }//end page()
}//end class
