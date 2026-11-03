<?php

/**
 * Golden test for the game master checks of the events, player attendance,
 * registration choices, worlds and XP awards controllers.
 *
 * Every endpoint is called as a signed-out caller, a player, a game master
 * and an administrator. Every collaborator is a recording stub, so the
 * fixture holds the response (class, status, data) and the ordered list of
 * every collaborator call, which shows each access decision and that it is
 * taken before any read or write. Compared, as JSON text, with
 * fixtures/game-master-access.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 *
 * The controllers are built from their constructor's parameter types, so the
 * same test runs against any wiring of the session and group checks.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Controller\EventsController;
use OCA\Larpinq\Controller\PlayerAttendanceController;
use OCA\Larpinq\Controller\RegistrationChoicesController;
use OCA\Larpinq\Controller\WorldsController;
use OCA\Larpinq\Controller\XpAwardsController;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Pins who may call what on five controllers, and what each call reads first.
 *
 * @covers \OCA\Larpinq\Controller\EventsController
 * @covers \OCA\Larpinq\Controller\PlayerAttendanceController
 * @covers \OCA\Larpinq\Controller\RegistrationChoicesController
 * @covers \OCA\Larpinq\Controller\WorldsController
 * @covers \OCA\Larpinq\Controller\XpAwardsController
 * @uses   \OCA\Larpinq\Controller\CallerAccess
 */
class GameMasterAccessGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/game-master-access.golden.json';

	private const TEMPLATE = '0f8fad5b-d9cb-469f-a165-70867728950e';

	/**
	 * The ordered collaborator calls of the current case.
	 *
	 * @var array<int,string>
	 */
	private array $log = [];

	/**
	 * The callers: uid or null, game master, admin.
	 *
	 * @return array<string,array{0:string|null,1:bool,2:bool}>
	 */
	private function callers(): array {
		return [
			'signed out' => [null, false, false],
			'player' => ['player', false, false],
			'game master' => ['gm', true, false],
			'admin' => ['admin', false, true],
		];
	}//end callers()

	/**
	 * The endpoints: controller class and the call.
	 *
	 * @return array<string,array{0:class-string,1:callable}>
	 */
	private function endpoints(): array {
		return [
			'events downloadRunsheet' => [EventsController::class, fn ($c) => $c->downloadRunsheet('e-1', self::TEMPLATE)],
			'events roster' => [EventsController::class, fn ($c) => $c->roster('e-1')],
			'events recordAttendance' => [EventsController::class, fn ($c) => $c->recordAttendance('e-1')],
			'attendance index' => [PlayerAttendanceController::class, fn ($c) => $c->index('p-1')],
			'choices offer' => [RegistrationChoicesController::class, fn ($c) => $c->offer('r-1', 'code')],
			'choices counts' => [RegistrationChoicesController::class, fn ($c) => $c->counts('e-1')],
			'worlds access' => [WorldsController::class, fn ($c) => $c->access()],
			'worlds preview' => [WorldsController::class, fn ($c) => $c->preview('w-1')],
			'worlds copy' => [WorldsController::class, fn ($c) => $c->copy('w-1', 'Copy')],
			'xp access' => [XpAwardsController::class, fn ($c) => $c->access()],
			'xp roster' => [XpAwardsController::class, fn ($c) => $c->roster('e-1')],
			'xp award' => [XpAwardsController::class, fn ($c) => $c->award('e-1', [['character' => 'c-1', 'amount' => 5]])],
			'xp award no rows' => [XpAwardsController::class, fn ($c) => $c->award('e-1', [])],
		];
	}//end endpoints()

	/**
	 * A stub that logs every call to it and answers with PHPUnit's default
	 * return value for the method's return type (OpenRegister counts as installed).
	 *
	 * @param string $name The collaborator's short name.
	 *
	 * @return Stub The stub.
	 */
	private function recorder(string $name): Stub {
		$log = &$this->log;
		return new class($name, $log) implements Stub {
			/**
			 * Build the recorder.
			 *
			 * @param string $name The collaborator's short name.
			 * @param array<int,string> $log The shared call log.
			 */
			public function __construct(private string $name, private array &$log) {
			}

			/**
			 * Log and answer; OpenRegister counts as installed.
			 *
			 * @param Invocation $invocation The call.
			 *
			 * @return mixed The default return value.
			 */
			public function invoke(Invocation $invocation): mixed {
				$this->log[] = $this->name.'::'.$invocation->methodName().'('.json_encode($invocation->parameters()).')';
				if ($invocation->methodName() === 'getInstalledApps') {
					return ['openregister'];
				}

				return $invocation->generateReturnValue();
			}

			/**
			 * Describe.
			 *
			 * @return string The description.
			 */
			public function toString(): string {
				return 'records '.$this->name;
			}
		};
	}//end recorder()

	/**
	 * A recording stub of a type.
	 *
	 * @param class-string $type The type.
	 *
	 * @return MockObject The stub.
	 */
	private function recording(string $type): MockObject {
		$mock = $this->createMock(originalClassName: $type);
		$short = substr((string)strrchr('\\'.$type, '\\'), 1);
		$mock->method($this->anything())->will($this->recorder(name: $short));
		return $mock;
	}//end recording()

	/**
	 * The session and group manager for a caller.
	 *
	 * @param array{0:string|null,1:bool,2:bool} $caller The caller.
	 *
	 * @return array{0:IUserSession,1:IGroupManager} The two.
	 */
	private function identity(array $caller): array {
		[$uid, $isGm, $isAdmin] = $caller;
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturnCallback(
				function () use ($uid): string {
					$this->log[] = 'IUser::getUID()';
					return $uid;
				}
			);
		}

		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function () use ($user): ?IUser {
				$this->log[] = 'IUserSession::getUser()';
				return $user;
			}
		);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(
			function (string $who) use ($isAdmin): bool {
				$this->log[] = 'IGroupManager::isAdmin('.$who.')';
				return $isAdmin;
			}
		);
		$groups->method('isInGroup')->willReturnCallback(
			function (string $who, string $group) use ($isGm): bool {
				$this->log[] = 'IGroupManager::isInGroup('.$who.','.$group.')';
				return $isGm && $group === Application::GM_GROUP;
			}
		);

		return [$session, $groups];
	}//end identity()

	/**
	 * A controller built from its constructor's parameter types.
	 *
	 * @param class-string $class The controller class.
	 * @param array{0:string|null,1:bool,2:bool} $caller The caller.
	 *
	 * @return object The controller.
	 */
	private function controller(string $class, array $caller): object {
		[$session, $groups] = $this->identity(caller: $caller);
		$args = [];
		foreach ((new \ReflectionMethod($class, '__construct'))->getParameters() as $parameter) {
			$type = $parameter->getType();
			$typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
			$args[] = match (true) {
				$parameter->getName() === 'appName' => 'larpinq',
				$typeName === IUserSession::class => $session,
				$typeName === IGroupManager::class => $groups,
				$typeName === 'OCA\Larpinq\Controller\CallerAccess' => new $typeName($session, $groups),
				$typeName === IRequest::class => $this->recording(type: IRequest::class),
				default => $this->recording(type: $typeName),
			};
		}

		return new $class(...$args);
	}//end controller()

	/**
	 * Every endpoint for every caller matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach ($this->endpoints() as $endpoint => [$class, $call]) {
			foreach ($this->callers() as $who => $caller) {
				$this->log = [];
				try {
					$response = $call($this->controller(class: $class, caller: $caller));
					$outcome = [
						'class' => get_class($response),
						'status' => $response instanceof Response ? $response->getStatus() : null,
						'data' => method_exists($response, 'getData') === true ? $response->getData() : null,
					];
				} catch (\Throwable $e) {
					$outcome = ['threw' => get_class($e).': '.$e->getMessage()];
				}

				$outcome['calls'] = $this->log;
				$actual[$endpoint.' / '.$who] = json_encode($outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
			}
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
