<?php

/**
 * Tests for the OpenRegister autoload prelude.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\AppInfo;

use OCA\LarpingApp\AppInfo\OpenRegisterAutoloader;
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
class OpenRegisterAutoloaderTest extends TestCase
{
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
    public function testRegisterNeverThrows(): void
    {
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
     * `OC_App::registerAutoloading()` early-returns on an `$alreadyRegistered`
     * key, so a second call is a no-op. `Application::register()` may run more
     * than once in a single process, and a prelude that failed or threw on the
     * second call would be a latent bootstrap defect.
     *
     * @return void
     */
    public function testRegisterIsIdempotent(): void
    {
        OpenRegisterAutoloader::register();
        $afterFirst = count(spl_autoload_functions());

        OpenRegisterAutoloader::register();
        $afterSecond = count(spl_autoload_functions());

        $this->assertSame(
            expected: $afterFirst,
            actual: $afterSecond,
            message: 'A second call must not stack another autoloader — '
                .'OC_App::registerAutoloading() early-returns on an '
                .'$alreadyRegistered key, so the prelude is free to repeat.'
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
    public function testRegisterSwallowsAnAppThatCannotResolve(): void
    {
        $before = count(spl_autoload_functions());

        OpenRegisterAutoloader::register('an-app-that-is-not-installed');

        $this->assertSame(
            expected: $before,
            actual: count(spl_autoload_functions()),
            message: 'A prelude whose app cannot be resolved must leave the '
                .'autoloader untouched and must not rethrow.'
        );

    }//end testRegisterSwallowsAnAppThatCannotResolve()
}//end class
