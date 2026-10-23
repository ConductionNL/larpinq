<?php

/**
 * Tests for the OpenRegister autoload prelude.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\AppInfo;

use OCA\Larpinq\AppInfo\OpenRegisterAutoloader;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * The prelude's whole purpose is that it CANNOT take down the caller.
 *
 * It runs first in `Application::register()`, ahead of the `class_exists()`
 * guards that decide whether the deep-link listener and the server-authoritative
 * skill-requirement / XP-budget listeners get registered. An exception escaping
 * it would abort that whole method and leave every listener unregistered — the
 * exact failure the prelude exists to prevent — so "never throws" is the
 * contract under test, on ANY instance, with OpenRegister present or absent.
 */
class OpenRegisterAutoloaderTest extends TestCase {

	/**
	 * Temporary fake openregister app directory.
	 *
	 * @var string
	 */
	private string $appPath;

	/**
	 * Create a fake openregister app with one class under lib/.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appPath = sys_get_temp_dir().'/larpinq-or-'.bin2hex(random_bytes(4));
		mkdir($this->appPath.'/lib/Fake', 0777, true);
		file_put_contents(
			$this->appPath.'/lib/Fake/Probe.php',
			"<?php\nnamespace OCA\\OpenRegister\\Fake;\nfinal class Probe {}\n"
		);

	}//end setUp()

	/**
	 * Remove the loader and the fake app.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		OpenRegisterAutoloader::unregister();
		@unlink($this->appPath.'/lib/Fake/Probe.php');
		@rmdir($this->appPath.'/lib/Fake');
		@rmdir($this->appPath.'/lib');
		@rmdir($this->appPath);
		parent::tearDown();

	}//end tearDown()

	/**
	 * The prelude must never throw, whatever the instance looks like.
	 *
	 * This runs in both environments the suite is executed in: with Nextcloud
	 * booted (where OpenRegister may or may not be installed) and with only the
	 * OCP stubs registered (where `\OCP\Server::get()` cannot resolve
	 * anything). Both must be swallowed.
	 *
	 * @return void
	 */
	public function testRegisterNeverThrows(): void {
		$before = count(spl_autoload_functions());

		OpenRegisterAutoloader::register();

		// Reaching this line at all IS the assertion: the contract is that the
		// prelude returns control to its caller under every instance state. A
		// Throwable escaping it would fail the test here, and in production
		// would abort the whole of Application::register().
		$this->assertGreaterThan(
			expected: 0,
			actual: $before,
			message: 'The prelude must return control to its caller, never throw.'
		);

	}//end testRegisterNeverThrows()

	/**
	 * Calling the prelude twice must be free and must agree with itself.
	 *
	 * The prelude short-circuits once its loader is on the SPL chain, so a
	 * second call is a no-op. `Application::register()` may run more
	 * than once in a single process, and a prelude that failed or threw on the
	 * second call would be a latent bootstrap defect.
	 *
	 * @return void
	 */
	public function testRegisterIsIdempotent(): void {
		OpenRegisterAutoloader::register();
		$afterFirst = count(spl_autoload_functions());

		OpenRegisterAutoloader::register();
		$afterSecond = count(spl_autoload_functions());

		$this->assertSame(
			expected: $afterFirst,
			actual: $afterSecond,
			message: 'A second call must not stack another autoloader — '
				. 'the prelude short-circuits once its loader is registered, '
				. 'so it is free to repeat.'
		);

	}//end testRegisterIsIdempotent()

	/**
	 * The degraded path must be swallowed, not rethrown.
	 *
	 * In production this is `openregister` on an instance where it is not
	 * installed: `IAppManager::getAppPath()` throws `AppPathNotFoundException`.
	 * The prelude MUST absorb it — a Throwable escaping here would abort the
	 * caller's entire `register()`, which is the failure the prelude exists to
	 * prevent, and it would abort it on EVERY request.
	 *
	 * The app id is a parameter for exactly this reason. Every instance this
	 * suite runs on HAS OpenRegister installed, so without an id that cannot
	 * resolve, this branch is dead code that no test can reach — and a branch
	 * no test can reach is a branch no one has ever checked.
	 *
	 * @return void
	 */
	public function testRegisterSwallowsAnAppThatCannotResolve(): void {
		$before = count(spl_autoload_functions());

		OpenRegisterAutoloader::register('an-app-that-is-not-installed');

		$this->assertSame(
			expected: $before,
			actual: count(spl_autoload_functions()),
			message: 'A prelude whose app cannot be resolved must leave the '
				. 'autoloader untouched and must not rethrow.'
		);

	}//end testRegisterSwallowsAnAppThatCannotResolve()

	/**
	 * Nextcloud 35 removed `OC_App::registerAutoloading()`; the prelude may only
	 * use public API and plain PHP.
	 *
	 * @return void
	 */
	public function testSourceUsesNoPrivateOcAppApi(): void {
		$source = (string) file_get_contents(__DIR__.'/../../../lib/AppInfo/OpenRegisterAutoloader.php');
		$code   = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);
		$this->assertStringNotContainsString('OC_App', $code);

	}//end testSourceUsesNoPrivateOcAppApi()

	/**
	 * With OpenRegister enabled, its PSR-4 prefix resolves after the prelude.
	 *
	 * @return void
	 */
	public function testRegistersPsr4PrefixWhenEnabled(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->with('openregister')->willReturn(true);
		$appManager->method('getAppPath')->with('openregister')->willReturn($this->appPath.'/');

		OpenRegisterAutoloader::register(appManager: $appManager);
		$afterFirst = count(spl_autoload_functions());
		OpenRegisterAutoloader::register(appManager: $appManager);

		$this->assertSame($afterFirst, count(spl_autoload_functions()));
		$this->assertTrue(class_exists('OCA\\OpenRegister\\Fake\\Probe'));

	}//end testRegistersPsr4PrefixWhenEnabled()

	/**
	 * A disabled OpenRegister is not wired, and its path is never resolved.
	 *
	 * @return void
	 */
	public function testDoesNothingWhenDisabled(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(false);
		$appManager->expects($this->never())->method('getAppPath');

		$before = count(spl_autoload_functions());
		OpenRegisterAutoloader::register(appManager: $appManager);

		$this->assertSame($before, count(spl_autoload_functions()));

	}//end testDoesNothingWhenDisabled()

	/**
	 * An app manager that throws is swallowed.
	 *
	 * @return void
	 */
	public function testNeverThrowsWhenAppManagerThrows(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willThrowException(new \RuntimeException('boom'));

		$before = count(spl_autoload_functions());
		OpenRegisterAutoloader::register(appManager: $appManager);

		$this->assertSame($before, count(spl_autoload_functions()));

	}//end testNeverThrowsWhenAppManagerThrows()

	/**
	 * The loader only answers for OpenRegister's own classes.
	 *
	 * @return void
	 */
	public function testClassFileOnlyAnswersForOpenRegister(): void {
		$this->assertSame(
			'/x/lib/Db/Schema.php',
			OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\OpenRegister\\Db\\Schema')
		);
		$this->assertNull(OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\Larpinq\\Foo'));
		$this->assertNull(OpenRegisterAutoloader::classFile(appPath: '/x', class: 'OCA\\OpenRegister\\'));

	}//end testClassFileOnlyAnswersForOpenRegister()
}//end class
