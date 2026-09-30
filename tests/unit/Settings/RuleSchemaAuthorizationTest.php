<?php

/**
 * Who reads and writes the eight rule schemas once game masters manage the
 * register (DECISIONS rows 25 and 27, data-portability REQ-AIE-002).
 *
 * In OpenRegister a schema with an EMPTY authorization block takes the
 * REGISTER's block as its baseline (PermissionHandler::resolveAuthorizationRaw),
 * and a non-empty block refuses every action it does not list. So a register
 * block holding only `manage` would refuse read and write on every rule-less
 * schema to everyone but administrators. The eight schemas therefore declare
 * their own rules first: read for every signed-in user, create, update and
 * delete for game masters.
 *
 * The verdicts come from OpenRegister's REAL evaluator, loaded from an
 * OpenRegister checkout: `OPENREGISTER_LIB`, or the `openregister` app beside
 * this one (the Nextcloud apps directory, as in the PHPUnit CI job). Without
 * one the verdict tests are skipped and say so; the declaration tests always
 * run.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/data-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use Composer\Autoload\ClassLoader;
use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\ConfigFileLoaderService;
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
 * The rule schemas keep their readers and gain writers, and game masters import.
 */
class RuleSchemaAuthorizationTest extends TestCase {

	/**
	 * The eight schemas that had no rules of their own, by register key.
	 *
	 * @var list<string>
	 */
	private const RULE_SCHEMAS = ['player', 'ability', 'skill', 'item', 'condition', 'effect', 'event', 'setting'];

	/**
	 * The block each of the eight declares.
	 *
	 * @var array<string, list<string>>
	 */
	private const RULE_BLOCK = [
		'read' => ['authenticated'],
		'create' => [Application::GM_GROUP],
		'update' => [Application::GM_GROUP],
		'delete' => [Application::GM_GROUP],
	];

	/**
	 * The two record schemas read by game masters and the record's owner only.
	 *
	 * @var list<string>
	 */
	private const RECORD_SCHEMAS = ['xpAward', 'attendance'];

