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

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\AppInfo\Registrar\AppHostRegistrar;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;

/**
 * Records what the registrar hands to AppHost, so the test can assert the
 * scoping options actually reach it rather than merely that a call was made.
 */
final class BootstrapSpy {

	/** @var array<int, array<string, mixed>> */
	public static array $calls = [];

	/**
	 * Record one registration call.
	 *
	 * @param string               $appId   The leaf app id.
	 * @param array<string, mixed> $options The scoping options.
	 *
	 * @return void
	 */
	public static function record(string $appId, array $options): void {
		self::$calls[] = ['appId' => $appId, 'options' => $options];
	}//end record()
}//end class

/**
 * A registrar whose AppHost is loadable and records the call.
 *
 * The seams are overridden rather than the entry point being injected, because
 * the production call site has to stay a literal `Bootstrap::register(...)` —
 * hydra-gate-14 greps for exactly that string to know these routes are bound.
 */
final class SpyingRegistrar extends AppHostRegistrar {

	/**
	 * Pretend openregister is installed.
	 *
	 * @return bool Always true.
	 */
	protected function appHostIsLoadable(): bool {
		return true;
	}//end appHostIsLoadable()

	/**
	 * Record instead of calling the real engine.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	protected function callAppHost(IRegistrationContext $context): void {
		BootstrapSpy::record(appId: Application::APP_ID, options: self::OPTIONS);
	}//end callAppHost()
}//end class

/**
 * A registrar whose AppHost is present but throws — openregister installed yet
 * unloadable, which is the case the try/catch exists for.
 */
final class ThrowingRegistrar extends AppHostRegistrar {

	/**
	 * Pretend openregister is installed.
	 *
	 * @return bool Always true.
	 */
	protected function appHostIsLoadable(): bool {
		return true;
	}//end appHostIsLoadable()

	/**
	 * Fail the way an unloadable AppHost would.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	protected function callAppHost(IRegistrationContext $context): void {
		throw new \RuntimeException('AppHost present but unloadable');
	}//end callAppHost()
}//end class

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
	/**
	 * The engine is wired, with the options this app actually means to pass.
	 *
	 * Asserting only "it returned true" would pass just as happily if the
	 * options were dropped on the way, which is the failure this whole class
	 * exists to prevent.
	 *
	 * @return void
	 */
	public function testWiresTheEngineAndForwardsTheScopingOptions(): void {
		BootstrapSpy::$calls = [];

		$registrar = new SpyingRegistrar();

		$this->assertTrue(
			$registrar->register(context: $this->createMock(IRegistrationContext::class)),
		);

		$this->assertCount(1, BootstrapSpy::$calls, 'the engine must be registered exactly once');
		$this->assertSame('larpinq', BootstrapSpy::$calls[0]['appId']);
		$this->assertSame(
			AppHostRegistrar::OPTIONS,
			BootstrapSpy::$calls[0]['options'],
			'the scoping options must reach AppHost unchanged',
		);
	}//end testWiresTheEngineAndForwardsTheScopingOptions()

	/**
	 * A throwing AppHost must not take the request down with it.
	 *
	 * This runs inside Application::register(), on every request. An escaping
	 * exception here fatals the whole instance-wide request, so the app must lose
	 * its AppHost features and keep serving.
	 *
	 * @return void
	 */
	public function testSwallowsAnUnloadableAppHostRatherThanFatalingTheRequest(): void {
		$registrar = new ThrowingRegistrar();

		$this->assertFalse(
			$registrar->register(context: $this->createMock(IRegistrationContext::class)),
			'a throwing AppHost must be reported as "not wired", not re-thrown',
		);
	}//end testSwallowsAnUnloadableAppHostRatherThanFatalingTheRequest()
}//end class
