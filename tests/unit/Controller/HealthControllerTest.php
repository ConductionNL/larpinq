<?php

/**
 * HealthControllerTest.
 *
 * The failure a health probe must never have is refusing to answer. If
 * `/api/health` errors when OpenRegister is missing, monitoring cannot tell
 * "larpinq is down" from "larpinq cannot report", and larpinq does not declare
 * `<app>openregister</app>`, so an administrator can create exactly that
 * instance. These tests hold the degraded path to a documented HTTP 200.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Controller\HealthController;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Covers the health endpoint's envelope and its OpenRegister-absent fallback.
 *
 * @spec openspec/specs/apphost-adoption/spec.md
 */
class HealthControllerTest extends TestCase {

	/**
	 * Mocked HTTP request.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Mocked Nextcloud config.
	 *
	 * @var IConfig&MockObject
	 */
	private IConfig&MockObject $config;

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
		$this->config = $this->createMock(IConfig::class);
		$this->container = $this->createMock(ContainerInterface::class);

	}//end setUp()

	/**
	 * The controller under test.
	 *
	 * @return HealthController
	 */
	private function controller(): HealthController {
		return new HealthController($this->request, $this->config, $this->container);

	}//end controller()

	/**
	 * With no engine in the container, health still answers 200 degraded.
	 *
	 * @return void
	 */
	public function testAnswersDegradedWhenTheEngineCannotBeResolved(): void {
		$this->container->method('get')
			->willThrowException(new \RuntimeException('not registered'));
		$this->config->method('getAppValue')
			->with(Application::APP_ID, 'installed_version', '')
			->willReturn('9.9.9');

		$response = $this->controller()->index();
		$data = $response->getData();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('degraded', $data['status']);
		$this->assertSame(Application::APP_ID, $data['app']);
		$this->assertSame('9.9.9', $data['version']);
		$this->assertSame(['openregister' => 'unavailable'], $data['checks']);

	}//end testAnswersDegradedWhenTheEngineCannotBeResolved()

	/**
	 * The degraded envelope carries all four ADR-006 keys, not a subset.
	 *
	 * A probe that omits a key its consumer reads is a broken contract even
	 * when the status code is right.
	 *
	 * @return void
	 */
	public function testDegradedEnvelopeCarriesTheFullShape(): void {
		$this->container->method('get')
			->willThrowException(new \RuntimeException('not registered'));
		$this->config->method('getAppValue')->willReturn('');

		$data = $this->controller()->index()->getData();

		$this->assertSame(
			['status', 'app', 'version', 'checks'],
			array_keys($data),
			'The ADR-006 envelope keys and their order are the contract.'
		);

	}//end testDegradedEnvelopeCarriesTheFullShape()

	/**
	 * A container that returns an unusable engine degrades rather than fatals.
	 *
	 * `ContainerInterface::get()` is not required to throw for an unknown id in
	 * every implementation, so the null-object case is exercised separately
	 * from the throwing one.
	 *
	 * @return void
	 */
	public function testDegradesWhenTheContainerAnswersWithAnUnusableEngine(): void {
		$this->container->method('get')->willReturn(new \stdClass());
		$this->config->method('getAppValue')->willReturn('1.2.3');

		$response = $this->controller()->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('degraded', $response->getData()['status']);

	}//end testDegradesWhenTheContainerAnswersWithAnUnusableEngine()
}//end class
