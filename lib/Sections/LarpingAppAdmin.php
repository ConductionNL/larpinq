<?php

declare(strict_types=1);

/**
 * LarpingApp admin section implementation
 *
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 *
 * @category Settings
 * @package  OCA\LarpingApp\Sections
 * @link     https://larpingapp.com
 */

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
 * @phpstan-api
 * @php-version 8.2
 */
class LarpingAppAdmin implements IIconSection
{
    /**
     * @var IL10N
     */
    private $_l;

    /**
     * @var IURLGenerator
     */
    private $_urlGenerator;

    /**
     * Constructor
     *
     * @param  IL10N         $l            Localization service
     * @param  IURLGenerator $urlGenerator URL generator service
     * @return void
     */
    public function __construct(IL10N $l, IURLGenerator $urlGenerator)
    {
        $this->_l = $l;
        $this->_urlGenerator = $urlGenerator;
    }

    /**
     * Get the section icon
     *
     * @return string
     */
    public function getIcon(): string
    {
        return $this->_urlGenerator->imagePath('larpingapp', 'app-dark.svg');
    }

    /**
     * Get the section ID
     *
     * @return string The section ID
     */
    public function getID(): string
    {
        return 'larpingapp';
    }

    /**
     * Get the section name
     *
     * @return string The section name
     */
    public function getName(): string
    {
        return $this->_l->t('LarpingApp');
    }

    /**
     * Get the section priority
     *
     * @return int The section priority
     */
    public function getPriority(): int
    {
        return 55;
    }
}