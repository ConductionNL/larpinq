<?php

/**
 * Unit tests for ConfigFileLoaderService.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\ConfigFileLoaderService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for ConfigFileLoaderService.
 */
class ConfigFileLoaderServiceTest extends TestCase
{

    private ConfigFileLoaderService $service;
    private IAppManager $appManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appManager = $this->createMock(IAppManager::class);
        $this->service = new ConfigFileLoaderService($this->appManager);
    }

    public function testLoadConfigurationFileThrowsWhenFileNotFound(): void
    {
        $this->appManager->method('getAppPath')
            ->with('larpingapp')
            ->willReturn('/nonexistent/path');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $this->service->loadConfigurationFile();
    }

    public function testEnsureSourceTypeSetsDefaultSourceType(): void
    {
        $data = [];

        $result = $this->service->ensureSourceType($data);

        self::assertArrayHasKey('x-openregister', $result);
        self::assertSame('local', $result['x-openregister']['sourceType']);
    }

    public function testEnsureSourceTypePreservesExistingSourceType(): void
    {
        $data = [
            'x-openregister' => [
                'sourceType' => 'remote',
            ],
        ];

        $result = $this->service->ensureSourceType($data);

        self::assertSame('remote', $result['x-openregister']['sourceType']);
    }

    public function testEnsureSourceTypeHandlesNonArrayOpenregister(): void
    {
        $data = [
            'x-openregister' => 'invalid',
        ];

        $result = $this->service->ensureSourceType($data);

        self::assertIsArray($result['x-openregister']);
        self::assertSame('local', $result['x-openregister']['sourceType']);
    }

    public function testEnsureSourceTypeAddsToExistingOpenregister(): void
    {
        $data = [
            'x-openregister' => [
                'otherKey' => 'value',
            ],
        ];

        $result = $this->service->ensureSourceType($data);

        self::assertSame('local', $result['x-openregister']['sourceType']);
        self::assertSame('value', $result['x-openregister']['otherKey']);
    }
}
