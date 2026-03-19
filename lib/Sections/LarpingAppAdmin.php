<?php
/**
 * LarpingApp admin section implementation
 *
 * @category  Settings
 * @package   OCA\LarpingApp\Sections
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Sections;

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
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 *
 * @psalm-api
 * @php-version 8.2
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
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(appName: 'larpingapp', file: 'app-dark.svg');
    }//end getIcon()

    /**
     * Get the section ID
     *
     * @return string The section ID
     */
    public function getID(): string
    {
        return 'larpingapp';
    }//end getID()

    /**
     * Get the section name
     *
     * @return string The section name
     */
    public function getName(): string
    {
        return $this->l10n->t('LarpingApp');
    }//end getName()

    /**
     * Get the section priority
     *
     * @return int The section priority
     */
    public function getPriority(): int
    {
        return 55;
    }//end getPriority()
}//end class
