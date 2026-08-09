<?php

/**
 * Wire-contract tests for the per-user preferences endpoints.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2024 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Controller;

use OCA\LarpingApp\AppInfo\Application;
use OCA\LarpingApp\Controller\PreferencesController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * `GET /api/preferences/{key}` (`preferences#getPreference`) and
 * `PUT /api/preferences/{key}` (`preferences#setPreference`) are public
 * endpoints consumed by shared `@conduction/nextcloud-vue` widgets across
 * apps — `CnSupportDialog`'s "seen" flag is the current caller. Their wire
 * contract is therefore not private to this app, and hydra gate-25 reported
 * both as new public endpoints with no contract test:
 *
 *     [gate-25] contract-coverage: FAIL — 2 new public endpoint(s) missing a contract test
 *     preferences#getPreference — new public endpoint (url=/api/preferences/{key})
 *     preferences#setPreference — new public endpoint (url=/api/preferences/{key})
 *
 * These assert the ITEM, not the container: the exact status code AND the exact
 * body for every branch, and — for the write — the exact `IConfig` call the
 * controller must make. A test that only checked "a JSONResponse came back", or
 * only that the status was 200, would pass against a controller that stored
 * nothing, stored under the wrong key, or leaked a value across users.
 *
 * The key-sanitisation branch is a security boundary, not a formatting nicety:
 * every key is namespaced to `pref_<sanitised>` precisely so a caller cannot
 * reach arbitrary `IConfig` user values belonging to this app. That is asserted
 * on the value actually handed to `IConfig`.
 *
 * @spec openspec/changes/retrofit-2026-05-26-preferences-api/tasks.md#task-1
 * @spec openspec/changes/retrofit-2026-05-26-preferences-api/tasks.md#task-2
 */
class PreferencesControllerContractTest extends TestCase
{

    /**
     * Mock IRequest.
     *
     * @var IRequest&MockObject
     */
    private IRequest&MockObject $request;

    /**
     * Mock IConfig.
     *
     * @var IConfig&MockObject
     */
    private IConfig&MockObject $config;

    /**
     * Mock IUserSession.
     *
     * @var IUserSession&MockObject
     */
    private IUserSession&MockObject $userSession;


