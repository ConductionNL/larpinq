<?php

/**
 * Unit tests for the Larpinq admin settings page and its section.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Sections\LarpinqAdmin as LarpinqAdminSection;
use OCA\Larpinq\Settings\LarpinqAdmin;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Larpinq\Settings\LarpinqAdmin
 * @covers \OCA\Larpinq\Sections\LarpinqAdmin
 */
class LarpinqAdminTest extends TestCase {

	/**
	 * The form hands the app version to the page and renders the admin template.
	 *
	 * @return void
	 */
	public function testTheFormProvidesTheVersion(): void {
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppVersion')->with('larpinq')->willReturn('2.1.0');
		$state = $this->createMock(originalClassName: IInitialState::class);
		$state->expects($this->once())->method('provideInitialState')->with('version', '2.1.0');

		$response = (new LarpinqAdmin(appManager: $apps, initialState: $state))->getForm();

		$this->assertSame(expected: 'larpinq', actual: $response->getApp());
		$this->assertSame(expected: 'settings/admin', actual: $response->getTemplateName());
		$this->assertSame(expected: [], actual: $response->getParams());
	}//end testTheFormProvidesTheVersion()

	/**
	 * The page sits in the app's own section, which exists, and delegates no config keys.
	 *
	 * @return void
	 */
	public function testThePageSitsInTheAppsOwnSection(): void {
		$page = new LarpinqAdmin(
			appManager: $this->createMock(originalClassName: IAppManager::class),
			initialState: $this->createMock(originalClassName: IInitialState::class)
		);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->expects($this->once())->method('imagePath')->with('larpinq', 'app-dark.svg')->willReturn('/apps/larpinq/img/app-dark.svg');
		$section = new LarpinqAdminSection(l10n: $l10n, urlGenerator: $urls);

		$this->assertSame(expected: $section->getID(), actual: $page->getSection());
		$this->assertSame(expected: 'larpinq', actual: $section->getID());
		$this->assertSame(expected: 'Larpinq', actual: $section->getName());
		$this->assertSame(expected: '/apps/larpinq/img/app-dark.svg', actual: $section->getIcon());
		$this->assertSame(expected: 55, actual: $section->getPriority());
		$this->assertSame(expected: 10, actual: $page->getPriority());
		$this->assertNull(actual: $page->getName());
		$this->assertSame(expected: [], actual: $page->getAuthorizedAppConfig());
	}//end testThePageSitsInTheAppsOwnSection()
}//end class
