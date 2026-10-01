<?php

/**
 * Tests for registrations: capacity, the waiting list, approval, the
 * participants and the character a player brings
 * (registration-intake-and-capacity REQ-RIC-002 to REQ-RIC-006).
 *
 * Every save runs through larpinq's real RegistrationListener,
 * RegistrationService and RegisterObjectFetcher over an in-memory
 * OpenRegister that dispatches OpenRegister's own pre- and post-write events.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/InMemoryOpenRegister.php';

use OCA\Larpinq\Listener\RegistrationListener;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationCharacterCheck;
use OCA\Larpinq\Service\RegistrationService;
use OCA\Larpinq\Service\TicketChoiceService;
use OCA\Larpinq\Tests\Unit\Support\InMemoryObjectEntity;
use OCA\Larpinq\Tests\Unit\Support\InMemoryOpenRegister;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

/**
 * Locks shared by every "request" of one test, each request with its own view.
 */
class SharedLocks {
	/**
	 * Lock path => owning request.
	 *
	 * @var array<string, string>
	 */
	public array $held = [];

	/**
	 * Every acquire that succeeded.
	 *
	 * @var list<string>
	 */
	public array $acquired = [];

	/**
	 * One request's locking provider.
	 *
	 * @param string $request The request name.
	 *
	 * @return ILockingProvider The provider.
	 */
	public function forRequest(string $request): ILockingProvider {
		$shared = $this;
		return new class($shared, $request) implements ILockingProvider {
			/**
			 * @param SharedLocks $shared The shared table.
			 * @param string $request The request name.
			 */
			public function __construct(private SharedLocks $shared, private string $request) {
			}

			public function isLocked(string $path, int $type): bool {
				return isset($this->shared->held[$path]);
			}

			public function acquireLock(string $path, int $type, ?string $readablePath = null): void {
				if (isset($this->shared->held[$path]) === true) {
					throw new LockedException($path);
				}

				$this->shared->held[$path] = $this->request;
				$this->shared->acquired[] = $path;
			}

			public function releaseLock(string $path, int $type): void {
				if (($this->shared->held[$path] ?? null) === $this->request) {
					unset($this->shared->held[$path]);
				}
			}

			public function changeLock(string $path, int $targetType): void {
			}

			public function releaseAll(): void {
				foreach ($this->shared->held as $path => $owner) {
					if ($owner === $this->request) {
						unset($this->shared->held[$path]);
					}
				}
			}
		};
	}
}

/**
 * REQ-RIC-002 to REQ-RIC-006.
 */
class RegistrationServiceTest extends TestCase {

	private const EVENT = 'a0000000-0000-4000-8000-000000000001';
	private const ALDMOOR = 'b0000000-0000-4000-8000-000000000001';
	private const ELSEWHERE = 'b0000000-0000-4000-8000-000000000002';
	private const ANNA = 'c0000000-0000-4000-8000-000000000001';
	private const KAREL = 'c0000000-0000-4000-8000-000000000002';
	private const SANNE = 'c0000000-0000-4000-8000-000000000003';
	private const PIETER = 'c0000000-0000-4000-8000-000000000004';
	private const MIRELA = 'd0000000-0000-4000-8000-000000000001';
	private const HARROW = 'd0000000-0000-4000-8000-000000000002';
	private const VENN = 'd0000000-0000-4000-8000-000000000003';
	private const TOMAS = 'd0000000-0000-4000-8000-000000000004';
	private const FAR_AWAY = 'd0000000-0000-4000-8000-000000000005';
	private const HAND_1 = 'd0000000-0000-4000-8000-000000000006';
	private const HAND_2 = 'd0000000-0000-4000-8000-000000000007';

	private InMemoryOpenRegister $store;

	private SharedLocks $locks;

	private string $signedIn = 'gm';

