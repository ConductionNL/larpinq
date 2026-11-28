<?php

/**
 * Golden test for the dataset steps of SetupController.
 *
 * Each case posts to saveConfig() or runAction() with a scripted request,
 * stored config and demo data service, and records the response and every
 * config write and install call. Compared with
 * fixtures/setup-dataset.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\SetupController;
use OCA\Larpinq\Service\DemoDataService;
use OCA\Larpinq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The dataset steps, case by case.
 *
 * @covers \OCA\Larpinq\Controller\SetupController
 */
class SetupDatasetGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/setup-dataset.golden.json';

	/**
	 * The cases.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$cases = [
			'save, no dataset' => ['call' => 'save', 'params' => ['a' => 'x', 'n' => 3, '_route' => 'r']],
			'save, known dataset' => ['call' => 'save', 'params' => ['demo_dataset' => 'demo', 'flag' => true]],
			'save, none' => ['call' => 'save', 'params' => ['demo_dataset' => 'none']],
			'save, unknown dataset' => ['call' => 'save', 'params' => ['demo_dataset' => 'other', 'a' => 'x']],
			'save, array dataset' => ['call' => 'save', 'params' => ['demo_dataset' => ['demo'], 'a' => 'x']],
			'save, numeric dataset' => ['call' => 'save', 'params' => ['demo_dataset' => 7]],
			'save, nested values' => ['call' => 'save', 'params' => ['list' => [1, 'two'], 'map' => ['k' => null], 'nil' => null, 'f' => 1.5]],
			'load, nothing picked' => [],
			'load, stored pick' => ['stored' => 'demo'],
			'load, stored none' => ['stored' => 'none'],
			'load, posted demo' => ['params' => ['dataset' => 'demo']],
			'load, posted none' => ['params' => ['dataset' => 'none'], 'stored' => 'demo'],
			'load, posted unknown' => ['params' => ['dataset' => 'other'], 'stored' => 'demo'],
			'load, posted array' => ['params' => ['dataset' => ['demo']]],
			'load, posted empty' => ['params' => ['dataset' => '']],
			'install id, nothing picked' => ['action' => 'install-demo-data'],
			'install id, stored none' => ['action' => 'install-demo-data', 'stored' => 'none'],
			'install id, posted none' => ['action' => 'install-demo-data', 'params' => ['dataset' => 'none']],
			'load, install fails' => ['stored' => 'demo', 'installThrows' => true],
			'load, install fails as error' => ['stored' => 'demo', 'installThrows' => 'error'],
			'unknown action' => ['action' => 'reboot'],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'call' => 'run',
				'action' => 'load-demo-data',
				'params' => [],
				'stored' => '',
				'installThrows' => false,
			];
		}

		return $cases;
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $case) {
			$actual[$name] = json_encode(value: $this->outcome(case: $case), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Run one case.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> The response and the calls.
	 */
	private function outcome(array $case): array {
		$calls = [];
		$params = $case['params'];
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			function (string $key, mixed $default = null) use ($params): mixed {
				if (array_key_exists($key, $params) === true) {
					return $params[$key];
				}

				return $default;
			}
		);
		$request->method('getParams')->willReturn($params);

		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			function (string $app, string $key, string $default = '') use ($case): string {
				if ($key === 'demo_dataset') {
					return $case['stored'];
				}

				return $default;
			}
		);
		$config->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value) use (&$calls): bool {
				$calls[] = ['set', $app, $key, $value];
				return true;
			}
		);

		$demo = $this->createMock(originalClassName: DemoDataService::class);
		$demo->method('listChoices')->willReturn([['id' => 'demo', 'title' => 'Demo'], ['id' => 'none', 'title' => 'None']]);
		$demo->method('install')->willReturnCallback(
			function () use ($case, &$calls): array {
				$calls[] = ['install'];
				if ($case['installThrows'] === true) {
					throw new \RuntimeException('import refused');
				}

				if ($case['installThrows'] === 'error') {
					throw new \Error('type error');
				}

				return ['objects' => 12, 'registers' => 1, 'schemas' => 4];
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message) use (&$calls): void {
				$calls[] = ['log', $message];
			}
		);

		$controller = new SetupController(
			request: $request,
			appConfig: $config,
			demoDataService: $demo,
			settingsService: $this->createMock(originalClassName: SettingsService::class),
			appManager: $this->createMock(originalClassName: IAppManager::class),
			logger: $logger
		);

		if ($case['call'] === 'save') {
			$response = $controller->saveConfig();
		} else {
			$response = $controller->runAction(actionId: $case['action']);
		}

		return ['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls];
	}//end outcome()
}//end class
