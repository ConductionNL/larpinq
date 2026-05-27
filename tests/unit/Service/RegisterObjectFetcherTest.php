<?php

/**
 * Unit tests for RegisterObjectFetcher.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use Exception;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Tests for RegisterObjectFetcher service.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class RegisterObjectFetcherTest extends TestCase
{

    /**
     * The service under test.
     *
     * @var RegisterObjectFetcher
     */
    private RegisterObjectFetcher $service;

    /**
     * DI container mock.
     *
     * @var ContainerInterface&MockObject
     */
    private ContainerInterface&MockObject $container;

    /**
     * App manager mock.
     *
     * @var IAppManager&MockObject
     */
    private IAppManager&MockObject $appManager;

    /**
     * App config mock.
     *
     * @var IAppConfig&MockObject
     */
    private IAppConfig&MockObject $config;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container  = $this->createMock(originalClassName: ContainerInterface::class);
        $this->appManager = $this->createMock(originalClassName: IAppManager::class);
        $this->config     = $this->createMock(originalClassName: IAppConfig::class);

        $this->service = new RegisterObjectFetcher(
            container: $this->container,
            appManager: $this->appManager,
            config: $this->config,
        );

    }//end setUp()

    /**
     * Test that getObjects throws when OpenRegister is not installed.
     *
     * @return void
     */
    public function testGetObjectsThrowsWhenOpenRegisterNotInstalled(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn([]);

        $this->expectException(exception: Exception::class);
        $this->expectExceptionMessage(message: 'OpenRegister app is not installed');

        $this->service->getObjects('character');

    }//end testGetObjectsThrowsWhenOpenRegisterNotInstalled()

    /**
     * Test that getObjects throws when the register is not configured.
     *
     * @return void
     */
    public function testGetObjectsThrowsWhenRegisterNotConfigured(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockObjectService = new \stdClass();
        $this->container->method('get')
            ->with('OCA\OpenRegister\Service\ObjectService')
            ->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    // Returns empty string for all keys.
                    return $default;
                }
            );

        $this->expectException(exception: Exception::class);
        $this->expectExceptionMessage(message: 'Register not configured for character');

        $this->service->getObjects('character');

    }//end testGetObjectsThrowsWhenRegisterNotConfigured()

    /**
     * Test that getObjects throws when the schema is not configured.
     *
     * @return void
     */
    public function testGetObjectsThrowsWhenSchemaNotConfigured(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockObjectService = new \stdClass();
        $this->container->method('get')
            ->with('OCA\OpenRegister\Service\ObjectService')
            ->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    if ($key === 'character_register') {
                        return 'reg-123';
                    }

                    return $default;
                }
            );

        $this->expectException(exception: Exception::class);
        $this->expectExceptionMessage(message: 'Schema not configured for character');

        $this->service->getObjects('character');

    }//end testGetObjectsThrowsWhenSchemaNotConfigured()

    /**
     * Test that getObject converts an uppercase object type to lowercase.
     *
     * @return void
     */
    public function testGetObjectConvertsUppercaseTypeToLowercase(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        $mockMapper->method('find')->willReturn(['id' => 'obj-1', 'name' => 'Test']);

        $mockObjectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        // Should look up 'skill_register' and 'skill_schema' (lowercase).
        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    if ($key === 'skill_register') {
                        return 'reg-1';
                    }

                    if ($key === 'skill_schema') {
                        return 'sch-1';
                    }

                    return $default;
                }
            );

        // Pass 'Skill' with uppercase S.
        $result = $this->service->getObject('Skill', 'obj-1');

        self::assertSame(expected: 'obj-1', actual: $result['id']);
        self::assertSame(expected: 'Test', actual: $result['name']);

    }//end testGetObjectConvertsUppercaseTypeToLowercase()

    /**
     * Test that getObject cleans URI-format IDs.
     *
     * @return void
     */
    public function testGetObjectCleansUriFormatIds(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['find'])
            ->getMock();

        // Expect 'abc-123' after URI cleaning.
        $mockMapper->expects($this->once())
            ->method('find')
            ->with('abc-123')
            ->willReturn(['id' => 'abc-123']);

        $mockObjectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    if ($key === 'character_register') {
                        return 'reg-1';
                    }

                    if ($key === 'character_schema') {
                        return 'sch-1';
                    }

                    return $default;
                }
            );

        $result = $this->service->getObject('character', 'https://example.com/api/objects/abc-123');

        self::assertSame(expected: 'abc-123', actual: $result['id']);

    }//end testGetObjectCleansUriFormatIds()

    /**
     * Test that getObjects returns arrays of arrays.
     *
     * @return void
     */
    public function testGetObjectsReturnsArrayOfArrays(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['findAll'])
            ->getMock();
        $mockMapper->method('findAll')->willReturn(
            [
                ['id' => 'obj-1', 'name' => 'First'],
                ['id' => 'obj-2', 'name' => 'Second'],
            ]
        );

        $mockObjectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    if ($key === 'skill_register') {
                        return 'reg-1';
                    }

                    if ($key === 'skill_schema') {
                        return 'sch-1';
                    }

                    return $default;
                }
            );

        $results = $this->service->getObjects('skill');

        self::assertCount(expectedCount: 2, haystack: $results);
        self::assertSame(expected: 'obj-1', actual: $results[0]['id']);
        self::assertSame(expected: 'obj-2', actual: $results[1]['id']);

    }//end testGetObjectsReturnsArrayOfArrays()

    /**
     * Test that getObjects passes a config array as the first argument to findAll.
     *
     * Regression guard for issue #204: the prior code passed positional scalar args
     * ($limit, $offset, ...) which mis-mapped to OpenRegister's findAll(array $config, ...)
     * signature, causing a TypeError on every request.
     *
     * @return void
     */
    public function testGetObjectsPassesConfigArrayToFindAll(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['findAll'])
            ->getMock();

        // Verify that findAll is called with a config array as the first positional argument,
        // not with positional scalar/null args (which would fail against OR's real signature).
        $mockMapper->expects($this->once())
            ->method('findAll')
            ->with(
                $this->callback(
                    callback: function (mixed $firstArg): bool {
                        return is_array($firstArg)
                            && array_key_exists('limit', $firstArg)
                            && array_key_exists('offset', $firstArg)
                            && array_key_exists('filters', $firstArg)
                            && array_key_exists('sort', $firstArg)
                            && array_key_exists('search', $firstArg);
                    }
                )
            )
            ->willReturn([]);

        $mockObjectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    if ($key === 'character_register') {
                        return 'reg-1';
                    }

                    if ($key === 'character_schema') {
                        return 'sch-1';
                    }

                    return $default;
                }
            );

        $results = $this->service->getObjects('character');

        self::assertSame(expected: [], actual: $results);

    }//end testGetObjectsPassesConfigArrayToFindAll()

    /**
     * Test that getObject handles JSON-serializable objects.
     *
     * @return void
     */
    public function testGetObjectHandlesJsonSerializableObjects(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockObj = new class implements \JsonSerializable {
            /**
             * Serialize the object to JSON.
             *
             * @return array<string,string>
             */
            public function jsonSerialize(): array
            {
                return ['id' => 'json-1', 'name' => 'Serializable'];
            }//end jsonSerialize()
        };

        $mockMapper = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        $mockMapper->method('find')->willReturn($mockObj);

        $mockObjectService = $this->getMockBuilder(className: \stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(
                function (string $app, string $key, string $default): string {
                    if ($key === 'character_register') {
                        return 'reg-1';
                    }

                    if ($key === 'character_schema') {
                        return 'sch-1';
                    }

                    return $default;
                }
            );

        $result = $this->service->getObject('character', 'json-1');

        self::assertSame(expected: 'json-1', actual: $result['id']);
        self::assertSame(expected: 'Serializable', actual: $result['name']);

    }//end testGetObjectHandlesJsonSerializableObjects()
}//end class
