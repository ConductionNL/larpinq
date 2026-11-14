<?php

/**
 * Unit tests for the Larpinq admin settings form.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Settings\LarpinqAdmin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\IDelegatedSettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the form, section, priority and delegation contract of LarpinqAdmin.
 *
 * @covers \OCA\Larpinq\Settings\LarpinqAdmin
 */
class LarpinqAdminTest extends TestCase {

	/**
	 * App manager mock.
	 *
	 * @var IAppManager&MockObject
	 */
	private IAppManager $appManager;

	/**
	 * Initial state mock.
	 *
	 * @var IInitialState&MockObject
	 */
	private IInitialState $initialState;

	/**
	 * Subject under test.
	 *
	 * @var LarpinqAdmin
	 */
	private LarpinqAdmin $settings;

	/**
	 * Build the subject with mocked boundaries.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->initialState = $this->createMock(originalClassName: IInitialState::class);
		$this->settings = new LarpinqAdmin(appManager: $this->appManager, initialState: $this->initialState);
	}

	/**
	 * The form provides the installed app version as initial state and renders the admin template.
	 *
	 * @return void
	 */
	public function testGetFormProvidesVersionAndRendersAdminTemplate(): void {
		$this->appManager->expects($this->once())
			->method('getAppVersion')
			->with('larpinq')
			->willReturn('3.4.5');
		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('version', '3.4.5');

		$response = $this->settings->getForm();

		$this->assertInstanceOf(expected: TemplateResponse::class, actual: $response);
		$this->assertSame(expected: 'larpinq', actual: $response->getApp());
		$this->assertSame(expected: 'settings/admin', actual: $response->getTemplateName());
		$this->assertSame(expected: [], actual: $response->getParams());
	}

	/**
	 * The form is rendered as a settings fragment, never as a full user page.
	 *
	 * @return void
	 */
	public function testGetFormRendersAsDefaultRenderMode(): void {
		$this->appManager->method('getAppVersion')->willReturn('0.0.1');

		$response = $this->settings->getForm();

		$this->assertSame(expected: TemplateResponse::RENDER_AS_USER, actual: $response->getRenderAs());
	}

	/**
	 * The settings live in the app's own section with priority 10.
	 *
	 * @return void
	 */
	public function testSectionAndPriority(): void {
		$this->assertSame(expected: 'larpinq', actual: $this->settings->getSection());
		$this->assertSame(expected: 10, actual: $this->settings->getPriority());
	}

	/**
	 * Delegation exposes no name and no app-config keys, and the class is a delegated setting.
	 *
	 * @return void
	 */
	public function testDelegationContract(): void {
		$this->assertInstanceOf(expected: IDelegatedSettings::class, actual: $this->settings);
		$this->assertNull(actual: $this->settings->getName());
		$this->assertSame(expected: [], actual: $this->settings->getAuthorizedAppConfig());
	}
}
