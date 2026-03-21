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
 */
class RegisterObjectFetcherTest extends TestCase
{

    private RegisterObjectFetcher $service;
    private ContainerInterface&MockObject $container;
    private IAppManager&MockObject $appManager;
    private IAppConfig&MockObject $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
        $this->appManager = $this->createMock(IAppManager::class);
        $this->config = $this->createMock(IAppConfig::class);

        $this->service = new RegisterObjectFetcher(
            $this->container,
            $this->appManager,
            $this->config,
        );
    }

    public function testGetObjectsThrowsWhenOpenRegisterNotInstalled(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenRegister app is not installed');

        $this->service->getObjects('character');
    }

    public function testGetObjectsThrowsWhenRegisterNotConfigured(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockObjectService = new \stdClass();
        $this->container->method('get')
            ->with('OCA\OpenRegister\Service\ObjectService')
            ->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default): string {
                return $default; // Returns empty string for all keys.
            });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Register not configured for character');

        $this->service->getObjects('character');
    }

    public function testGetObjectsThrowsWhenSchemaNotConfigured(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockObjectService = new \stdClass();
        $this->container->method('get')
            ->with('OCA\OpenRegister\Service\ObjectService')
            ->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default): string {
                if ($key === 'character_register') {
                    return 'reg-123';
                }
                return $default;
            });

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Schema not configured for character');

        $this->service->getObjects('character');
    }

    public function testGetObjectConvertsUppercaseTypeToLowercase(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        $mockMapper->method('find')->willReturn(['id' => 'obj-1', 'name' => 'Test']);

        $mockObjectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        // Should look up 'skill_register' and 'skill_schema' (lowercase).
        $this->config->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default): string {
                if ($key === 'skill_register') {
                    return 'reg-1';
                }
                if ($key === 'skill_schema') {
                    return 'sch-1';
                }
                return $default;
            });

        // Pass 'Skill' with uppercase S.
        $result = $this->service->getObject('Skill', 'obj-1');

        self::assertSame('obj-1', $result['id']);
        self::assertSame('Test', $result['name']);
    }

    public function testGetObjectCleansUriFormatIds(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        // Expect 'abc-123' after URI cleaning.
        $mockMapper->expects($this->once())
            ->method('find')
            ->with('abc-123')
            ->willReturn(['id' => 'abc-123']);

        $mockObjectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default): string {
                if ($key === 'character_register') {
                    return 'reg-1';
                }
                if ($key === 'character_schema') {
                    return 'sch-1';
                }
                return $default;
            });

        $result = $this->service->getObject('character', 'https://example.com/api/objects/abc-123');

        self::assertSame('abc-123', $result['id']);
    }

    public function testGetObjectsReturnsArrayOfArrays(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockMapper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['findAll'])
            ->getMock();
        $mockMapper->method('findAll')->willReturn([
            ['id' => 'obj-1', 'name' => 'First'],
            ['id' => 'obj-2', 'name' => 'Second'],
        ]);

        $mockObjectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default): string {
                if ($key === 'skill_register') {
                    return 'reg-1';
                }
                if ($key === 'skill_schema') {
                    return 'sch-1';
                }
                return $default;
            });

        $results = $this->service->getObjects('skill');

        self::assertCount(2, $results);
        self::assertSame('obj-1', $results[0]['id']);
        self::assertSame('obj-2', $results[1]['id']);
    }

    public function testGetObjectHandlesJsonSerializableObjects(): void
    {
        $this->appManager->method('getInstalledApps')->willReturn(['openregister']);

        $mockObj = new class implements \JsonSerializable {

            public function jsonSerialize(): array
            {
                return ['id' => 'json-1', 'name' => 'Serializable'];
            }
        };

        $mockMapper = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['find'])
            ->getMock();
        $mockMapper->method('find')->willReturn($mockObj);

        $mockObjectService = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getMapper'])
            ->getMock();
        $mockObjectService->method('getMapper')->willReturn($mockMapper);

        $this->container->method('get')->willReturn($mockObjectService);

        $this->config->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default): string {
                if ($key === 'character_register') {
                    return 'reg-1';
                }
                if ($key === 'character_schema') {
                    return 'sch-1';
                }
                return $default;
            });

        $result = $this->service->getObject('character', 'json-1');

        self::assertSame('json-1', $result['id']);
        self::assertSame('Serializable', $result['name']);
    }
}
