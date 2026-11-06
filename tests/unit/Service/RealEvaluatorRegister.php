<?php

/**
 * OpenRegister's REAL read evaluator under an in-memory register, for tests of
 * the reads larpinq makes as the signed-in user and with its own authority
 * (DECISIONS row 30).
 *
 * Rows read with RBAC on pass through OpenRegister's PermissionHandler with the
 * larpinq register's merged authorization blocks; rows read with RBAC off do
 * not. OpenRegister is loaded from `OPENREGISTER_LIB` or the `openregister` app
 * beside this one; without one the test is skipped and says so.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/RegisterAnswers.php';

use Composer\Autoload\ClassLoader;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Tests\Unit\Support\RegisterAnswers;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * Helpers for a test case over the real evaluator.
 */
trait RealEvaluatorRegister {

	/**
	 * Put OpenRegister's classes on the path, or skip the test.
	 *
	 * @return void
	 */
	private function requireOpenRegister(): void {
		$candidates = [(string)getenv('OPENREGISTER_LIB'), dirname(__DIR__, 4) . '/openregister/lib'];
		foreach ($candidates as $lib) {
			if ($lib !== '' && is_file($lib . '/Service/Object/PermissionHandler.php') === true) {
				foreach (ClassLoader::getRegisteredLoaders() as $loader) {
					$loader->addPsr4('OCA\\OpenRegister\\', rtrim($lib, '/') . '/');
				}

				return;
			}
		}

		$this->markTestSkipped(message: 'OpenRegister is not on the path; set OPENREGISTER_LIB to its lib/ to run the real evaluator.');
	}//end requireOpenRegister()

	/**
	 * The authorization blocks of the register as OpenRegister imports it.
	 *
	 * @return array<string, array<string, mixed>> Blocks by lower-cased schema key.
	 */
	private function authorizationBlocks(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		$merged = $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath);

		$blocks = [];
		foreach ($merged['components']['schemas'] as $key => $schema) {
			$blocks[strtolower((string)$key)] = ($schema['authorization'] ?? []);
		}

