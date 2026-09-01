<?php

/**
 * MetricsControllerTest.
 *
 * Two properties matter here and neither is the happy path. The endpoint must
 * degrade to 503 rather than 500 when the AppHost engine is missing, because a
 * 500 reads as "larpinq is broken" when the truth is "metrics are not
 * configured". And it must keep the Prometheus content type even while
 * degraded, because a scraper decides how to parse the body from that header.
 *
 * The admin-only posture comes from the deliberate absence of
 * `#[NoAdminRequired]`, which Nextcloud's dispatcher enforces before the method
 * runs. That is asserted here by reflection over the attributes, since a unit
 * test never passes through the dispatcher.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\MetricsController;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;

/**
 * Covers the metrics endpoint's degraded path and its access posture.
 *
 * @spec openspec/specs/apphost-adoption/spec.md
 */
class MetricsControllerTest extends TestCase {

	/**
	 * Mocked HTTP request.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Mocked DI container standing in for the AppHost engine's home.
	 *
	 * @var ContainerInterface&MockObject
	 */
	private ContainerInterface&MockObject $container;

	/**
	 * Build the collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->container = $this->createMock(ContainerInterface::class);

	}//end setUp()

	/**
	 * The controller under test.
	 *
	 * @return MetricsController
	 */
	private function controller(): MetricsController {
		return new MetricsController($this->request, $this->container);

	}//end controller()

	/**
	 * A missing engine is 503 with a Prometheus comment, never a 500.
	 *
	 * @return void
	 */
	public function testDegradesToServiceUnavailableWithoutTheEngine(): void {
		$this->container->method('get')
			->willThrowException(new \RuntimeException('not registered'));

		$response = $this->controller()->index();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$this->assertNotSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertStringStartsWith('#', $response->render());
		$this->assertStringContainsString('observability engine', $response->render());

	}//end testDegradesToServiceUnavailableWithoutTheEngine()

	/**
	 * The declared content type is Prometheus text exposition 0.0.4.
	 *
	 * Asserted against the constant rather than the response, deliberately:
	 * `Response::getHeaders()` reaches into `OCP\Server` for the request id,
	 * which needs the `OC` server container that a unit test does not boot.
	 * The constant is the single place the value is written, so pinning it
	 * still catches a silent change to what scrapers are told to parse.
	 *
	 * @return void
	 */
	public function testDeclaresThePrometheusContentType(): void {
		$reflected = new \ReflectionClass(MetricsController::class);

		$this->assertSame(
			'text/plain; version=0.0.4; charset=utf-8',
			$reflected->getConstant('CONTENT_TYPE')
		);

	}//end testDeclaresThePrometheusContentType()

	/**
	 * `index()` carries no `#[NoAdminRequired]`, so NC requires an admin.
	 *
	 * This is the whole access control for the endpoint. Adding that attribute
	 * would silently open metric data to any signed-in user, and nothing else
	 * in the class would change, so the absence is asserted directly.
	 *
	 * @return void
	 */
	public function testIndexIsAdminOnlyByOmittingNoAdminRequired(): void {
		$attributes = (new ReflectionMethod(MetricsController::class, 'index'))
			->getAttributes();
		$names = array_map(static fn ($a) => $a->getName(), $attributes);

		$this->assertNotContains(
			'OCP\AppFramework\Http\Attribute\NoAdminRequired',
			$names,
			'Metrics must stay admin-only: NoAdminRequired would open it to any user.'
		);
		$this->assertNotContains(
			'OCP\AppFramework\Http\Attribute\PublicPage',
			$names,
			'Metrics must never be a public page.'
		);

	}//end testIndexIsAdminOnlyByOmittingNoAdminRequired()
}//end class
