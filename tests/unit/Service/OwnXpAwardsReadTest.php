<?php

/**
 * A player's own stat sheet counts the XP awards a game master granted to
 * their character (DECISIONS row 30, event-xp-awards).
 *
 * xpAward is read by game masters and the record's owner only, and the owner
 * of an award is the game master who granted it. Read as the player, OpenRegister
 * hides every award, so the player's own XP, and the XP budget their skill
 * choices are checked against, would count nothing. Players see their own
 * records through the app's pages: larpinq checks that the caller owns the
 * character (CharacterConnectionGuard), then reads that character's awards
 * with the app's authority.
 *
 * The read verdicts come from OpenRegister's REAL evaluator, loaded from
 * `OPENREGISTER_LIB` or the `openregister` app beside this one; without one
 * the test is skipped and says so.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use Composer\Autoload\ClassLoader;
use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * The owner of a character reads that character's XP awards; nobody else does.
 */
class OwnXpAwardsReadTest extends TestCase {

	/**
	 * The fake OpenRegister: objects per schema, read through the real evaluator.
	 *
	 * @var object
	 */
	private object $openRegister;

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

		$this->markTestSkipped('OpenRegister is not on the path; set OPENREGISTER_LIB to its lib/ to run the real evaluator.');
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
		$registers = $this->createMock(\OCA\OpenRegister\Db\RegisterMapper::class);
		$registers->method('getFirstRegisterWithSchema')->willReturn(3);
		$registers->method('find')->willReturn($register);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === \OCA\OpenRegister\Db\RegisterMapper::class ? $registers : throw new \RuntimeException('not in this test: ' . $id))
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn($groups);

		return new \OCA\OpenRegister\Service\Object\PermissionHandler(
			userSession: $session,
			userManager: $users,
			groupManager: $groupManager,
			schemaMapper: $this->createMock(\OCA\OpenRegister\Db\SchemaMapper::class),
			objectEntityMapper: $this->createMock(\OCA\OpenRegister\Db\MagicMapper::class),
			conditionMatcher: $this->createMock(\OCA\OpenRegister\Service\ConditionMatcher::class),
			appConfig: $this->createMock(IAppConfig::class),
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

			public function __construct(private object $handler, private string $uid, private array $blocks) {
			}

			public function seed(string $schema, string $owner, array $data): void {
				$data['@self'] = ['id' => $data['id'], 'owner' => $owner, 'schema' => $schema];
				$this->objects[$schema][$data['id']] = $data;
			}

			public function readable(string $schema, array $row, bool $rbac): bool {
				$entity = new \OCA\OpenRegister\Db\Schema();
				// A distinct id per schema: the evaluator caches its verdicts by schema id.
				$entity->setId((int)(array_search($schema, array_keys($this->blocks), true) + 100));
				$entity->setAuthorization($this->blocks[$schema] ?? []);
				return $this->handler->hasPermission(schema: $entity, action: 'read', userId: $this->uid, objectOwner: $row['@self']['owner'], _rbac: $rbac);
			}

			public function rows(string $schema, array $filters, bool $rbac): array {
				$this->reads[] = ['schema' => $schema, 'rbac' => $rbac];
				$rows = [];
				foreach ($this->objects[$schema] ?? [] as $row) {
					foreach ($filters as $field => $value) {
						if (in_array($field, ['register', 'schema'], true) === false && ($row[$field] ?? null) !== $value) {
							continue 2;
						}
					}

					if ($this->readable($schema, $row, $rbac) === true) {
						$rows[] = $row;
					}
				}

				return $rows;
			}

			public function getMapper($register, $schema): object {
				$store = $this;
				return new class($store, (string)$schema) {
					public function __construct(private object $store, private string $schema) {
					}

					public function findAll(array $config = []): array {
						return $this->store->rows($this->schema, ($config['filters'] ?? []), true);
					}

					public function find(string $id): array {
						$row = $this->store->objects[$this->schema][$id] ?? null;
						if ($row === null || $this->store->readable($this->schema, $row, true) === false) {
							throw new \RuntimeException('Object not found');
						}

						return $row;
					}
				};
			}

			public function findAll(array $config = [], bool $_rbac = true, bool $_multitenancy = true): array {
				return $this->rows((string)($config['filters']['schema'] ?? ''), ($config['filters'] ?? []), $_rbac);
			}
		};
	}//end openRegister()

	/**
	 * The stat engine as larpinq wires it, reading as the signed-in player.
	 *
	 * @param string $uid The signed-in player.
	 *
	 * @return CharacterService The engine.
	 */
	private function engine(string $uid): CharacterService {
		$blocks = $this->authorizationBlocks();
		$this->openRegister = $this->openRegister($this->permissionHandler($uid, ['larpers']), $uid, $blocks);

		$this->openRegister->seed('ability', 'gerrit', ['id' => 'ab-xp', 'name' => 'XP', 'base' => 0]);
		$this->openRegister->seed('character', 'anna', ['id' => 'ch-anna', 'name' => 'Brynja', 'ownerUid' => 'anna']);
		$this->openRegister->seed('character', 'bert', ['id' => 'ch-bert', 'name' => 'Oswin', 'ownerUid' => 'bert']);
		// The game master gerrit granted both awards, so he owns them.
		$this->openRegister->seed('xpaward', 'gerrit', ['id' => 'aw-1', 'character' => 'ch-anna', 'event' => 'ev-1', 'amount' => 20, 'reason' => 'attendance']);
		$this->openRegister->seed('xpaward', 'gerrit', ['id' => 'aw-2', 'character' => 'ch-anna', 'event' => 'ev-2', 'amount' => 5, 'reason' => 'plot']);
		$this->openRegister->seed('xpaward', 'gerrit', ['id' => 'aw-3', 'character' => 'ch-bert', 'event' => 'ev-1', 'amount' => 30, 'reason' => 'attendance']);

		$openRegister = $this->openRegister;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\OpenRegister\Service\ObjectService' ? $openRegister : throw new \RuntimeException('not bound: ' . $id))
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key): string => (str_ends_with($key, '_register') === true ? '3' : substr($key, 0, -strlen('_schema')))
		);
		$fetcher = new RegisterObjectFetcher($container, $apps, $config, new NullLogger());

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new CharacterService($fetcher, new NullLogger(), new EffectApplier(), new CharacterConnectionGuard($fetcher), $session);
	}//end engine()

	/**
	 * Control: OpenRegister's evaluator itself refuses the player the award,
	 * so a green result below comes from larpinq's own read, not a lax fake.
	 *
	 * @return void
	 */
	public function testTheEvaluatorHidesTheGameMastersAwardFromThePlayer(): void {
		$this->requireOpenRegister();
		$this->engine('anna');
		$this->assertFalse($this->openRegister->readable('xpaward', $this->openRegister->objects['xpaward']['aw-1'], true));
		$this->assertTrue($this->openRegister->readable('character', $this->openRegister->objects['character']['ch-anna'], true));
	}//end testTheEvaluatorHidesTheGameMastersAwardFromThePlayer()

	/**
	 * The owner's stat sheet counts both awards on her character, with their audit.
	 *
	 * @return void
	 */
	public function testThePlayerCountsTheAwardsOnHerOwnCharacter(): void {
		$this->requireOpenRegister();
		$engine = $this->engine('anna');

		$stats = $engine->calculateCharacter(['id' => 'ch-anna', 'name' => 'Brynja', 'ownerUid' => 'anna'])['stats'];

		$this->assertSame(25, $stats['ab-xp']['value']);
		$this->assertSame(['aw-1', 'aw-2'], array_map(static fn (array $entry): string => $entry['award']['id'], $stats['ab-xp']['audit']));
	}//end testThePlayerCountsTheAwardsOnHerOwnCharacter()

	/**
	 * Another player's character gets none of its awards read with the app's
	 * authority: the ownership check comes from the stored character, not the
	 * payload, so claiming ownerUid in a candidate does not help.
	 *
	 * @return void
	 */
	public function testAnotherPlayersAwardsStayHidden(): void {
		$this->requireOpenRegister();
		$engine = $this->engine('anna');

		$stats = $engine->calculateCharacter(['id' => 'ch-bert', 'name' => 'Oswin', 'ownerUid' => 'anna'])['stats'];

		$this->assertSame(0, $stats['ab-xp']['value']);
		$this->assertSame([], array_values(array_filter($this->openRegister->reads, static fn (array $read): bool => $read['rbac'] === false)));
	}//end testAnotherPlayersAwardsStayHidden()
}//end class
