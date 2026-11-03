<?php

/**
 * Golden test for the list reads of RegisterObjectFetcher.
 *
 * getObjects() and getObjectsWithAppAuthority() are called with each argument
 * combination against a fake OpenRegister whose findAll() returns rows in
 * every shape (arrays, JSON-serialisable entities, plain objects). The call
 * OpenRegister received and the rows returned are compared, as JSON text so
 * key order and scalar types count, with
 * fixtures/register-object-fetcher.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite the fixture.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * A row OpenRegister returns as an entity.
 */
class GoldenSerialisableRow implements \JsonSerializable {
	/**
	 * Serialise.
	 *
	 * @return array<string,mixed> The row.
	 */
	public function jsonSerialize(): array {
		return ['id' => 'entity', 'nested' => ['a' => 1]];
	}
}

/**
 * Pins what the list reads send to OpenRegister and return.
 *
 * @covers \OCA\Larpinq\Service\RegisterObjectFetcher
 */
class RegisterObjectFetcherGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/register-object-fetcher.golden.json';

	/**
	 * The calls OpenRegister received in the current case.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $calls = [];

	/**
	 * A fetcher over a fake OpenRegister.
	 *
	 * @return RegisterObjectFetcher The fetcher.
	 */
	private function fetcher(): RegisterObjectFetcher {
		$plain = new \stdClass();
		$plain->id = 'plain';
		$plain->count = 2;
		$rows = [['id' => 'array', 'n' => null], new GoldenSerialisableRow(), $plain, []];

		$service = new class($rows, $this->calls) {
			/**
			 * Build the fake.
			 *
			 * @param array<int,mixed> $rows The rows findAll returns.
			 * @param array<int,array<string,mixed>> $calls Receives every call.
			 */
			public function __construct(private array $rows, private array &$calls) {
			}

			/**
			 * The mapper for a register and schema.
			 *
			 * @param string $register The register.
			 * @param string $schema The schema.
			 *
			 * @return object The mapper.
			 */
			public function getMapper(string $register, string $schema): object {
				$this->calls[] = ['getMapper' => [$register, $schema]];
				$rows = $this->rows;
				$calls = &$this->calls;
				return new class($rows, $calls) {
					/**
					 * Build the mapper.
					 *
					 * @param array<int,mixed> $rows The rows.
					 * @param array<int,array<string,mixed>> $calls Receives every call.
					 */
					public function __construct(private array $rows, private array &$calls) {
					}

					/**
					 * Find all.
					 *
					 * @param mixed ...$args The arguments.
					 *
					 * @return array<int,mixed> The rows.
					 */
					public function findAll(mixed ...$args): array {
						$this->calls[] = ['mapper.findAll' => $args];
						return $this->rows;
					}
				};
			}

			/**
			 * Find all with flags.
			 *
			 * @param mixed ...$args The arguments, by name.
			 *
			 * @return array<int,mixed> The rows.
			 */
			public function findAll(mixed ...$args): array {
				$this->calls[] = ['service.findAll' => $args];
				return $this->rows;
			}
		};

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			function (string $id) use ($service): object {
				if ($id === 'OCA\OpenRegister\Service\ObjectService') {
					return $service;
				}

				throw new \RuntimeException('not bound');
			}
		);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => $app.'/'.$key
		);

		return new RegisterObjectFetcher(container: $container, appManager: $appManager, config: $config, logger: $this->createMock(originalClassName: LoggerInterface::class));
	}//end fetcher()

	/**
	 * The calls to record.
	 *
	 * @return array<string,callable> The calls by name.
	 */
	private function cases(): array {
		return [
			'getObjects type only' => fn (RegisterObjectFetcher $f) => $f->getObjects('Character'),
			'getObjects every argument' => fn (RegisterObjectFetcher $f) => $f->getObjects(objectType: 'skill', limit: 5, offset: 10, filters: ['a' => 'b'], sort: ['name' => 'asc'], search: 'q'),
			'getObjects null filters and sort' => fn (RegisterObjectFetcher $f) => $f->getObjects(objectType: 'item', filters: null, sort: null),
			'getObjects empty search' => fn (RegisterObjectFetcher $f) => $f->getObjects(objectType: 'item', limit: 0, search: ''),
			'app authority with limit' => fn (RegisterObjectFetcher $f) => $f->getObjectsWithAppAuthority(objectType: 'xpAward', filters: ['character' => 'c1', 'n' => 3], limit: 7),
			'app authority filter overriding register' => fn (RegisterObjectFetcher $f) => $f->getObjectsWithAppAuthority(objectType: 'xpAward', filters: ['register' => 'x']),
			'app authority empty filter' => fn (RegisterObjectFetcher $f) => $f->getObjectsWithAppAuthority(objectType: 'xpAward', filters: []),
			'app authority empty value' => fn (RegisterObjectFetcher $f) => $f->getObjectsWithAppAuthority(objectType: 'xpAward', filters: ['a' => 'b', 'c' => '']),
		];
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach ($this->cases() as $name => $call) {
			$this->calls = [];
			try {
				$returned = $call($this->fetcher());
				$outcome = ['returned' => $returned, 'types' => array_map('get_debug_type', $returned)];
			} catch (\Throwable $e) {
				$outcome = ['threw' => get_class($e).': '.$e->getMessage()];
			}

			$outcome['calls'] = $this->calls;
			$actual[$name] = json_encode($outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()
}//end class
