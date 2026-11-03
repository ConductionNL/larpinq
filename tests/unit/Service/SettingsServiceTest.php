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

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->settingsLoadService = $this->createMock(originalClassName: SettingsLoadService::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->service = new SettingsService(
			appConfig: $this->appConfig,
			settingsLoadService: $this->settingsLoadService,
			logger: $this->logger,
		);
	}

	/**
	 * Get settings returns all config keys.
	 *
	 * @return void
	 */
	public function testGetSettingsReturnsAllConfigKeys(): void {
		$this->appConfig
			->method('getValueString')
			->willReturn('test-value');

		$result = $this->service->getSettings();

		$this->assertArrayHasKey(key: 'register', array: $result);
		$this->assertArrayHasKey(key: 'character_schema', array: $result);
		$this->assertArrayHasKey(key: 'character_register', array: $result);
		$this->assertArrayHasKey(key: 'character_source', array: $result);
		$this->assertArrayHasKey(key: 'player_schema', array: $result);
		$this->assertArrayHasKey(key: 'player_register', array: $result);
		$this->assertArrayHasKey(key: 'player_source', array: $result);
		$this->assertArrayHasKey(key: 'ability_schema', array: $result);
		$this->assertArrayHasKey(key: 'skill_schema', array: $result);
		$this->assertArrayHasKey(key: 'item_schema', array: $result);
		$this->assertArrayHasKey(key: 'condition_schema', array: $result);
		$this->assertArrayHasKey(key: 'effect_schema', array: $result);
		$this->assertArrayHasKey(key: 'event_schema', array: $result);
		$this->assertArrayHasKey(key: 'setting_schema', array: $result);
		$this->assertArrayHasKey(key: 'attendance_schema', array: $result);
		$this->assertArrayHasKey(key: 'lorepage_schema', array: $result);
		$this->assertArrayHasKey(key: 'characterfield_schema', array: $result);
		// The faction guard resolves its three schemas by these keys.
		$this->assertArrayHasKey(key: 'faction_schema', array: $result);
		$this->assertArrayHasKey(key: 'factionmember_schema', array: $result);
		$this->assertArrayHasKey(key: 'relationship_schema', array: $result);
		// The build report reads a build by this key.
		$this->assertArrayHasKey(key: 'characterbuild_schema', array: $result);
		// The batch award and the XP stage read awards by this key.
		$this->assertArrayHasKey(key: 'xpaward_schema', array: $result);
		$this->assertArrayHasKey(key: 'registration_schema', array: $result);
		// The ticket choices read ticket types, options and codes by these keys.
		$this->assertArrayHasKey(key: 'tickettype_schema', array: $result);
		$this->assertArrayHasKey(key: 'registrationoption_schema', array: $result);
		$this->assertArrayHasKey(key: 'accesscode_schema', array: $result);
		// CONFIG_KEYS now includes {slug}_schema + {slug}_register + {slug}_source
		// for all 21 slugs (9 original + attendance + lorepage + characterfield
		// + faction + factionmember + relationship + characterbuild + xpaward + registration
		// + tickettype + registrationoption + accesscode)
		// plus the global 'register' key = 1 + 21*3 = 64.
		$this->assertCount(expectedCount: 64, haystack: $result);
	}

	/**
	 * Get settings returns empty strings as defaults.
	 *
	 * @return void
	 */
	public function testGetSettingsReturnsEmptyStringsAsDefaults(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default) {
				return $default;
			});

		$result = $this->service->getSettings();

		foreach ($result as $value) {
			$this->assertSame(expected: '', actual: $value);
		}
	}

	/**
	 * Update settings only updates known keys.
	 *
	 * @return void
	 */
	public function testUpdateSettingsOnlyUpdatesKnownKeys(): void {
		$this->appConfig
			->expects($this->exactly(count: 2))
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

	/**
	 * Update settings logs update.
	 *
	 * @return void
	 */
	public function testUpdateSettingsLogsUpdate(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$this->logger
			->expects($this->once())
			->method('info')
			->with(
				'Larpinq settings updated',
				$this->callback(callback: function ($context) {
					return isset($context['keys']);
				})
			);

		$this->service->updateSettings(['register' => 'reg-1']);
	}

	/**
	 * Update settings returns updated values.
	 *
	 * @return void
	 */
	public function testUpdateSettingsReturnsUpdatedValues(): void {
		$this->appConfig
			->method('getValueString')
			->willReturn('updated-value');

		$result = $this->service->updateSettings(['register' => 'reg-1']);

		$this->assertIsArray(actual: $result);
		$this->assertCount(expectedCount: 64, haystack: $result);
	}

	/**
	 * Load settings delegates to load service.
	 *
	 * @return void
	 */
	public function testLoadSettingsDelegatesToLoadService(): void {
		$this->settingsLoadService
			->expects($this->once())
			->method('loadSettings')
			->willReturn(['status' => 'ok']);

		$this->settingsLoadService
			->expects($this->never())
			->method('reloadSettings');

		$result = $this->service->loadSettings();

		$this->assertSame(expected: ['status' => 'ok'], actual: $result);
	}

	/**
	 * Reload settings delegates to load service.
	 *
	 * @return void
	 */
	public function testReloadSettingsDelegatesToLoadService(): void {
		$this->settingsLoadService
			->expects($this->once())
			->method('reloadSettings')
			->willReturn(['status' => 'reimported']);

		$this->settingsLoadService
			->expects($this->never())
			->method('loadSettings');

		$result = $this->service->reloadSettings();

		$this->assertSame(expected: ['status' => 'reimported'], actual: $result);
	}

	/**
	 * Get config value returns value.
	 *
	 * @return void
	 */
	public function testGetConfigValueReturnsValue(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with('larpinq', 'register', '')
			->willReturn('my-register-id');

		$result = $this->service->getConfigValue('register');

		$this->assertSame(expected: 'my-register-id', actual: $result);
	}

	/**
	 * Set config value sets value.
	 *
	 * @return void
	 */
	public function testSetConfigValueSetsValue(): void {
		$this->appConfig
			->expects($this->once())
			->method('setValueString')
			->with('larpinq', 'register', 'new-id');

		$this->service->setConfigValue('register', 'new-id');
	}
}
