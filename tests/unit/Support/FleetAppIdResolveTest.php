<?php

/**
 * Unit tests for the app-manager side of the cross-app identity resolver.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

use OCA\Larpinq\Support\FleetAppId;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pins how FleetAppId resolves an installed app id, its enabled state, its
 * route path and a class name.
 *
 * @covers \OCA\Larpinq\Support\FleetAppId
 */
final class FleetAppIdResolveTest extends TestCase {

	/**
	 * An app manager on which exactly the given ids are installed.
	 *
	 * @param string[] $installed The installed app ids.
	 *
	 * @return IAppManager&MockObject The app manager.
	 */
	private function managerWith(array $installed): IAppManager {
		$manager = $this->createMock(originalClassName: IAppManager::class);
		$manager->method('isInstalled')->willReturnCallback(
			static fn (string $id): bool => in_array($id, $installed, true)
		);

		return $manager;
	}//end managerWith()

	/**
	 * The new id wins when both the new and the retired id are installed.
	 *
	 * @return void
	 */
	public function testResolvePrefersTheNewId(): void {
		$manager = $this->managerWith(installed: ['filinq', 'docudesk']);

		$this->assertSame(expected: 'filinq', actual: FleetAppId::resolve(appManager: $manager, canonical: 'filinq'));
	}//end testResolvePrefersTheNewId()

	/**
	 * An instance still on the retired id resolves to it.
	 *
	 * @return void
	 */
	public function testResolveFallsBackToTheRetiredId(): void {
		$manager = $this->managerWith(installed: ['docudesk']);

		$this->assertSame(expected: 'docudesk', actual: FleetAppId::resolve(appManager: $manager, canonical: 'filinq'));
		$this->assertTrue(condition: FleetAppId::isInstalled(appManager: $manager, canonical: 'filinq'));
	}//end testResolveFallsBackToTheRetiredId()

	/**
	 * An app outside the map is looked up by its own id only.
	 *
	 * @return void
	 */
	public function testUnknownCanonicalIsLookedUpAsItself(): void {
		$manager = $this->createMock(originalClassName: IAppManager::class);
		$manager->expects($this->once())->method('isInstalled')->with('openregister')->willReturn(true);

		$this->assertSame(expected: 'openregister', actual: FleetAppId::resolve(appManager: $manager, canonical: 'openregister'));
	}//end testUnknownCanonicalIsLookedUpAsItself()

	/**
	 * Nothing installed resolves to null, and isInstalled says false.
	 *
	 * @return void
	 */
	public function testNothingInstalledResolvesToNull(): void {
		$manager = $this->managerWith(installed: []);

		$this->assertNull(actual: FleetAppId::resolve(appManager: $manager, canonical: 'keepiq'));
		$this->assertFalse(condition: FleetAppId::isInstalled(appManager: $manager, canonical: 'keepiq'));
	}//end testNothingInstalledResolvesToNull()

	/**
	 * A candidate whose lookup throws is skipped, and the next one still resolves.
	 *
	 * @return void
	 */
	public function testAThrowingCandidateDoesNotAbortTheSearch(): void {
		$manager = $this->createMock(originalClassName: IAppManager::class);
		$manager->method('isInstalled')->willReturnCallback(
			static function (string $id): bool {
				if ($id === 'integriq') {
					throw new RuntimeException('lookup failed');
				}

				return $id === 'openconnector';
			}
		);

		$this->assertSame(expected: 'openconnector', actual: FleetAppId::resolve(appManager: $manager, canonical: 'integriq'));
	}//end testAThrowingCandidateDoesNotAbortTheSearch()

	/**
	 * The enabled check asks about the resolved id.
	 *
	 * @return void
	 */
	public function testIsEnabledForUserAsksTheResolvedId(): void {
		$manager = $this->managerWith(installed: ['procest']);
		$manager->expects($this->once())->method('isEnabledForUser')->with('procest')->willReturn(true);

		$this->assertTrue(condition: FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'dossiq'));
	}//end testIsEnabledForUserAsksTheResolvedId()

	/**
	 * An app that is not installed is not enabled, and the enabled check is not asked.
	 *
	 * @return void
	 */
	public function testIsEnabledForUserIsFalseWhenNotInstalled(): void {
		$manager = $this->managerWith(installed: []);
		$manager->expects($this->never())->method('isEnabledForUser');

		$this->assertFalse(condition: FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'dossiq'));
	}//end testIsEnabledForUserIsFalseWhenNotInstalled()

	/**
	 * An enabled check that throws counts as not enabled.
	 *
	 * @return void
	 */
	public function testIsEnabledForUserIsFalseWhenTheCheckThrows(): void {
		$manager = $this->managerWith(installed: ['dossiq']);
		$manager->method('isEnabledForUser')->willThrowException(new RuntimeException('no user'));

		$this->assertFalse(condition: FleetAppId::isEnabledForUser(appManager: $manager, canonical: 'dossiq'));
	}//end testIsEnabledForUserIsFalseWhenTheCheckThrows()

	/**
	 * The route path uses the resolved id and joins the suffix with exactly one slash.
	 *
	 * @return void
	 */
	public function testAppPathUsesTheResolvedIdAndSuffix(): void {
		$manager = $this->managerWith(installed: ['scholiq']);

		$this->assertSame(expected: '/apps/scholiq', actual: FleetAppId::appPath(appManager: $manager, canonical: 'learniq'));
		$this->assertSame(
			expected: '/apps/scholiq/courses/7',
			actual: FleetAppId::appPath(appManager: $manager, canonical: 'learniq', suffix: '//courses/7')
		);
		$this->assertSame(
			expected: '/apps/scholiq/courses',
			actual: FleetAppId::appPath(appManager: $manager, canonical: 'learniq', suffix: 'courses')
		);
	}//end testAppPathUsesTheResolvedIdAndSuffix()

	/**
	 * Without an installed app there is no route path.
	 *
	 * @return void
	 */
	public function testAppPathIsNullWhenNotInstalled(): void {
		$this->assertNull(actual: FleetAppId::appPath(appManager: $this->managerWith(installed: []), canonical: 'learniq', suffix: 'x'));
	}//end testAppPathIsNullWhenNotInstalled()

	/**
	 * An existing class under the newest namespace resolves to that class name.
	 *
	 * @return void
	 */
	public function testResolveClassReturnsAnExistingClass(): void {
		$this->assertSame(
			expected: FleetAppId::class,
			actual: FleetAppId::resolveClass(canonical: 'larpinq', relative: '\\Support\\FleetAppId')
		);
	}//end testResolveClassReturnsAnExistingClass()

	/**
	 * An unknown app or a missing class resolves to null.
	 *
	 * @return void
	 */
	public function testResolveClassIsNullWhenNothingExists(): void {
		$this->assertNull(actual: FleetAppId::resolveClass(canonical: 'larpinq', relative: 'Support\\DoesNotExist'));
		$this->assertNull(actual: FleetAppId::resolveClass(canonical: 'unknownapp', relative: 'Support\\FleetAppId'));
	}//end testResolveClassIsNullWhenNothingExists()
}//end class