		return $blocks;
	}//end authorizationBlocks()

	/**
	 * OpenRegister's real PermissionHandler for a signed-in user.
	 *
	 * @param string       $uid    The user.
	 * @param list<string> $groups The user's groups.
	 *
	 * @return object The handler.
	 */
	private function permissionHandler(string $uid, array $groups): object {
		$register = new \OCA\OpenRegister\Db\Register();
		$register->setId(3);
		$registers = $this->createMock(originalClassName: \OCA\OpenRegister\Db\RegisterMapper::class);
		$registers->method('getFirstRegisterWithSchema')->willReturn(3);
		$registers->method('find')->willReturn($register);
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => RegisterAnswers::onlyService(id: $id, bound: \OCA\OpenRegister\Db\RegisterMapper::class, service: $registers)
		);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturn($user);
		$groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn($groups);

		return new \OCA\OpenRegister\Service\Object\PermissionHandler(
			userSession: $session,
			userManager: $users,
			groupManager: $groupManager,
			schemaMapper: $this->createMock(originalClassName: \OCA\OpenRegister\Db\SchemaMapper::class),
			objectEntityMapper: $this->createMock(originalClassName: \OCA\OpenRegister\Db\MagicMapper::class),
			conditionMatcher: $this->createMock(originalClassName: \OCA\OpenRegister\Service\ConditionMatcher::class),
			appConfig: $this->createMock(originalClassName: IAppConfig::class),
			logger: new NullLogger(),
			container: $container,
		);
	}//end permissionHandler()

	/**
	 * An in-memory ObjectService whose reads pass each row through the real
	 * evaluator as the signed-in user, unless RBAC is switched off.
	 *
	 * @param object                              $handler The real PermissionHandler.
	 * @param string                              $uid     The signed-in user.
	 * @param array<string, array<string, mixed>> $blocks  Authorization blocks by schema.
	 *
	 * @return object The fake.
	 */
	private function openRegister(object $handler, string $uid, array $blocks): object {
		return new class($handler, $uid, $blocks) {
			/** @var array<string, array<string, array<string, mixed>>> */
			public array $objects = [];

			/** @var list<array{schema: string, rbac: bool}> */
			public array $reads = [];

			/**
			 * A register over the real evaluator, for one user.
			 *
			 * @param object $handler The handler.
			 * @param string $uid The user id.
			 * @param array $blocks The blocks.
			 */
			public function __construct(private object $handler, private string $uid, private array $blocks) {
			}

			/**
			 * Store an object of a schema with its owner.
			 *
			 * @param string $schema The schema.
			 * @param string $owner The owner.
			 * @param array $data The data.
			 *
			 * @return void
			 */
			public function seed(string $schema, string $owner, array $data): void {
				$data['@self'] = ['id' => $data['id'], 'owner' => $owner, 'schema' => $schema];
				$this->objects[$schema][$data['id']] = $data;
			}

			/**
			 * Whether OpenRegister lets the user read a row.
			 *
			 * @param string $schema The schema.
			 * @param array $row The row.
			 * @param bool $rbac Whether RBAC applies.
			 *
			 * @return bool True when OpenRegister lets the user read a row.
			 */
			public function readable(string $schema, array $row, bool $rbac): bool {
				$entity = new \OCA\OpenRegister\Db\Schema();
				// A distinct id per schema: the evaluator caches its verdicts by schema id.
				$entity->setId((int)(array_search($schema, array_keys($this->blocks), true) + 100));
				$entity->setAuthorization($this->blocks[$schema] ?? []);
				return $this->handler->hasPermission(schema: $entity, action: 'read', userId: $this->uid, objectOwner: $row['@self']['owner'], _rbac: $rbac);
			}

			/**
			 * The rows of a schema that match the filters and that the user may read.
			 *
			 * @param string $schema The schema.
			 * @param array $filters The filters.
			 * @param bool $rbac Whether RBAC applies.
			 *
			 * @return array The rows of a schema that match the filters and that the user may read.
			 */
			public function rows(string $schema, array $filters, bool $rbac): array {
				$this->reads[] = ['schema' => $schema, 'rbac' => $rbac];
				$rows = [];
				foreach ($this->objects[$schema] ?? [] as $row) {
					foreach ($filters as $field => $value) {
						if (in_array($field, ['register', 'schema'], true) === false && ($row[$field] ?? null) !== $value) {
							continue 2;
						}
					}

					if ($this->readable(schema: $schema, row: $row, rbac: $rbac) === true) {
						$rows[] = $row;
					}
				}

				return $rows;
			}

			/**
			 * The mapper of a schema.
			 *
			 * @param mixed $register The register.
			 * @param mixed $schema The schema.
			 *
			 * @return object The mapper of a schema.
			 */
			public function getMapper($register, $schema): object {
				$store = $this;
				return new class($store, (string)$schema) {
					/**
					 * A mapper over the store for one schema.
					 *
					 * @param object $store The store.
					 * @param string $schema The schema.
					 */
					public function __construct(private object $store, private string $schema) {
					}

					/**
					 * Every row of the schema the user may read.
					 *
					 * @param array $config The config.
					 *
					 * @return array Every row of the schema the user may read.
					 */
					public function findAll(array $config = []): array {
						return $this->store->rows($this->schema, ($config['filters'] ?? []), true);
					}

					/**
					 * One row by id.
					 *
					 * @param string $id The id.
					 *
					 * @return array One row by id.
					 */
					public function find(string $id): array {
						$row = $this->store->objects[$this->schema][$id] ?? null;
						if ($row === null || $this->store->readable($this->schema, $row, true) === false) {
							throw new \RuntimeException('Object not found');
						}

						return $row;
					}
				};
			}

			/**
			 * Every row of the schema the user may read.
			 *
			 * @param array $config The config.
			 * @param bool $_rbac Whether RBAC applies.
			 * @param bool $_multitenancy Whether multitenancy applies.
			 *
			 * @return array Every row of the schema the user may read.
			 */
			public function findAll(array $config = [], bool $_rbac = true, bool $_multitenancy = true): array {
				return $this->rows(schema: (string)($config['filters']['schema'] ?? ''), filters: ($config['filters'] ?? []), rbac: $_rbac);
			}
		};
	}//end openRegister()

	/**
	 * A real RegisterObjectFetcher over the fake OpenRegister; the schema id is
	 * the lower-cased type name.
	 *
	 * @param object $openRegister The fake OpenRegister.
	 *
	 * @return RegisterObjectFetcher The fetcher.
	 */
	private function fetcherOver(object $openRegister): RegisterObjectFetcher {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => RegisterAnswers::onlyService(id: $id, bound: 'OCA\OpenRegister\Service\ObjectService', service: $openRegister)
		);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key): string => RegisterAnswers::registerConfig(key: $key, default: '')
		);

		return new RegisterObjectFetcher(container: $container, appManager: $apps, config: $config, logger: new NullLogger());
	}//end fetcherOver()

	/**
	 * A session signed in as the user.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUserSession The session.
	 */
	private function sessionOf(string $uid): IUserSession {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		return $session;
	}//end sessionOf()
}//end trait
