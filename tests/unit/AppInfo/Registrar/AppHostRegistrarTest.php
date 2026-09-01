<?php

/**
 * Unit tests for AppHostRegistrar.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\AppInfo\Registrar
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\AppInfo\Registrar;

use OCA\Larpinq\AppInfo\Registrar\AppHostRegistrar;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AppHostRegistrar.
 *
 * The options this class passes are not decoration: each one exists because its
 * absence produced a specific, silent failure. They are asserted here so a later
 * "tidy-up" that drops one fails a test instead of an app-enable in CI.
 *
 * @covers \OCA\Larpinq\AppInfo\Registrar\AppHostRegistrar
 */
class AppHostRegistrarTest extends TestCase {

	private AppHostRegistrar $registrar;

	protected function setUp(): void {
		parent::setUp();
		$this->registrar = new AppHostRegistrar();
	}//end setUp()

	/**
	 * The guard is the whole reason this is safe to call on every request.
	 *
	 * `Application::register()` runs on EVERY request. An unguarded static call
	 * to a class in another app fatals the entire instance-wide request when that
	 * app is absent, not merely Larpinq's AppHost features.
	 *
	 * ⚠️ Both worlds are asserted, because this suite runs in both. The bare unit
	 * job has no openregister; the CI matrix installs it as an additional app. A
	 * test that simply asserted absence would pass locally and fail on CI, and one
	 * that skipped when it was present would stop checking the branch that
	 * actually ships.
	 *
	 * @return void
	 */
	public function testGuardsTheStaticCallOnOpenRegistersPresence(): void {
		$context = $this->createMock(IRegistrationContext::class);
		$present = class_exists(AppHostRegistrar::BOOTSTRAP);

		if ($present === false) {
			// Absent: decline quietly, and touch nothing on the context.
			$context->expects($this->never())->method('registerService');
			$context->expects($this->never())->method('registerEventListener');

			$this->assertFalse(
				$this->registrar->register(context: $context),
				'with openregister absent, register() must report that it wired nothing',
			);
			return;
		}

		// Present: the engine is wired and the call reports success. Nothing is
		// asserted about WHICH ids it registers — that is OpenRegister's contract,
		// not Larpinq's, and pinning it here would break on every AppHost change.
		$this->assertTrue(
			$this->registrar->register(context: $context),
			'with openregister present, register() must report that it wired the engine',
		);
	}//end testGuardsTheStaticCallOnOpenRegistersPresence()

	/**
	 * The service namespace MUST NOT be Larpinq's real one.
	 *
	 * AppHost's `registerServices()` claims `<serviceNs>\SettingsService`
	 * unconditionally — unlike the controller aliases there is no "unless the
	 * leaf defines it" guard. Pointed at `OCA\Larpinq\Service` it replaces
	 * Larpinq's own SettingsService with the AppHost generic, and
	 * `Repair\InitializeRegister::__construct()`, which type-hints the Larpinq
	 * class, dies with a TypeError at app-enable time. That took out all six
	 * PHPUnit cells and Newman when this call was first added.
	 *
	 * @return void
	 */
	public function testParksTheAppHostServicesOutsideTheAppsOwnNamespace(): void {
		$serviceNs = AppHostRegistrar::OPTIONS['serviceNamespace'];

		$this->assertNotSame(
			'OCA\\Larpinq\\Service',
			$serviceNs,
			'AppHost would take over OCA\\Larpinq\\Service\\SettingsService and break InitializeRegister',
		);
		$this->assertStringStartsWith(
			'OCA\\Larpinq\\',
			$serviceNs,
			'the parking namespace must still belong to this app',
		);
		$this->assertFalse(
			class_exists($serviceNs . '\\SettingsService'),
			'the parking namespace must hold no real class, or the alias would shadow it',
		);
	}//end testParksTheAppHostServicesOutsideTheAppsOwnNamespace()

	/**
	 * Deep links stay off because Larpinq registers its own listener.
	 *
	 * AppHost binds the same event under the same class name, so leaving this on
	 * registers `DeepLinkRegistrationListener` twice.
	 *
	 * @return void
	 */
	public function testLeavesDeepLinkRegistrationToTheApp(): void {
		$this->assertFalse(
			AppHostRegistrar::OPTIONS['deepLinks'],
			'Larpinq registers its own DeepLinkRegistrationListener in Application::register()',
		);
		$this->assertTrue(
			class_exists('OCA\\Larpinq\\Listener\\DeepLinkRegistrationListener'),
			'…and that listener must exist, otherwise turning AppHost deep links off loses the feature',
		);
	}//end testLeavesDeepLinkRegistrationToTheApp()

	/**
	 * The namespace must be passed explicitly.
	 *
	 * AppHost falls back to a StudlyCase guess from the app id when it is
	 * omitted. `larpinq` guesses to `Larpinq`, which happens to be right today,
	 * so an omission would not fail here — it would fail the next time the app id
	 * and the namespace stop agreeing, which is exactly what the fleet rename did
	 * to other apps.
	 *
	 * @return void
	 */
	public function testStatesTheNamespaceRatherThanLettingItBeGuessed(): void {
		$this->assertSame('OCA\\Larpinq', AppHostRegistrar::OPTIONS['namespace']);
		$this->assertTrue(
			class_exists(AppHostRegistrar::OPTIONS['namespace'] . '\\AppInfo\\Application'),
			'the declared namespace must be the one this app actually uses',
		);
	}//end testStatesTheNamespaceRatherThanLettingItBeGuessed()
}//end class
