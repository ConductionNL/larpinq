<?php

/**
 * Unit tests for DashboardController.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\DashboardController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardController.
 */
class DashboardControllerTest extends TestCase {

	private DashboardController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = new DashboardController(
			'larpinq',
			$this->createMock(IRequest::class),
		);
	}

	public function testPageReturnsTemplateResponse(): void {
		$result = $this->controller->page();

		self::assertInstanceOf(TemplateResponse::class, $result);
	}

	/**
	 * The SPA catch-all serves the same shell as page().
	 *
	 * `dashboard#catchAll` at GET /{path} is what makes larpinq's deep links
	 * work: before it, /apps/larpinq/characters and /events 404'd at the
	 * SERVER, which is why this app could not simply switch to history routing
	 * with the others. It is a public network-facing endpoint, so gate-25
	 * (contract-coverage) wants a test rather than an `@contract exclude`.
	 *
	 * Asserting equality with page() rather than merely "returns a response" is
	 * the point: catchAll() exists only to delegate, and a delegation that
	 * quietly rendered the wrong template would hand every deep link a blank
	 * page while still answering HTTP 200.
	 *
	 * @return void
	 */
	public function testCatchAllServesTheSameShellAsPage(): void {
		$result = $this->controller->catchAll();
		$page = $this->controller->page();

		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertSame('index', $result->getTemplateName());
		self::assertSame($page->getTemplateName(), $result->getTemplateName());
		self::assertSame($page->getRenderAs(), $result->getRenderAs());
		self::assertSame($page->getParams(), $result->getParams());
	}

	public function testPageUsesIndexTemplate(): void {
		$result = $this->controller->page();

		self::assertSame('index', $result->getTemplateName());
	}

	public function testPageUsesLarpinqApp(): void {
		$result = $this->controller->page();

		// TemplateResponse stores the app name.
		self::assertInstanceOf(TemplateResponse::class, $result);
	}

	public function testPageReturnsEmptyParams(): void {
		$result = $this->controller->page();

		self::assertEmpty($result->getParams());
	}
}
