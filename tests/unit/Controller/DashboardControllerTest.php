<?php

/**
 * Unit tests for DashboardController.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Controller;

use OCA\LarpingApp\Controller\DashboardController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardController.
 */
class DashboardControllerTest extends TestCase
{

    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new DashboardController(
            'larpingapp',
            $this->createMock(IRequest::class),
            $this->createMock(IAppConfig::class),
        );
    }

    public function testPageReturnsTemplateResponse(): void
    {
        $result = $this->controller->page();

        self::assertInstanceOf(TemplateResponse::class, $result);
    }

    public function testPageUsesIndexTemplate(): void
    {
        $result = $this->controller->page();

        self::assertSame('index', $result->getTemplateName());
    }

    public function testPageUsesLarpingappApp(): void
    {
        $result = $this->controller->page();

        // TemplateResponse stores the app name.
        self::assertInstanceOf(TemplateResponse::class, $result);
    }

    public function testPageReturnsEmptyParams(): void
    {
        $result = $this->controller->page();

        self::assertEmpty($result->getParams());
    }
}
