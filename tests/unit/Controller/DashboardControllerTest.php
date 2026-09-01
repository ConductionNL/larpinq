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

	/**
	 * The SPA catch-all serves the same shell as page().
	 *
	 * `dashboard#catchAll` on `/{path}` is what makes a deep link survive a
	 * RELOAD. Before it existed, /apps/larpinq/characters and /events both
	 * returned 404 while every other hash-mode app answered 200, and that 404
	 * is what kept this app on hash routing. The failure is invisible from
	 * inside the SPA, because the SPA never loads to report it.
	 *
	 * Serving *a* response is not the contract. Serving the app shell is: a
	 * catch-all that answered with anything else would still route, still
	 * return 200, and still leave every deep link broken.
	 */
	public function testCatchAllServesTheAppShell(): void {
		$result = $this->controller->catchAll();

		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertSame('index', $result->getTemplateName());
	}

	/**
	 * The two entry points agree, so a deep link is not a second-class page.
	 *
	 * Asserted as an observable pair rather than by reading catchAll()'s body,
	 * so it survives the delegation being rewritten.
	 */
	public function testCatchAllAndPageAgreeOnTemplateAndParams(): void {
		$page = $this->controller->page();
		$catchAll = $this->controller->catchAll();

		self::assertSame($page->getTemplateName(), $catchAll->getTemplateName());
		self::assertSame($page->getParams(), $catchAll->getParams());
	}
}
