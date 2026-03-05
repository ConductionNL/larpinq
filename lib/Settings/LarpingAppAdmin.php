<?php
/**
 * LarpingApp admin section implementation
 *
 * @category  Settings
 * @package   OCA\LarpingApp\Settings
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Admin section for LarpingApp settings
 *
 * Provides the admin section configuration for the LarpingApp
 *
 * @category  Settings
 * @package   OCA\LarpingApp\Settings
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */
class LarpingAppAdmin implements IIconSection
{

    /**
     * Localization service instance
     *
     * @var IL10N
     */
    private $l;

    /**
     * URL generator service instance
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Constructor for the admin section
     *
     * @param IL10N         $l            Localization service
     * @param IURLGenerator $urlGenerator URL generator service
     */
    public function __construct(IL10N $l, IURLGenerator $urlGenerator)
    {
        $this->l            = $l;
        $this->urlGenerator = $urlGenerator;
    }//end __construct()

    /**
     * Get the section icon path
     *
     * Returns the path to the section icon
     *
     * @return string Icon path
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(appName: 'larpingapp', file: 'app-dark.svg');
    }//end getIcon()

    /**
     * Get the section identifier
     *
     * Returns the unique identifier for this section
     *
     * @return string Section ID
     */
    public function getID(): string
    {
        return 'larpingapp';
    }//end getID()

    /**
     * Get the translated section name
     *
     * Returns the localized name of this section
     *
     * @return string Translated section name
     */
    public function getName(): string
    {
        return $this->l->t('LarpingApp');
    }//end getName()

    /**
     * Get the section priority
     *
     * Returns the priority value that determines section ordering
     *
     * @return int Priority value (0-100)
     */
    public function getPriority(): int
    {
        return 55;
    }//end getPriority()
}//end class
