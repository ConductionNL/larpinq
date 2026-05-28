<?php
/**
 * LarpingApp admin settings implementation.
 *
 * @category  Settings
 * @package   OCA\LarpingApp\Settings
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-17
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-19
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Settings;

use OCA\LarpingApp\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

/**
 * Admin settings form for the LarpingApp application.
 *
 * @psalm-suppress UnusedClass Registered in appinfo/info.xml as admin settings.
 */
class LarpingAppAdmin implements ISettings
{
    /**
     * Constructor.
     *
     * @param IAppManager   $appManager   The app manager.
     * @param IInitialState $initialState The initial state service.
     */
    public function __construct(
        private IAppManager $appManager,
        private IInitialState $initialState,
    ) {
    }//end __construct()

    /**
     * Get the admin settings form.
     *
     * @return TemplateResponse The settings form template.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-19
     */
    public function getForm(): TemplateResponse
    {
        $version = $this->appManager->getAppVersion(appId: Application::APP_ID);

        $this->initialState->provideInitialState('version', $version);

        return new TemplateResponse(
            Application::APP_ID,
            'settings/admin',
            []
        );
    }//end getForm()

    /**
     * Get the settings section ID.
     *
     * @return string The section ID.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-17
     */
    public function getSection(): string
    {
        return Application::APP_ID;
    }//end getSection()

    /**
     * Get the settings priority.
     *
     * @return int The priority.
     */
    public function getPriority(): int
    {
        return 10;
    }//end getPriority()
}//end class
