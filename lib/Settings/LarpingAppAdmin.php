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
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Settings;

use OCA\LarpingApp\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/**
 * Admin settings form for the LarpingApp application.
 */
class LarpingAppAdmin implements ISettings
{
    /**
     * Constructor.
     *
     * @param IAppManager $appManager The app manager.
     */
    public function __construct(
        private IAppManager $appManager,
    ) {
    }//end __construct()

    /**
     * Get the admin settings form.
     *
     * @return TemplateResponse The settings form template.
     */
    public function getForm(): TemplateResponse
    {
        $version = $this->appManager->getAppVersion(appId: Application::APP_ID);

        return new TemplateResponse(
            Application::APP_ID,
            'settings/admin',
            ['version' => $version]
        );
    }//end getForm()

    /**
     * Get the settings section ID.
     *
     * @return string The section ID.
     */
    public function getSection(): string
    {
        return 'larpingapp';
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
