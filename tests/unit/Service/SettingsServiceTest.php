<?php

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\SettingsLoadService;
use OCA\Larpinq\Service\SettingsService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SettingsServiceTest extends TestCase {
	private SettingsService $service;
	private IAppConfig $appConfig;
	private SettingsLoadService $settingsLoadService;
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->settingsLoadService = $this->createMock(SettingsLoadService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new SettingsService(
			$this->appConfig,
			$this->settingsLoadService,
			$this->logger,
		);
	}

	public function testGetSettingsReturnsAllConfigKeys(): void {
		$this->appConfig
			->method('getValueString')
			->willReturn('test-value');

		$result = $this->service->getSettings();

		$this->assertArrayHasKey('register', $result);
		$this->assertArrayHasKey('character_schema', $result);
		$this->assertArrayHasKey('character_register', $result);
		$this->assertArrayHasKey('character_source', $result);
		$this->assertArrayHasKey('player_schema', $result);
		$this->assertArrayHasKey('player_register', $result);
		$this->assertArrayHasKey('player_source', $result);
		$this->assertArrayHasKey('ability_schema', $result);
		$this->assertArrayHasKey('skill_schema', $result);
		$this->assertArrayHasKey('item_schema', $result);
		$this->assertArrayHasKey('condition_schema', $result);
		$this->assertArrayHasKey('effect_schema', $result);
		$this->assertArrayHasKey('event_schema', $result);
		$this->assertArrayHasKey('setting_schema', $result);
		$this->assertArrayHasKey('attendance_schema', $result);
		$this->assertArrayHasKey('lorepage_schema', $result);
		$this->assertArrayHasKey('characterfield_schema', $result);
		// The faction guard resolves its three schemas by these keys.
		$this->assertArrayHasKey('faction_schema', $result);
		$this->assertArrayHasKey('factionmember_schema', $result);
		$this->assertArrayHasKey('relationship_schema', $result);
		// The build report reads a build by this key.
		$this->assertArrayHasKey('characterbuild_schema', $result);
		// The batch award and the XP stage read awards by this key.
		$this->assertArrayHasKey('xpaward_schema', $result);
		$this->assertArrayHasKey('registration_schema', $result);
		// The ticket choices read ticket types, options and codes by these keys.
		$this->assertArrayHasKey('tickettype_schema', $result);
		$this->assertArrayHasKey('registrationoption_schema', $result);
		$this->assertArrayHasKey('accesscode_schema', $result);
		// CONFIG_KEYS now includes {slug}_schema + {slug}_register + {slug}_source
		// for all 21 slugs (9 original + attendance + lorepage + characterfield
		// + faction + factionmember + relationship + characterbuild + xpaward + registration
		// + tickettype + registrationoption + accesscode)
		// plus the global 'register' key = 1 + 21*3 = 64.
		$this->assertCount(64, $result);
	}

	public function testGetSettingsReturnsEmptyStringsAsDefaults(): void {
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

	public function testUpdateSettingsOnlyUpdatesKnownKeys(): void {
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

	public function testUpdateSettingsLogsUpdate(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$this->logger
			->expects($this->once())
			->method('info')
			->with(
				'Larpinq settings updated',
				$this->callback(function ($context) {
					return isset($context['keys']);
				})
			);

		$this->service->updateSettings(['register' => 'reg-1']);
	}

	public function testUpdateSettingsReturnsUpdatedValues(): void {
		$this->appConfig
			->method('getValueString')
			->willReturn('updated-value');

		$result = $this->service->updateSettings(['register' => 'reg-1']);

		$this->assertIsArray($result);
		$this->assertCount(64, $result);
	}

	public function testLoadSettingsDelegatesToLoadService(): void {
		$this->settingsLoadService
			->expects($this->once())
			->method('loadSettings')
			->willReturn(['status' => 'ok']);

		$this->settingsLoadService
			->expects($this->never())
			->method('reloadSettings');

		$result = $this->service->loadSettings();

		$this->assertSame(['status' => 'ok'], $result);
	}

	public function testReloadSettingsDelegatesToLoadService(): void {
		$this->settingsLoadService
			->expects($this->once())
			->method('reloadSettings')
			->willReturn(['status' => 'reimported']);

		$this->settingsLoadService
			->expects($this->never())
			->method('loadSettings');

		$result = $this->service->reloadSettings();

		$this->assertSame(['status' => 'reimported'], $result);
	}

	public function testGetConfigValueReturnsValue(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with('larpinq', 'register', '')
			->willReturn('my-register-id');

		$result = $this->service->getConfigValue('register');

		$this->assertSame('my-register-id', $result);
	}

	public function testSetConfigValueSetsValue(): void {
		$this->appConfig
			->expects($this->once())
			->method('setValueString')
			->with('larpinq', 'register', 'new-id');

		$this->service->setConfigValue('register', 'new-id');
	}
}
