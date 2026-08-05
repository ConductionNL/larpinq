<?php
/**
 * LarpingApp admin section implementation
 *
 * @category  Settings
 * @package   OCA\LarpingApp\Sections
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-16
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-17
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-18
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Sections;

use OCA\LarpingApp\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Admin Section for LarpingApp
 *
 * @category  Apps
 * @package   LarpingApp
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @psalm-api
 * @php-version 8.2
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-16
 */
class LarpingAppAdmin implements IIconSection
{

    /**
     * Localization service instance
     *
     * @var IL10N
     */
    private $l10n;

    /**
     * URL generator service instance
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Constructor
     *
     * @param IL10N         $l10n         Localization service
     * @param IURLGenerator $urlGenerator URL generator service
     *
     * @return void
     */
    public function __construct(IL10N $l10n, IURLGenerator $urlGenerator)
    {
        $this->l10n         = $l10n;
        $this->urlGenerator = $urlGenerator;
    }//end __construct()

    /**
     * Get the section icon
     *
     * @return string
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-16
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(appName: Application::APP_ID, file: 'app-dark.svg');
    }//end getIcon()

    /**
     * Get the section ID
     *
     * @return string The section ID
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-17
     */
    public function getID(): string
    {
        return Application::APP_ID;
    }//end getID()

    /**
     * Get the section name
     *
     * @return string The section name
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-17
     */
    public function getName(): string
    {
        return $this->l10n->t('LarpingApp');
    }//end getName()

    /**
     * Get the section priority
     *
     * @return int The section priority
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-18
     */
    public function getPriority(): int
    {
        return 55;
    }//end getPriority()
}//end class
