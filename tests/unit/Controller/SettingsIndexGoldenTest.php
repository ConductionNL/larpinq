<?php

/**
 * Golden test for the settings read of SettingsController.
 *
 * Each case calls index() with a scripted caller, installed apps, register
 * and schema mappers, and records the response and the mapper calls.
 * Compared with fixtures/settings-index.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\SettingsController;
use OCA\Larpinq\Service\SettingsService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * An OpenRegister entity double that serialises to a fixed array.
 */
class GoldenSerialisable implements \JsonSerializable {
	/**
	 * Build it.
	 *
	 * @param array<string,mixed> $data The serialised form.
	 */
	public function __construct(private readonly array $data) {
	}//end __construct()

	/**
	 * The serialised form.
	 *
	 * @return array<string,mixed> The data.
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}//end jsonSerialize()
}//end class

/**
 * A register mapper double.
 */
class GoldenRegisterMapper {
	/**
	 * Build it.
	 *
	 * @param array<int,GoldenSerialisable>|null $registers The registers, or null to throw.
	 * @param \ArrayObject                       $calls     The call log.
	 */
	public function __construct(private readonly ?array $registers, private readonly \ArrayObject $calls) {
	}//end __construct()

	/**
	 * All registers.
	 *
	 * @param bool $_rbac         Whether RBAC applies.
	 * @param bool $_multitenancy Whether multitenancy applies.
	 *
	 * @return array<int,GoldenSerialisable> The registers.
	 */
	public function findAll(bool $_rbac = true, bool $_multitenancy = true): array {
		$this->calls[] = ['findAll', $_rbac, $_multitenancy];
		if ($this->registers === null) {
			throw new \RuntimeException('database gone');
		}

		return $this->registers;
	}//end findAll()
}//end class

/**
 * A schema mapper double.
 */
class GoldenSchemaMapper {
	/**
	 * Build it.
	 *
	 * @param array<int,array<string,mixed>> $schemas Schemas by id.
	 * @param \ArrayObject                   $calls   The call log.
	 */
	public function __construct(private readonly array $schemas, private readonly \ArrayObject $calls) {
	}//end __construct()

	/**
	 * One schema.
	 *
	 * @param int $id The id.
	 *
	 * @return GoldenSerialisable The schema.
	 */
	public function find(int $id): GoldenSerialisable {
		$this->calls[] = ['find', $id];
		if (isset($this->schemas[$id]) === false) {
			throw new \RuntimeException('no schema '.$id);
		}

		return new GoldenSerialisable(data: $this->schemas[$id]);
	}//end find()
}//end class

/**
 * The settings read, case by case.
 *
 * @covers \OCA\Larpinq\Controller\SettingsController
 * @uses   \OCA\Larpinq\Controller\CallerAccess
 * @uses   \OCA\Larpinq\Tests\Unit\Controller\GoldenSerialisable
 * @uses   \OCA\Larpinq\Tests\Unit\Controller\GoldenRegisterMapper
 * @uses   \OCA\Larpinq\Tests\Unit\Controller\GoldenSchemaMapper
 */
class SettingsIndexGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/settings-index.golden.json';

	/**
	 * The cases.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$registers = [
			['id' => 1, 'title' => 'Larp', 'schemas' => [10, '11', 99]],
			['id' => 2, 'title' => 'Empty', 'schemas' => []],
			['id' => 3, 'title' => 'No key'],
		];
		$cases = [
			'admin' => [],
			'admin, no OpenRegister' => ['apps' => ['files']],
			'player' => ['uid' => 'player1'],
			'signed out' => ['uid' => null],
			'player, no OpenRegister' => ['uid' => 'player1', 'apps' => ['files']],
			'admin, register read fails' => ['registers' => null],
			'admin, no registers' => ['registers' => []],
			'admin, no schema mapper' => ['schemaMapper' => false],
			'admin, no register mapper' => ['registerMapper' => false],
			'admin, every register' => ['registers' => $registers],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'uid' => 'admin1',
				'apps' => ['files', 'openregister'],
				'registers' => [$registers[0]],
				'registerMapper' => true,
				'schemaMapper' => true,
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
		$calls = new \ArrayObject();
		$registers = null;
		if ($case['registers'] !== null) {
			$registers = array_map(fn (array $r): GoldenSerialisable => new GoldenSerialisable(data: $r), $case['registers']);
		}

		$registerMapper = new GoldenRegisterMapper(registers: $registers, calls: $calls);
		$schemas = [10 => ['id' => 10, 'title' => 'Character'], 11 => ['id' => 11, 'title' => 'Skill']];
		$schemaMapper = new GoldenSchemaMapper(schemas: $schemas, calls: $calls);

		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			function (string $id) use ($case, $registerMapper, $schemaMapper, $calls): object {
				$calls[] = ['container', $id];
				if ($id === 'OCA\OpenRegister\Db\RegisterMapper' && $case['registerMapper'] === true) {
					return $registerMapper;
				}

				if ($id === 'OCA\OpenRegister\Db\SchemaMapper' && $case['schemaMapper'] === true) {
					return $schemaMapper;
				}

				throw new \RuntimeException('not in the container: '.$id);
			}
		);

		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn($case['apps']);

		$settings = $this->createMock(originalClassName: SettingsService::class);
		$settings->method('getSettings')->willReturnCallback(
			function () use ($calls): array {
				$calls[] = ['getSettings'];
				return ['character_register' => '5'];
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['warning', 'debug'] as $level) {
			$logger->method($level)->willReturnCallback(
				function (string $message) use ($level, $calls): void {
					$calls[] = [$level, $message];
				}
			);
		}

		$controller = new SettingsController(
			request: $this->createMock(originalClassName: IRequest::class),
			container: $container,
			appManager: $apps,
			settingsService: $settings,
			callerAccess: $this->callerAccess(uid: $case['uid']),
			logger: $logger
		);

		$response = $controller->index();

		return ['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls->getArrayCopy()];
	}//end outcome()

	/**
	 * The real caller access over a session and a group manager that know one admin.
	 *
	 * @param string|null $uid The signed-in user, or null.
	 *
	 * @return CallerAccess The caller access.
	 */
	private function callerAccess(?string $uid): CallerAccess {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(fn (string $who): bool => $who === 'admin1');

		return new CallerAccess(userSession: $session, groupManager: $groups);
	}//end callerAccess()
}//end class