	/**
	 * A register with one event in Aldmoor, four players and their characters.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = new InMemoryOpenRegister();
		$this->locks = new SharedLocks();
		$this->event(capacity: 3, approval: false);
		$players = [self::ANNA => 'anna', self::KAREL => 'karel', self::SANNE => 'sanne', self::PIETER => 'pieter'];
		foreach ($players as $id => $uid) {
			$this->store->seed('player', ['id' => $id, 'name' => ucfirst($uid), 'userUid' => $uid]);
		}

		$characters = [
			[self::MIRELA, 'Mirela the Wanderer', self::ANNA, 'active', self::ALDMOOR],
			[self::HARROW, 'Old Captain Harrow', self::KAREL, 'retired', self::ALDMOOR],
			[self::VENN, 'Lady Venn', self::SANNE, 'active', self::ALDMOOR],
			[self::TOMAS, 'Tomas', self::PIETER, 'active', self::ALDMOOR],
			[self::FAR_AWAY, 'Anna elsewhere', self::ANNA, 'active', self::ELSEWHERE],
		];
		foreach ($characters as [$id, $name, $player, $status, $world]) {
			$this->store->seed('character', ['id' => $id, 'name' => $name, 'ocName' => $player, 'status' => $status, 'setting' => $world]);
		}

		$this->store->listeners = [$this->listener(request: 'main')];
	}//end setUp()

	/**
	 * Replace the event.
	 *
	 * @param int|null $capacity The capacity.
	 * @param bool $approval Whether approval is required.
	 * @param array<int, string> $players Characters already in the event.
	 *
	 * @return void
	 */
	private function event(?int $capacity, bool $approval, array $players = []): void {
		$this->store->seed('event', [
			'id' => self::EVENT,
			'name' => 'Winter Court 2026',
			'setting' => self::ALDMOOR,
			'capacity' => $capacity,
			'approvalRequired' => $approval,
			'players' => $players,
		]);
	}//end event()

	/**
	 * A real fetcher over the store.
	 *
	 * @return RegisterObjectFetcher The fetcher.
	 */
	private function fetcher(): RegisterObjectFetcher {
		$store = $this->store;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\OpenRegister\Service\ObjectService' ? $store : throw new \RuntimeException('not bound: ' . $id))
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);

