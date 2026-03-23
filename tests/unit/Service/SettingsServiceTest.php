<?php

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\SettingsService;
use OCA\LarpingApp\Service\SettingsLoadService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SettingsServiceTest extends TestCase
{
    private SettingsService $service;
    private IAppConfig $appConfig;
    private SettingsLoadService $settingsLoadService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->appConfig = $this->createMock(IAppConfig::class);
        $this->settingsLoadService = $this->createMock(SettingsLoadService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SettingsService(
            $this->appConfig,
            $this->settingsLoadService,
            $this->logger,
        );
    }

    public function testGetSettingsReturnsAllConfigKeys(): void
    {
        $this->appConfig
            ->method('getValueString')
            ->willReturn('test-value');

        $result = $this->service->getSettings();

        $this->assertArrayHasKey('register', $result);
        $this->assertArrayHasKey('character_schema', $result);
        $this->assertArrayHasKey('player_schema', $result);
        $this->assertArrayHasKey('ability_schema', $result);
        $this->assertArrayHasKey('skill_schema', $result);
        $this->assertArrayHasKey('item_schema', $result);
        $this->assertArrayHasKey('condition_schema', $result);
        $this->assertArrayHasKey('effect_schema', $result);
        $this->assertArrayHasKey('event_schema', $result);
        $this->assertArrayHasKey('setting_schema', $result);
        $this->assertCount(10, $result);
    }

    public function testGetSettingsReturnsEmptyStringsAsDefaults(): void
    {
        $this->appConfig
            ->method('getValueString')
            ->willReturnCallback(function (string $app, string $key, string $default) {
                return $default;
            });

        $result = $this->service->getSettings();

        foreach ($result as $value) {
            $this->assertSame('', $value);
        }
    }

    public function testUpdateSettingsOnlyUpdatesKnownKeys(): void
    {
        $this->appConfig
            ->expects($this->exactly(2))
            ->method('setValueString');

        $this->appConfig
            ->method('getValueString')
            ->willReturn('');

        $this->service->updateSettings([
            'register' => 'reg-1',
            'character_schema' => 'schema-1',
            'unknown_key' => 'should-be-ignored',
        ]);
    }

    public function testUpdateSettingsLogsUpdate(): void
    {
        $this->appConfig->method('getValueString')->willReturn('');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'LarpingApp settings updated',
                $this->callback(function ($context) {
                    return isset($context['keys']);
                })
            );

        $this->service->updateSettings(['register' => 'reg-1']);
    }

    public function testUpdateSettingsReturnsUpdatedValues(): void
    {
        $this->appConfig
            ->method('getValueString')
            ->willReturn('updated-value');

        $result = $this->service->updateSettings(['register' => 'reg-1']);

        $this->assertIsArray($result);
        $this->assertCount(10, $result);
    }

    public function testLoadSettingsDelegatesToLoadService(): void
    {
        $this->settingsLoadService
            ->expects($this->once())
            ->method('loadSettings')
            ->with(false)
            ->willReturn(['status' => 'ok']);

        $result = $this->service->loadSettings();

        $this->assertSame(['status' => 'ok'], $result);
    }

    public function testLoadSettingsForce(): void
    {
        $this->settingsLoadService
            ->expects($this->once())
            ->method('loadSettings')
            ->with(true)
            ->willReturn(['status' => 'reimported']);

        $result = $this->service->loadSettings(true);

        $this->assertSame(['status' => 'reimported'], $result);
    }

    public function testGetConfigValueReturnsValue(): void
    {
        $this->appConfig
            ->expects($this->once())
            ->method('getValueString')
            ->with('larpingapp', 'register', '')
            ->willReturn('my-register-id');

        $result = $this->service->getConfigValue('register');

        $this->assertSame('my-register-id', $result);
    }

    public function testSetConfigValueSetsValue(): void
    {
        $this->appConfig
            ->expects($this->once())
            ->method('setValueString')
            ->with('larpingapp', 'register', 'new-id');

        $this->service->setConfigValue('register', 'new-id');
    }
}
