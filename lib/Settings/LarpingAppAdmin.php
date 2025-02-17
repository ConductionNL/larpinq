<?php

declare(strict_types=1);

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

namespace OCA\LarpingApp\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Admin section for LarpingApp settings
 *
 * Provides the admin section configuration for the LarpingApp
 *
 * @category Settings
 * @package  OCA\LarpingApp\Settings
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class LarpingAppAdmin implements IIconSection
{
    /**
     * Localization service instance
     *
     * @var IL10N
     */
    private $_l;

    /**
     * URL generator service instance
     *
     * @var IURLGenerator
     */
    private $_urlGenerator;

    /**
     * Constructor for the admin section
     *
     * @param IL10N         $l            Localization service
     * @param IURLGenerator $urlGenerator URL generator service
     */
    public function __construct(IL10N $l, IURLGenerator $urlGenerator)
    {
        $this->_l = $l;
        $this->_urlGenerator = $urlGenerator;
    }

    /**
     * Get the section icon path
     *
     * Returns the path to the section icon
     *
     * @return string Icon path
     */
    public function getIcon(): string
    {
        return $this->_urlGenerator->imagePath('larpingapp', 'app-dark.svg');
    }

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
    }

    /**
     * Get the translated section name
     *
     * Returns the localized name of this section
     *
     * @return string Translated section name
     */
    public function getName(): string
    {
        return $this->_l->t('LarpingApp');
    }

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
    }
}