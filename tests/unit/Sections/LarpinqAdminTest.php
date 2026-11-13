<?php

/**
 * Unit tests for the Larpinq admin settings section.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Sections
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Sections;

use OCA\Larpinq\Sections\LarpinqAdmin;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;
use PHPUnit\Framework\TestCase;

/**
 * The admin section that holds the Larpinq settings.
 *
 * @covers \OCA\Larpinq\Sections\LarpinqAdmin
 */
class LarpinqAdminTest extends TestCase {

	/**
	 * The icon is the app's dark icon, resolved through the URL generator.
	 *
	 * @return void
	 */
	public function testIconIsTheAppDarkIcon(): void {
		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with('larpinq', 'app-dark.svg')
			->willReturn('/custom_apps/larpinq/img/app-dark.svg');
		$section = new LarpinqAdmin(l10n: $this->createMock(originalClassName: IL10N::class), urlGenerator: $urlGenerator);

		$this->assertSame(expected: '/custom_apps/larpinq/img/app-dark.svg', actual: $section->getIcon());
	}//end testIconIsTheAppDarkIcon()

	/**
	 * The name is the translated app name.
	 *
	 * @return void
	 */
	public function testNameIsTranslated(): void {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->expects($this->once())->method('t')->with('Larpinq')->willReturn('Larpinq (vertaald)');
		$section = new LarpinqAdmin(l10n: $l10n, urlGenerator: $this->createMock(originalClassName: IURLGenerator::class));

		$this->assertSame(expected: 'Larpinq (vertaald)', actual: $section->getName());
	}//end testNameIsTranslated()

	/**
	 * The section id matches the app id the settings form names, with priority 55.
	 *
	 * @return void
	 */
	public function testIdAndPriority(): void {
		$section = new LarpinqAdmin(
			l10n: $this->createMock(originalClassName: IL10N::class),
			urlGenerator: $this->createMock(originalClassName: IURLGenerator::class)
		);

		$this->assertInstanceOf(expected: IIconSection::class, actual: $section);
		$this->assertSame(expected: 'larpinq', actual: $section->getID());
		$this->assertSame(expected: 55, actual: $section->getPriority());
	}//end testIdAndPriority()
}//end class