	/**
	 * The player and the game master, by their Nextcloud groups.
	 *
	 * @var array<string, list<string>>
	 */
	private const USERS = [
		'player' => ['larpers'],
		'game master' => [Application::GM_GROUP, 'larpers'],
	];

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$this->assertIsArray($monolith);

		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($loader, $monolith, $appPath);
		return $merged;
	}//end mergedRegister()

	/**
	 * Put OpenRegister's classes on the path, or skip the test.
	 *
	 * The prefix is APPENDED, so the verbatim stubs under tests/stubs keep
	 * serving the classes they carry, and every other class comes from the
	 * real OpenRegister.
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
	 * OpenRegister's verdict for a signed-in user on a schema of the larpinq register.
	 *
	 * @param array<string, mixed>      $schemaAuthorization   The schema's block.
	 * @param array<string, mixed>|null $registerAuthorization The register's block.
	 * @param list<string>              $groups                The user's groups.
	 * @param string                    $action                read, create, update or delete.
	 * @param string|null               $owner                 The record's owner, or null for a schema-level check.
	 *
	 * @return bool Whether OpenRegister lets the user through.
	 */
	private function verdict(array $schemaAuthorization, ?array $registerAuthorization, array $groups, string $action, ?string $owner=null): bool {
		$schema = new \OCA\OpenRegister\Db\Schema();
		$schema->setId(7);
		$schema->setAuthorization($schemaAuthorization);
		$register = new \OCA\OpenRegister\Db\Register();
		$register->setId(3);
		$register->setAuthorization($registerAuthorization);

		$registers = $this->createMock(\OCA\OpenRegister\Db\RegisterMapper::class);
		$registers->method('getFirstRegisterWithSchema')->willReturn(3);
		$registers->method('find')->willReturn($register);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === \OCA\OpenRegister\Db\RegisterMapper::class ? $registers : throw new \RuntimeException('not in this test: ' . $id))
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anna');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn($groups);

		$handler = new \OCA\OpenRegister\Service\Object\PermissionHandler(
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

		return $handler->hasPermission(schema: $schema, action: $action, userId: 'anna', objectOwner: $owner);
	}//end verdict()

	/**
	 * OpenRegister's register import check for a signed-in user.
	 *
	 * @param array<string, mixed>|null $registerAuthorization The register's block.
	 * @param list<string>              $groups                The user's groups.
	 *
	 * @return bool Whether RegistersController::import lets the user through.
	 */
	private function mayImport(?array $registerAuthorization, array $groups): bool {
		$register = new \OCA\OpenRegister\Db\Register();
		$register->setAuthorization($registerAuthorization);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anna');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn($groups);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($groupManager);

		$class = \OCA\OpenRegister\Controller\RegistersController::class;
		$controller = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		\Closure::bind(
			function () use ($session, $groupManager, $container): void {
				$this->userSession = $session;
				$this->groupManager = $groupManager;
				$this->container = $container;
			},
			$controller,
			$class
		)();
		$check = new \ReflectionMethod($class, 'checkRegisterManagePermission');
		$check->setAccessible(true);

		return $check->invoke($controller, $register) === true;
	}//end mayImport()

	/**
	 * Each of the eight declares read for signed-in users and writes for game masters.
	 *
	 * @return void
	 */
	public function testEveryRuleSchemaDeclaresItsOwnRules(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];
		foreach (self::RULE_SCHEMAS as $key) {
			$this->assertSame(self::RULE_BLOCK, $schemas[$key]['authorization'] ?? null, "{$key} must declare its own rules");
		}
	}//end testEveryRuleSchemaDeclaresItsOwnRules()

	/**
	 * No schema falls back to the register's block, which holds only manage.
	 *
	 * @return void
	 */
	public function testNoSchemaFallsBackToTheRegisterBlock(): void {
		foreach ($this->mergedRegister()['components']['schemas'] as $key => $schema) {
			$this->assertNotEmpty($schema['authorization'] ?? null, "{$key} has no rules, so the register's manage-only block would become its rules");
		}
	}//end testNoSchemaFallsBackToTheRegisterBlock()

	/**
	 * A player reads all eight and writes none of them.
	 *
	 * @return void
	 */
	public function testAPlayerReadsButCannotWriteTheRuleSchemas(): void {
		$this->requireOpenRegister();
		$register = $this->mergedRegister()['components'];
		foreach (self::RULE_SCHEMAS as $key) {
			$block = ($register['schemas'][$key]['authorization'] ?? []);
			$registerBlock = ($register['registers']['larpinq']['authorization'] ?? null);
			$this->assertTrue($this->verdict($block, $registerBlock, self::USERS['player'], 'read'), "a player reads {$key}");
			foreach (['create', 'update', 'delete'] as $action) {
				$this->assertFalse($this->verdict($block, $registerBlock, self::USERS['player'], $action), "a player cannot {$action} {$key}");
			}
		}
	}//end testAPlayerReadsButCannotWriteTheRuleSchemas()

	/**
	 * A game master reads and writes all eight, and imports.
	 *
	 * @return void
	 */
	public function testAGameMasterWritesAndImports(): void {
		$this->requireOpenRegister();
		$register = $this->mergedRegister()['components'];
		$registerBlock = ($register['registers']['larpinq']['authorization'] ?? null);
		foreach (self::RULE_SCHEMAS as $key) {
			foreach (['read', 'create', 'update', 'delete'] as $action) {
				$this->assertTrue(
					$this->verdict(($register['schemas'][$key]['authorization'] ?? []), $registerBlock, self::USERS['game master'], $action),
					"a game master may {$action} {$key}"
				);
			}
		}

		$this->assertTrue($this->mayImport($registerBlock, self::USERS['game master']), 'a game master imports');
		$this->assertFalse($this->mayImport($registerBlock, self::USERS['player']), 'a player does not import');
	}//end testAGameMasterWritesAndImports()

	/**
	 * The register's rule changes no read verdict on any schema: whoever read a
	 * schema without it reads it with it.
	 *
	 * @return void
	 */
	public function testNobodyLosesRead(): void {
		$this->requireOpenRegister();
		$register = $this->mergedRegister()['components'];
		$registerBlock = ($register['registers']['larpinq']['authorization'] ?? null);
		foreach ($register['schemas'] as $key => $schema) {
			foreach (self::USERS as $who => $groups) {
				$without = $this->verdict(($schema['authorization'] ?? []), null, $groups, 'read');
				$with = $this->verdict(($schema['authorization'] ?? []), $registerBlock, $groups, 'read');
				$this->assertSame($without, $with, "the {$who} read verdict on {$key} must not change with the register's rule");
			}
		}
	}//end testNobodyLosesRead()

	/**
	 * XP awards and attendance are read by game masters only, never by every
	 * signed-in user and never without signing in (DECISIONS row 30).
	 *
	 * @return void
	 */
	public function testXpAwardsAndAttendanceDeclareGameMastersAsTheirReaders(): void {
		$schemas = $this->mergedRegister()['components']['schemas'];
		foreach (self::RECORD_SCHEMAS as $key) {
			$this->assertSame([Application::GM_GROUP], ($schemas[$key]['authorization']['read'] ?? null), "{$key} is read by game masters");
			$this->assertSame([Application::GM_GROUP], ($schemas[$key]['authorization']['create'] ?? null), "{$key} is written by game masters");
		}
	}//end testXpAwardsAndAttendanceDeclareGameMastersAsTheirReaders()

	/**
	 * A game master reads every award and attendance record, the record's owner
	 * reads their own, and another player reads none.
	 *
	 * @return void
	 */
	public function testGameMastersAndTheOwnerReadXpAwardsAndAttendance(): void {
		$this->requireOpenRegister();
		$register = $this->mergedRegister()['components'];
		$registerBlock = ($register['registers']['larpinq']['authorization'] ?? null);
		foreach (self::RECORD_SCHEMAS as $key) {
			$block = ($register['schemas'][$key]['authorization'] ?? []);
			$this->assertTrue($this->verdict($block, $registerBlock, self::USERS['game master'], 'read', 'gerrit'), "a game master reads a {$key} record someone else owns");
			$this->assertTrue($this->verdict($block, $registerBlock, self::USERS['player'], 'read', 'anna'), "the owner reads her own {$key} record");
			$this->assertFalse($this->verdict($block, $registerBlock, self::USERS['player'], 'read', 'gerrit'), "a player does not read a {$key} record she does not own");
			$this->assertFalse($this->verdict($block, $registerBlock, self::USERS['player'], 'read'), "a player does not read {$key} records at schema level");
			$this->assertFalse($this->verdict($block, $registerBlock, self::USERS['player'], 'create'), "a player does not create {$key} records");
		}
	}//end testGameMastersAndTheOwnerReadXpAwardsAndAttendance()
}//end class