    /**
     * Build the mocks shared by every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request     = $this->createMock(originalClassName: IRequest::class);
        $this->config      = $this->createMock(originalClassName: IConfig::class);
        $this->userSession = $this->createMock(originalClassName: IUserSession::class);

    }//end setUp()


    /**
     * Put a logged-in user on the session.
     *
     * @param string $uid The user id to report.
     *
     * @return void
     */
    private function signIn(string $uid='alice'): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);

    }//end signIn()


    /**
     * Build the controller under test.
     *
     * @return PreferencesController The controller.
     */
    private function controller(): PreferencesController
    {
        return new PreferencesController(
            request: $this->request,
            config: $this->config,
            userSession: $this->userSession
        );

    }//end controller()


    /**
     * Read the (status, data) pair off a response.
     *
     * @param JSONResponse $response The response under test.
     *
     * @return array{0: int, 1: mixed} Status code and decoded data.
     */
    private function wire(JSONResponse $response): array
    {
        return [$response->getStatus(), $response->getData()];

    }//end wire()


    /**
     * An anonymous read is 401 with a message, and must never touch IConfig.
     *
     * @return void
     */
    public function testGetPreferenceRejectsAnonymousCallers(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->config->expects($this->never())->method('getUserValue');

        [$status, $data] = $this->wire($this->controller()->getPreference(key: 'support-seen'));

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $status);
        $this->assertSame(['message' => 'Not logged in'], $data);

    }//end testGetPreferenceRejectsAnonymousCallers()


    /**
     * A key that sanitises to nothing is 400, and must never touch IConfig.
     *
     * @return void
     */
    public function testGetPreferenceRejectsAKeyThatSanitisesToNothing(): void
    {
        $this->signIn();
        $this->config->expects($this->never())->method('getUserValue');

        [$status, $data] = $this->wire($this->controller()->getPreference(key: '///'));

        $this->assertSame(Http::STATUS_BAD_REQUEST, $status);
        $this->assertSame(['message' => 'Invalid key'], $data);

    }//end testGetPreferenceRejectsAKeyThatSanitisesToNothing()


    /**
     * A stored value comes back as `{value: <stored>}` with HTTP 200, read
     * from this user's own `pref_`-namespaced key.
     *
     * @return void
     */
    public function testGetPreferenceReturnsTheStoredValueForThisUser(): void
    {
        $this->signIn(uid: 'alice');
        $this->config->expects($this->once())
            ->method('getUserValue')
            ->with('alice', Application::APP_ID, 'pref_support-seen', '')
            ->willReturn('2026-08-09');

        [$status, $data] = $this->wire($this->controller()->getPreference(key: 'support-seen'));

        $this->assertSame(Http::STATUS_OK, $status);
        $this->assertSame(['value' => '2026-08-09'], $data);

    }//end testGetPreferenceReturnsTheStoredValueForThisUser()


    /**
     * An unset preference is `{value: null}` — NOT an empty string. The widgets
     * that consume this distinguish "never set" from "set to empty".
     *
     * @return void
     */
    public function testGetPreferenceReportsAnUnsetKeyAsNull(): void
    {
        $this->signIn();
        $this->config->method('getUserValue')->willReturn('');

        [$status, $data] = $this->wire($this->controller()->getPreference(key: 'support-seen'));

        $this->assertSame(Http::STATUS_OK, $status);
        $this->assertSame(['value' => null], $data);

    }//end testGetPreferenceReportsAnUnsetKeyAsNull()


    /**
     * Keys are sanitised and namespaced before they reach IConfig, so a caller
     * cannot address a config key outside the `pref_` space.
     *
     * @return void
     */
    public function testGetPreferenceNamespacesAndSanitisesTheKey(): void
    {
        $this->signIn(uid: 'alice');
        $this->config->expects($this->once())
            ->method('getUserValue')
            ->with('alice', Application::APP_ID, 'pref_userlang', '')
            ->willReturn('');

        $this->controller()->getPreference(key: '../USER_lang!');

    }//end testGetPreferenceNamespacesAndSanitisesTheKey()


    /**
     * An anonymous write is 401 and must not reach IConfig at all.
     *
     * @return void
     */
    public function testSetPreferenceRejectsAnonymousCallers(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->config->expects($this->never())->method('setUserValue');
        $this->config->expects($this->never())->method('deleteUserValue');

        [$status, $data] = $this->wire(
            $this->controller()->setPreference(key: 'support-seen', value: 'x')
        );

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $status);
        $this->assertSame(['message' => 'Not logged in'], $data);

    }//end testSetPreferenceRejectsAnonymousCallers()


    /**
     * A key that sanitises to nothing is 400 and must not reach IConfig.
     *
     * @return void
     */
    public function testSetPreferenceRejectsAKeyThatSanitisesToNothing(): void
    {
        $this->signIn();
        $this->config->expects($this->never())->method('setUserValue');
        $this->config->expects($this->never())->method('deleteUserValue');

        [$status, $data] = $this->wire($this->controller()->setPreference(key: '@@@', value: 'x'));

        $this->assertSame(Http::STATUS_BAD_REQUEST, $status);
        $this->assertSame(['message' => 'Invalid key'], $data);

    }//end testSetPreferenceRejectsAKeyThatSanitisesToNothing()


    /**
     * A write stores the value under this user's namespaced key and echoes it.
     *
     * @return void
     */
    public function testSetPreferenceStoresTheValueAndEchoesIt(): void
    {
        $this->signIn(uid: 'alice');
        $this->config->expects($this->once())
            ->method('setUserValue')
            ->with('alice', Application::APP_ID, 'pref_support-seen', '2026-08-09');
        $this->config->expects($this->never())->method('deleteUserValue');

        [$status, $data] = $this->wire(
            $this->controller()->setPreference(key: 'support-seen', value: '2026-08-09')
        );

        $this->assertSame(Http::STATUS_OK, $status);
        $this->assertSame(['value' => '2026-08-09'], $data);

    }//end testSetPreferenceStoresTheValueAndEchoesIt()


    /**
     * An empty value CLEARS the preference — it must delete, not store an empty
     * string, or the next read could not tell "cleared" from "set to empty".
     *
     * @return void
     */
    public function testSetPreferenceWithAnEmptyValueDeletesTheKey(): void
    {
        $this->signIn(uid: 'alice');
        $this->config->expects($this->once())
            ->method('deleteUserValue')
            ->with('alice', Application::APP_ID, 'pref_support-seen');
        $this->config->expects($this->never())->method('setUserValue');

        [$status, $data] = $this->wire(
            $this->controller()->setPreference(key: 'support-seen', value: '')
        );

        $this->assertSame(Http::STATUS_OK, $status);
        $this->assertSame(['value' => null], $data);

    }//end testSetPreferenceWithAnEmptyValueDeletesTheKey()


    /**
     * The write path namespaces and sanitises the key exactly as the read path
     * does — otherwise a value written under one spelling is unreadable.
     *
     * @return void
     */
    public function testSetPreferenceNamespacesAndSanitisesTheKey(): void
    {
        $this->signIn(uid: 'alice');
        $this->config->expects($this->once())
            ->method('setUserValue')
            ->with('alice', Application::APP_ID, 'pref_userlang', 'nl');

        $this->controller()->setPreference(key: '../USER_lang!', value: 'nl');

    }//end testSetPreferenceNamespacesAndSanitisesTheKey()


}//end class