		return new RegisterObjectFetcher($container, $apps, $this->config(), new NullLogger());
	}//end fetcher()

	/**
	 * App config: register 3, the schema id is the type name.
	 *
	 * @return IAppConfig The config.
	 */
	private function config(): IAppConfig {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => (str_ends_with($key, '_register') === true ? '3' : (str_ends_with($key, '_schema') === true ? substr($key, 0, -strlen('_schema')) : $default))
		);
		return $config;
	}//end config()

	/**
	 * The real listener and service for one request.
	 *
	 * @param string $request The request name (its own lock view).
	 * @param int $lockAttempts How often the service tries a busy lock.
	 *
	 * @return RegistrationListener The listener.
	 */
	private function listener(string $request, int $lockAttempts = 30): RegistrationListener {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function (): IUser {
				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($this->signedIn);
				return $user;
			}
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$service = new RegistrationService($this->fetcher(), $this->locks->forRequest($request), new NullLogger(), $lockAttempts, 0);

		return new RegistrationListener($this->config(), $service, new RegistrationCharacterCheck($this->fetcher()), new TicketChoiceService($this->fetcher(), $service, new NullLogger()), $session, $l10n, new NullLogger());
	}//end listener()

	/**
	 * A sign-up as the Forms listener writes it.
	 *
	 * @param string $player The player.
	 * @param string $uid The submitter.
	 * @param string $at Submitted at.
	 * @param string $character The character, or empty.
	 *
	 * @return array<string, mixed> The stored registration.
	 */
	private function signUp(string $player, string $uid, string $at = '2026-10-01T10:00:00+00:00', string $character = ''): array {
		$data = [
			'event' => self::EVENT,
			'player' => $player,
			'submitterUid' => $uid,
			'submissionId' => 7,
			'submittedAt' => $at,
		];
		if ($character !== '') {
			$data['character'] = $character;
		}

		return $this->fetcher()->saveObjectWithAppAuthority('registration', $data);
	}//end signUp()

	/**
	 * A stored registration.
	 *
	 * @param string $id The uuid.
	 *
	 * @return array<string, mixed> The row.
	 */
	private function registration(string $id): array {
		return $this->store->objects['registration'][$id];
	}//end registration()

	/**
	 * Seed a registration without events.
	 *
	 * @param string $id The uuid.
	 * @param string $status The status.
	 * @param string $character The character.
	 * @param string $at Submitted at.
	 *
	 * @return void
	 */
	private function seedRegistration(string $id, string $status, string $character, string $at): void {
		$this->store->seed('registration', ['id' => $id, 'event' => self::EVENT, 'status' => $status, 'character' => $character, 'submittedAt' => $at]);
	}//end seedRegistration()

	/**
	 * Without approval, a sign-up with a free place is accepted, and the lock is free afterwards.
	 *
	 * @return void
	 */
	public function testWithoutApprovalAFreePlaceIsAccepted(): void {
		$saved = $this->signUp(player: self::ANNA, uid: 'anna');

		$row = $this->registration($saved['id']);
		$this->assertSame('accepted', $row['status']);
		$this->assertSame('larpinq', $row['decidedBy']);
		$this->assertNotEmpty($row['decidedAt']);
		$this->assertSame(['larpinq/event-capacity/' . self::EVENT], $this->locks->acquired);
		$this->assertSame([], $this->locks->held, 'the post-write handler releases the lock');
	}//end testWithoutApprovalAFreePlaceIsAccepted()

	/**
	 * Places taken are accepted registrations plus characters a game master added by hand.
	 *
	 * @return void
	 */
	public function testAFullEventWaitlistsAndCountsHandAddedParticipants(): void {
		$this->event(capacity: 3, approval: false, players: [self::HAND_1, self::HAND_2, self::VENN]);
		$this->seedRegistration(id: 'e0000000-0000-4000-8000-000000000001', status: 'accepted', character: self::VENN, at: '2026-09-01T10:00:00+00:00');

		$saved = $this->signUp(player: self::PIETER, uid: 'pieter');

		$this->assertSame('waitlisted', $this->registration($saved['id'])['status']);
	}//end testAFullEventWaitlistsAndCountsHandAddedParticipants()

	/**
	 * With approval, a new registration waits for a game master and takes no lock.
	 *
	 * @return void
	 */
	public function testWithApprovalANewRegistrationIsPending(): void {
		$this->event(capacity: 3, approval: true);

		$saved = $this->signUp(player: self::KAREL, uid: 'karel');

		$this->assertSame('pending', $this->registration($saved['id'])['status']);
		$this->assertSame([], $this->locks->acquired);
	}//end testWithApprovalANewRegistrationIsPending()

	/**
	 * A game master's accept gives a free place, or the waiting list when there is none.
	 *
	 * @return void
	 */
	public function testAGameMasterAcceptsWhenAPlaceIsFreeAndWaitlistsWhenNot(): void {
		$this->event(capacity: 1, approval: true);
		$first = $this->signUp(player: self::ANNA, uid: 'anna');
		$second = $this->signUp(player: self::SANNE, uid: 'sanne');

		$this->signedIn = 'gm';
		$this->fetcher()->saveObject('registration', ['status' => 'accepted'], $first['id']);
		$this->fetcher()->saveObject('registration', ['status' => 'accepted'], $second['id']);

		$this->assertSame('accepted', $this->registration($first['id'])['status']);
		$this->assertSame('gm', $this->registration($first['id'])['decidedBy']);
		$this->assertSame('waitlisted', $this->registration($second['id'])['status']);
		$this->assertSame([], $this->locks->held);
	}//end testAGameMasterAcceptsWhenAPlaceIsFreeAndWaitlistsWhenNot()

	/**
	 * A declined registration is stamped and takes no place.
	 *
	 * @return void
	 */
	public function testADeclinedRegistrationIsStampedAndTakesNoPlace(): void {
		$this->event(capacity: 1, approval: true);
		$karel = $this->signUp(player: self::KAREL, uid: 'karel');

		$this->fetcher()->saveObject('registration', ['status' => 'declined'], $karel['id']);
		$anna = $this->signUp(player: self::ANNA, uid: 'anna');
		$this->fetcher()->saveObject('registration', ['status' => 'accepted'], $anna['id']);

		$this->assertSame('declined', $this->registration($karel['id'])['status']);
		$this->assertSame('gm', $this->registration($karel['id'])['decidedBy']);
		$this->assertSame('accepted', $this->registration($anna['id'])['status']);
	}//end testADeclinedRegistrationIsStampedAndTakesNoPlace()

	/**
	 * Two sign-ups for the last place at the same moment: one is accepted, the other waitlisted.
	 *
	 * @return void
	 */
	public function testTheLastPlaceGoesToOneOfTwoSimultaneousSignUps(): void {
		$this->event(capacity: 3, approval: false, players: [self::HAND_1, self::HAND_2]);
		$sanneRequest = $this->listener(request: 'sanne');
		$pieterRequest = $this->listener(request: 'pieter', lockAttempts: 2);

		$sanne = new InMemoryObjectEntity(schema: 'registration', data: ['event' => self::EVENT, 'player' => self::SANNE, 'submittedAt' => '2026-10-01T10:00:00+00:00'], uuid: 'e0000000-0000-4000-8000-000000000011');
		$pieter = new InMemoryObjectEntity(schema: 'registration', data: ['event' => self::EVENT, 'player' => self::PIETER, 'submittedAt' => '2026-10-01T10:00:00+00:00'], uuid: 'e0000000-0000-4000-8000-000000000012');
		$sanneWrite = new ObjectCreatingEvent($sanne);
		$pieterWrite = new ObjectCreatingEvent($pieter);

		// Both pre-write handlers run before either registration is stored.
		$sanneRequest->handle($sanneWrite);
		$pieterRequest->handle($pieterWrite);
		$this->store->seed('registration', array_merge($sanne->getObject(), $sanneWrite->getModifiedData(), ['id' => $sanne->getUuid()]));
		$sanneRequest->handle(new ObjectCreatedEvent(new InMemoryObjectEntity(schema: 'registration', data: $this->registration((string)$sanne->getUuid()), uuid: $sanne->getUuid())));

		$this->assertSame('accepted', $sanneWrite->getModifiedData()['status']);
		$this->assertSame('waitlisted', $pieterWrite->getModifiedData()['status']);
		$this->assertSame([], $this->locks->held);
	}//end testTheLastPlaceGoesToOneOfTwoSimultaneousSignUps()

	/**
	 * An accepted registration with a character puts the character in the event.
	 *
	 * @return void
	 */
	public function testAnAcceptedRegistrationPutsItsCharacterInTheEvent(): void {
		$this->signedIn = 'anna';
		$this->signUp(player: self::ANNA, uid: 'anna', character: self::MIRELA);

		$this->assertSame([self::MIRELA], $this->store->objects['event'][self::EVENT]['players']);
	}//end testAnAcceptedRegistrationPutsItsCharacterInTheEvent()

	/**
	 * Leaving accepted takes the character out and gives the place to the oldest waitlisted.
	 *
	 * @return void
	 */
	public function testLeavingAcceptedRemovesTheCharacterAndPromotesTheOldestWaitlisted(): void {
		$this->event(capacity: 1, approval: false, players: [self::MIRELA]);
		$this->seedRegistration(id: 'e0000000-0000-4000-8000-000000000021', status: 'accepted', character: self::MIRELA, at: '2026-09-01T10:00:00+00:00');
		$this->seedRegistration(id: 'e0000000-0000-4000-8000-000000000022', status: 'waitlisted', character: self::VENN, at: '2026-09-03T10:00:00+00:00');
		$this->seedRegistration(id: 'e0000000-0000-4000-8000-000000000023', status: 'waitlisted', character: self::TOMAS, at: '2026-09-02T10:00:00+00:00');

		$this->fetcher()->saveObject('registration', ['status' => 'cancelled'], 'e0000000-0000-4000-8000-000000000021');

		$this->assertSame('accepted', $this->registration('e0000000-0000-4000-8000-000000000023')['status'], 'the oldest waitlisted gets the place');
		$this->assertSame('larpinq', $this->registration('e0000000-0000-4000-8000-000000000023')['decidedBy']);
		$this->assertSame('waitlisted', $this->registration('e0000000-0000-4000-8000-000000000022')['status']);
		$this->assertSame([self::TOMAS], array_values($this->store->objects['event'][self::EVENT]['players']));
		$this->assertSame([], $this->locks->held);
	}//end testLeavingAcceptedRemovesTheCharacterAndPromotesTheOldestWaitlisted()

	/**
	 * A player brings one of their own active characters of the event's world, nothing else.
	 *
	 * @return void
	 */
	public function testAPlayerChoosesOnlyTheirOwnActiveCharacterOfTheWorld(): void {
		$this->event(capacity: 3, approval: true);
		$anna = $this->signUp(player: self::ANNA, uid: 'anna');
		$this->signedIn = 'anna';

		foreach ([self::HARROW => 'not hers and retired', self::VENN => 'not hers', self::FAR_AWAY => 'another world'] as $character => $why) {
			try {
				$this->fetcher()->saveObject('registration', ['character' => $character], $anna['id']);
				$this->fail('Anna may not bring a character that is ' . $why);
			} catch (\RuntimeException $e) {
				$this->assertStringContainsString('Refused', $e->getMessage());
			}
		}

		$this->fetcher()->saveObject('registration', ['character' => self::MIRELA], $anna['id']);
		$this->assertSame(self::MIRELA, $this->registration($anna['id'])['character']);
	}//end testAPlayerChoosesOnlyTheirOwnActiveCharacterOfTheWorld()
}//end class
