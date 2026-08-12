<?php

/**
 * Unit tests for the SettingsController write path (update / create).
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Controller
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2024 Ruben Linde <ruben@larpingapp.com>
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Controller;

use OCA\LarpingApp\Controller\SettingsController;
use OCA\LarpingApp\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * `PUT /api/settings` (`settings#update`) is the canonical AppHost write and
 * `POST /api/settings` (`settings#create`) is its legacy alias. Both land in
 * this controller — LarpingApp ships the class itself, so OpenRegister's
 * generic settings controller is never aliased in to cover either.
 *
 * These tests assert the ITEM: that the write actually reaches
 * `SettingsService::updateSettings()` carrying the request's own parameters,
 * and that the response carries back what the service stored. A test that
 * checked only for a JSONResponse, or only for HTTP 200, would pass against a
 * controller that silently wrote nothing.
 *
 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
 */
class SettingsControllerWriteTest extends TestCase {

	/**
	 * Mock IRequest.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Mock SettingsService.
	 *
	 * @var SettingsService&MockObject
	 */
	private SettingsService&MockObject $settingsService;

	/**
	 * The controller under test.
	 *
	 * @var SettingsController
	 */
	private SettingsController $controller;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->settingsService = $this->createMock(SettingsService::class);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getInstalledApps')->willReturn(['openregister']);

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$this->controller = new SettingsController(
			request: $this->request,
			container: $this->createMock(ContainerInterface::class),
			appManager: $appManager,
			settingsService: $this->settingsService,
			groupManager: $this->createMock(IGroupManager::class),
			userSession: $userSession,
			logger: $this->createMock(LoggerInterface::class),
		);

	}//end setUp()

	/**
	 * update() must hand the request parameters to the settings service and
	 * return the config the service actually stored.
	 *
	 * The returned map is deliberately NOT the submission — SettingsService
	 * whitelists against CONFIG_KEYS and re-reads from IAppConfig, so returning
	 * the submission would hide every rejected key.
	 *
	 * @return void
	 */
	public function testUpdatePersistsRequestParametersAndReturnsStoredConfig(): void {
		$submitted = ['character_register' => '3', 'character_schema' => '7'];
		$stored = ['character_register' => '3', 'character_schema' => '7', 'version' => '1.2.3'];

		$this->request->expects($this->once())
			->method('getParams')
			->willReturn($submitted);

		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(data: $submitted)
			->willReturn($stored);

		$response = $this->controller->update();

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(
			['success' => true, 'config' => $stored],
			$response->getData(),
			'update() must return the config the service stored, not the submission'
		);

	}//end testUpdatePersistsRequestParametersAndReturnsStoredConfig()

	/**
	 * create() is a pure delegation to update() and must still write.
	 *
	 * LarpingApp's own frontend still POSTs here
	 * (`src/store/modules/settings.js::saveSettings()` and
	 * `src/views/settings/Settings.vue`), so the alias degrading into an empty
	 * success would be invisible from the UI while silently losing every save.
	 *
	 * @return void
	 */
	public function testCreateDelegatesToUpdateAndStillWrites(): void {
		$submitted = ['skill_source' => 'openregister'];
		$stored = ['skill_source' => 'openregister'];

		$this->request->expects($this->once())
			->method('getParams')
			->willReturn($submitted);

		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(data: $submitted)
			->willReturn($stored);

		$response = $this->controller->create();

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(
			['success' => true, 'config' => $stored],
			$response->getData(),
			'create() must produce exactly the result update() produces'
		);

	}//end testCreateDelegatesToUpdateAndStillWrites()

	/**
	 * An empty submission must still reach the service.
	 *
	 * An early return on an empty payload is indistinguishable, from the
	 * caller's side, from a successful no-op write.
	 *
	 * @return void
	 */
	public function testEmptySubmissionStillReachesTheService(): void {
		$this->request->method('getParams')->willReturn([]);

		$this->settingsService->expects($this->once())
			->method('updateSettings')
			->with(data: [])
			->willReturn(['unchanged' => true]);

		$response = $this->controller->update();

		self::assertSame(
			['success' => true, 'config' => ['unchanged' => true]],
			$response->getData()
		);

	}//end testEmptySubmissionStillReachesTheService()

	/**
	 * update() must translate a service failure into a 500 with an error key,
	 * matching the contract create() has always had.
	 *
	 * @return void
	 */
	public function testUpdateReturns500OnServiceException(): void {
		$this->request->method('getParams')->willReturn(['character_register' => '3']);

		$this->settingsService->method('updateSettings')
			->willThrowException(new \Exception('Service error'));

		$response = $this->controller->update();

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(500, $response->getStatus());
		self::assertSame('Service error', $response->getData()['error']);

	}//end testUpdateReturns500OnServiceException()

}//end class
