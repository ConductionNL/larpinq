<?php

/**
 * LarpinqAdmin renders the admin settings page with the app version as initial state.
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
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Larpinq\Settings\LarpinqAdmin
 */
class LarpinqAdminTest extends TestCase {

	/**
	 * The form hands the installed version to the frontend and renders the admin template.
	 *
	 * @return void
	 */
	public function testTheFormProvidesTheVersionAndRendersTheAdminTemplate(): void {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->expects($this->once())->method('getAppVersion')->with('larpinq')->willReturn('1.4.2');
		$state = $this->createMock(originalClassName: IInitialState::class);
		$state->expects($this->once())->method('provideInitialState')->with('version', '1.4.2');

		$form = (new LarpinqAdmin(appManager: $apps, initialState: $state))->getForm();

		$this->assertInstanceOf(expected: TemplateResponse::class, actual: $form);
		$this->assertSame(expected: 'larpinq', actual: $form->getApp());
		$this->assertSame(expected: 'settings/admin', actual: $form->getTemplateName());
		$this->assertSame(expected: [], actual: $form->getParams());
	}

	/**
	 * The page sits in the larpinq section at priority 10 and delegates no app config.
	 *
	 * @return void
	 */
	public function testThePageSitsInTheLarpinqSectionAndDelegatesNothing(): void {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->expects($this->never())->method('getAppVersion');
		$page = new LarpinqAdmin(appManager: $apps, initialState: $this->createMock(originalClassName: IInitialState::class));

		$this->assertSame(expected: 'larpinq', actual: $page->getSection());
		$this->assertSame(expected: 10, actual: $page->getPriority());
		$this->assertNull(actual: $page->getName());
		$this->assertSame(expected: [], actual: $page->getAuthorizedAppConfig());
	}
}
