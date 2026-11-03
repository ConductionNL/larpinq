<?php

/**
 * Golden test for how DemoDataService offers and imports the shipped dataset.
 *
 * Each case writes a descriptor (or none) into a throwaway app directory and
 * records listChoices(), isAvailable() and install(): the result or the
 * error, the importer call and the log lines. Compared with
 * fixtures/demo-data.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/wizard-dataset-card-load/specs/first-time-setup/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\DemoDataService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The shipped dataset, case by case.
 *
 * @covers \OCA\Larpinq\Service\DemoDataService
 */
class DemoDataGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/demo-data.golden.json';

	/**
	 * The cases: descriptor content (null for none), plus importer behaviour.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$cases = [
			'no descriptor' => ['descriptor' => null],
			'descriptor is a directory' => ['descriptor' => 'DIR'],
			'invalid json' => ['descriptor' => '{nope'],
			'json scalar' => ['descriptor' => '42'],
			'no components' => ['descriptor' => '{"x":1}'],
			'components not an object' => ['descriptor' => '{"components":"x"}'],
			'objects not a list' => ['descriptor' => '{"components":{"objects":"x"}}'],
			'objects null' => ['descriptor' => '{"components":{"objects":null}}'],
			'three objects' => ['descriptor' => '{"components":{"objects":[{"a":1},{"b":2},{"c":3}],"schemas":{"s":{}}}}'],
			'empty objects' => ['descriptor' => '{"components":{"objects":[]}}'],
			'importer reports nothing' => ['result' => []],
			'importer reports scalars' => ['result' => ['registers' => 'one', 'schemas' => null]],
			'importer throws' => ['result' => 'throw'],
			'no OpenRegister' => ['apps' => ['files']],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'descriptor' => '{"components":{"objects":[{"a":1},{"b":2}]}}',
				'result' => ['registers' => ['r1'], 'schemas' => ['s1', 's2', 's3']],
				'apps' => ['files', 'openregister'],
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
	 * Run one case in a throwaway app directory.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> What the service offered, imported and logged.
	 */
	private function outcome(array $case): array {
		$app = sys_get_temp_dir().'/larpinq-demo-'.bin2hex(random_bytes(6));
		mkdir(directory: $app.'/lib/Settings', recursive: true);
		$descriptor = $app.'/lib/Settings/larpinq_mock_register.json';
		if ($case['descriptor'] === 'DIR') {
			mkdir(directory: $descriptor);
		} else if ($case['descriptor'] !== null) {
			file_put_contents(filename: $descriptor, data: $case['descriptor']);
		}

		$log = new \ArrayObject(['imports' => [], 'logs' => []]);
		$trace = [];
		try {
			$service = $this->service(case: $case, app: $app, log: $log);
			$trace['available'] = $service->isAvailable();
			$trace['choices'] = array_map(fn (array $c): array => [$c['id'], $c['objectCount']], $service->listChoices());
			try {
				$trace['installed'] = $service->install();
			} catch (\RuntimeException $e) {
				$trace['error'] = $e->getMessage();
			}
		} finally {
			exec('rm -rf '.escapeshellarg($app));
		}

		$trace['error'] = str_replace($app, '<app>', ($trace['error'] ?? ''));

		return $trace + $log->getArrayCopy();
	}//end outcome()

	/**
	 * The service over the throwaway app, with an importer that records its call.
	 *
	 * @param array<string,mixed> $case  The case.
	 * @param string              $app   The app directory.
	 * @param \ArrayObject        $log   The imports and log lines.
	 *
	 * @return DemoDataService The service.
	 */
	private function service(array $case, string $app, \ArrayObject $log): DemoDataService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($app);
		$appManager->method('getAppVersion')->willReturn('1.2.3');
		$appManager->method('getInstalledApps')->willReturn($case['apps']);

		$importer = new class ($case['result'], $log) {
			/**
			 * Build it.
			 *
			 * @param mixed        $result The scripted result, or 'throw'.
			 * @param \ArrayObject $log    The imports and log lines.
			 */
			public function __construct(private readonly mixed $result, private readonly \ArrayObject $log) {
			}

			/**
			 * Record the import.
			 *
			 * @param string              $appId   The configuration identity.
			 * @param array<string,mixed> $data    The descriptor.
			 * @param string              $version The app version.
			 * @param bool                $force   Whether the version gate is skipped.
			 *
			 * @return mixed The scripted result.
			 */
			public function importFromApp(string $appId, array $data, string $version, bool $force): mixed {
				$imports = $this->log['imports'];
				$imports[] = [$appId, count($data), $version, $force];
				$this->log['imports'] = $imports;
				if ($this->result === 'throw') {
					throw new \RuntimeException('import refused');
				}

				return $this->result;
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($importer);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('info')->willReturnCallback(
			function (string $message, array $context = []) use ($log): void {
				$logs = $log['logs'];
				$logs[] = [$message, $context];
				$log['logs'] = $logs;
			}
		);

		return new DemoDataService(appManager: $appManager, container: $container, logger: $logger);
	}//end service()
}//end class
