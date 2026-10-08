<?php

/**
 * Tests for ticket types, options and codes on a registration
 * (registration-ticket-types-and-options REQ-RTO-001 to REQ-RTO-006).
 *
 * Every save runs through larpinq's real RegistrationListener,
 * RegistrationService, TicketChoiceService and RegisterObjectFetcher over an
 * in-memory OpenRegister that dispatches OpenRegister's own pre- and
 * post-write events.
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

use DateTimeImmutable;
use OCA\Larpinq\Listener\RegistrationListener;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationCharacterCheck;
use OCA\Larpinq\Service\RegistrationService;
use OCA\Larpinq\Service\RegistrationWriteCheck;
use OCA\Larpinq\Service\TicketChoiceService;
use OCA\Larpinq\Tests\Unit\Support\InMemoryOpenRegister;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use RuntimeException;

/**
 * REQ-RTO-001 to REQ-RTO-006.
 */
class TicketChoiceServiceTest extends TestCase {

	private const EVENT = 'a0000000-0000-4000-8000-000000000001';
	private const OTHER_EVENT = 'a0000000-0000-4000-8000-000000000002';
	private const ANNA = 'c0000000-0000-4000-8000-000000000001';
	private const SANNE = 'c0000000-0000-4000-8000-000000000003';
	private const JORIS = 'c0000000-0000-4000-8000-000000000005';
	private const EARLY = 'f0000000-0000-4000-8000-000000000001';
	private const PLAYER = 'f0000000-0000-4000-8000-000000000002';
	private const CREW = 'f0000000-0000-4000-8000-000000000003';
	private const CREW_FRIENDS = 'f0000000-0000-4000-8000-000000000004';
	private const ELSEWHERE_TICKET = 'f0000000-0000-4000-8000-000000000005';
	private const DOLLAR_TICKET = 'f0000000-0000-4000-8000-000000000006';
	private const MEAT = 'f1000000-0000-4000-8000-000000000001';
	private const VEGAN = 'f1000000-0000-4000-8000-000000000002';
	private const LANTERN = 'f2000000-0000-4000-8000-000000000001';
	private const OLD_CODE = 'f2000000-0000-4000-8000-000000000002';

	private InMemoryOpenRegister $store;

	private string $signedIn = 'anna';

	private ?RegistrationService $service = null;

	/**
	 * "Winter Court 2026" with the ticket types, options and code of the design.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = new InMemoryOpenRegister();
		$this->store->seed('event', ['id' => self::EVENT, 'name' => 'Winter Court 2026', 'capacity' => 100, 'approvalRequired' => false, 'players' => []]);
		$this->store->seed('event', ['id' => self::OTHER_EVENT, 'name' => 'Spring Fair', 'players' => []]);
		foreach ([self::ANNA => 'anna', self::SANNE => 'sanne', self::JORIS => 'joris'] as $id => $uid) {
			$this->store->seed('player', ['id' => $id, 'name' => ucfirst($uid), 'userUid' => $uid]);
		}

		$this->ticket(id: self::EARLY, name: 'Player early bird', role: 'player', amount: 8500, extra: ['saleUntil' => $this->moment(days: 30)]);
		$this->ticket(id: self::PLAYER, name: 'Player', role: 'player', amount: 11000);
		$this->ticket(id: self::CREW, name: 'Crew', role: 'crew', amount: 4500, extra: ['placeLimit' => 20]);
		$this->ticket(id: self::CREW_FRIENDS, name: 'Crew friends', role: 'crew', amount: 3000, extra: ['hidden' => true]);
		$this->ticket(id: self::ELSEWHERE_TICKET, name: 'Spring player', role: 'player', amount: 2000, event: self::OTHER_EVENT);
		$this->ticket(id: self::DOLLAR_TICKET, name: 'Visitor', role: 'other', amount: 1000, extra: ['currency' => 'USD']);
		$this->store->seed('registrationoption', ['id' => self::MEAT, 'event' => self::EVENT, 'name' => 'Full catering, meat', 'category' => 'meal', 'amount' => 3500, 'currency' => 'EUR']);
		$this->store->seed('registrationoption', ['id' => self::VEGAN, 'event' => self::EVENT, 'name' => 'Full catering, vegan', 'category' => 'meal', 'amount' => 3500, 'currency' => 'EUR', 'placeLimit' => 12]);
		$this->store->seed('accesscode', ['id' => self::LANTERN, 'event' => self::EVENT, 'code' => 'LANTERN', 'unlocks' => [self::CREW_FRIENDS], 'maxUses' => 5]);
		$this->store->seed('accesscode', ['id' => self::OLD_CODE, 'event' => self::EVENT, 'code' => 'BYGONE', 'unlocks' => [self::CREW_FRIENDS], 'validUntil' => $this->moment(days: -1)]);

		$this->store->listeners = [$this->listener()];
	}//end setUp()

	/**
	 * A ticket type of an event.
	 *
	 * @param string $id The uuid.
	 * @param string $name The name.
	 * @param string $role The role.
	 * @param int $amount The listed price in cents.
	 * @param array<string, mixed> $extra Further fields.
	 * @param string $event The event.
	 *
	 * @return void
	 */
	private function ticket(string $id, string $name, string $role, int $amount, array $extra = [], string $event = self::EVENT): void {
		$this->store->seed('tickettype', array_merge(['id' => $id, 'event' => $event, 'name' => $name, 'role' => $role, 'amount' => $amount, 'currency' => 'EUR', 'hidden' => false], $extra));
	}//end ticket()

	/**
	 * A moment relative to now.
	 *
	 * @param int $days Days from now (negative is past).
	 *
	 * @return string The moment.
	 */
	private function moment(int $days): string {
		return (new DateTimeImmutable())->modify(sprintf('%+d days', $days))->format(DATE_ATOM);
	}//end moment()

	/**
	 * A real fetcher over the store.
	 *
	 * @return RegisterObjectFetcher The fetcher.
	 */
	private function fetcher(): RegisterObjectFetcher {
		$store = $this->store;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\OpenRegister\Service\ObjectService' ? $store : throw new RuntimeException('not bound: ' . $id))
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => (str_ends_with($key, '_register') === true ? '3' : (str_ends_with($key, '_schema') === true ? substr($key, 0, -strlen('_schema')) : $default))
		);

		return new RegisterObjectFetcher($container, $apps, $config, new NullLogger());
	}//end fetcher()

	/**
	 * The real listener with the real services.
	 *
	 * @return RegistrationListener The listener.
	 */
	private function listener(): RegistrationListener {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($key === 'registration_schema' ? 'registration' : $default)
		);
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
		$locking = $this->createMock(ILockingProvider::class);
		$this->service = new RegistrationService($this->fetcher(), $locking, new NullLogger(), 1, 0);

		return new RegistrationListener(
			$config,
			$this->service,
			new RegistrationWriteCheck(
				new RegistrationCharacterCheck($this->fetcher()),
				new TicketChoiceService($this->fetcher(), $this->service, new NullLogger())
			),
			$session,
			$l10n,
			new NullLogger()
		);
	}//end listener()

	/**
	 * A registration for a player, with choices.
	 *
	 * @param string $player The player.
	 * @param array<string, mixed> $choices Ticket type, options, code, or a client's own lines.
	 *
	 * @return array<string, mixed> The stored registration.
	 */
	private function register(string $player, array $choices = []): array {
		return $this->fetcher()->saveObjectWithAppAuthority('registration', array_merge(['event' => self::EVENT, 'player' => $player, 'submittedAt' => '2026-10-01T10:00:00+00:00'], $choices));
	}//end register()

	/**
	 * The refusal of a save, or a failure when it was stored.
	 *
	 * @param callable $save The save.
	 * @param string $reason The expected reason.
	 *
	 * @return void
	 */
	private function assertRefused(callable $save, string $reason): void {
		try {
			$save();
		} catch (RuntimeException $e) {
			$this->assertStringContainsString($reason, $e->getMessage());
			return;
		}

		$this->fail('the save must be refused: ' . $reason);
	}//end assertRefused()

	/**
	 * Anna picks "Player": the registration records the ticket type with a line of EUR 110.
	 *
	 * @return void
	 */
	public function testAnnaPicksAPlayerTicketAndItsListedPriceIsRecorded(): void {
		$saved = $this->register(player: self::ANNA, choices: ['ticketType' => self::PLAYER]);

		$this->assertSame(
			[['kind' => 'ticket', 'ref' => self::PLAYER, 'name' => 'Player', 'amount' => 11000, 'currency' => 'EUR']],
			$this->store->objects['registration'][$saved['id']]['lines']
		);
	}//end testAnnaPicksAPlayerTicketAndItsListedPriceIsRecorded()

	/**
	 * Early bird and vegan catering give one line each.
	 *
	 * @return void
	 */
	public function testOptionsAddALineEach(): void {
		$saved = $this->register(player: self::ANNA, choices: ['ticketType' => self::EARLY, 'options' => [self::VEGAN]]);

		$lines = $this->store->objects['registration'][$saved['id']]['lines'];
		$this->assertSame(['ticket', 'option'], array_column($lines, 'kind'));
		$this->assertSame([8500, 3500], array_column($lines, 'amount'));
		$this->assertSame('Full catering, vegan', $lines[1]['name']);
	}//end testOptionsAddALineEach()

	/**
	 * A client that sends its own price line gets the listed price, on create and on update.
	 *
	 * @return void
	 */
	public function testAClientsOwnPriceIsIgnored(): void {
		$cheat = [['kind' => 'ticket', 'ref' => self::PLAYER, 'name' => 'Player', 'amount' => 100, 'currency' => 'EUR']];
		$saved = $this->register(player: self::ANNA, choices: ['ticketType' => self::PLAYER, 'lines' => $cheat]);
		$this->assertSame(11000, $this->store->objects['registration'][$saved['id']]['lines'][0]['amount']);

		$this->fetcher()->saveObject('registration', ['lines' => $cheat], $saved['id']);
		$this->assertSame(11000, $this->store->objects['registration'][$saved['id']]['lines'][0]['amount']);
	}//end testAClientsOwnPriceIsIgnored()

	/**
	 * A price change after the choice applies to new choices only.
	 *
	 * @return void
	 */
	public function testALaterPriceChangeKeepsTheChosenPrice(): void {
		$saved = $this->register(player: self::ANNA, choices: ['ticketType' => self::PLAYER]);
		$this->store->objects['tickettype'][self::PLAYER]['amount'] = 12500;

		$this->fetcher()->saveObject('registration', ['options' => [self::MEAT]], $saved['id']);

		$lines = $this->store->objects['registration'][$saved['id']]['lines'];
		$this->assertSame([11000, 3500], array_column($lines, 'amount'));
	}//end testALaterPriceChangeKeepsTheChosenPrice()

	/**
	 * Outside its sale window a ticket type is refused, and not offered.
	 *
	 * @return void
	 */
	public function testTooLateForTheEarlyBird(): void {
		$this->store->objects['tickettype'][self::EARLY]['saleUntil'] = $this->moment(days: -1);

		$this->assertRefused(fn () => $this->register(player: self::ANNA, choices: ['ticketType' => self::EARLY]), 'This ticket type is not on sale now.');

		$saved = $this->register(player: self::ANNA);
		$offer = (new TicketChoiceService($this->fetcher(), $this->service, new NullLogger()))->offer(registration: $this->store->objects['registration'][$saved['id']], code: '');
		$offered = array_column($offer['ticketTypes'], 'name');
		$this->assertNotContains('Player early bird', $offered);
		$this->assertContains('Player', $offered);
		$this->assertNotContains('Crew friends', $offered, 'a hidden ticket type is not offered without a code');
		$this->assertNotContains('Spring player', $offered, 'another event\'s ticket type is not offered');
	}//end testTooLateForTheEarlyBird()

	/**
	 * A hidden ticket type needs a valid code; "LANTERN" unlocks "Crew friends" and is recorded.
	 *
	 * @return void
	 */
	public function testACrewFriendUsesTheCode(): void {
		$this->assertRefused(fn () => $this->register(player: self::SANNE, choices: ['ticketType' => self::CREW_FRIENDS]), 'This ticket type needs a code that unlocks it.');
		$this->assertRefused(fn () => $this->register(player: self::SANNE, choices: ['ticketType' => self::CREW_FRIENDS, 'code' => 'BYGONE']), 'This code has expired.');
		$this->assertRefused(fn () => $this->register(player: self::SANNE, choices: ['code' => 'NOPE']), 'This code is not known for this event.');

		$saved = $this->register(player: self::SANNE, choices: ['ticketType' => self::CREW_FRIENDS, 'code' => 'lantern']);

		$row = $this->store->objects['registration'][$saved['id']];
		$this->assertSame(self::LANTERN, $row['accessCode']);
		$this->assertSame(3000, $row['lines'][0]['amount']);

		$offer = (new TicketChoiceService($this->fetcher(), $this->service, new NullLogger()))->offer(registration: $row, code: 'LANTERN');
		$this->assertSame('valid', $offer['code']);
		$this->assertContains('Crew friends', array_column($offer['ticketTypes'], 'name'));
	}//end testACrewFriendUsesTheCode()

	/**
	 * A code stops working after its last use.
	 *
	 * @return void
	 */
	public function testACodeIsUsedUp(): void {
		for ($use = 1; $use <= 5; $use++) {
			$this->store->seed('registration', ['id' => sprintf('e0000000-0000-4000-8000-%012d', $use), 'event' => self::EVENT, 'status' => 'accepted', 'accessCode' => self::LANTERN]);
		}

		$this->assertRefused(fn () => $this->register(player: self::SANNE, choices: ['ticketType' => self::CREW_FRIENDS, 'code' => 'LANTERN']), 'This code has been used up.');
	}//end testACodeIsUsedUp()

	/**
	 * A ticket type or option of another event is refused, and so are two currencies.
	 *
	 * @return void
	 */
	public function testChoicesFromAnotherEventOrInTwoCurrenciesAreRefused(): void {
		$this->assertRefused(fn () => $this->register(player: self::ANNA, choices: ['ticketType' => self::ELSEWHERE_TICKET]), 'This ticket type is not offered for this event.');
		$this->assertRefused(fn () => $this->register(player: self::ANNA, choices: ['options' => ['f1000000-0000-4000-8000-000000000099']]), 'This option is not offered for this event.');
		$this->assertRefused(fn () => $this->register(player: self::ANNA, choices: ['ticketType' => self::DOLLAR_TICKET, 'options' => [self::MEAT]]), 'All choices of a registration must be in one currency.');
	}//end testChoicesFromAnotherEventOrInTwoCurrenciesAreRefused()

	/**
	 * With all 20 crew places taken, Joris's crew registration is waitlisted although the event has room.
	 *
	 * @return void
	 */
	public function testCrewIsFull(): void {
		for ($place = 1; $place <= 20; $place++) {
			$this->store->seed('registration', ['id' => sprintf('e1000000-0000-4000-8000-%012d', $place), 'event' => self::EVENT, 'status' => 'accepted', 'ticketType' => self::CREW]);
		}

		$joris = $this->register(player: self::JORIS, choices: ['ticketType' => self::CREW]);
		$anna = $this->register(player: self::ANNA, choices: ['ticketType' => self::PLAYER]);

		$this->assertSame('waitlisted', $this->store->objects['registration'][$joris['id']]['status']);
		$this->assertSame('accepted', $this->store->objects['registration'][$anna['id']]['status']);
	}//end testCrewIsFull()

	/**
	 * An option with a place limit is refused once it is full.
	 *
	 * @return void
	 */
	public function testAFullOptionIsRefused(): void {
		for ($place = 1; $place <= 12; $place++) {
			$this->store->seed('registration', ['id' => sprintf('e2000000-0000-4000-8000-%012d', $place), 'event' => self::EVENT, 'status' => 'accepted', 'options' => [self::VEGAN]]);
		}

		$this->assertRefused(fn () => $this->register(player: self::ANNA, choices: ['options' => [self::VEGAN]]), 'This option is full.');
		$saved = $this->register(player: self::ANNA, choices: ['options' => [self::MEAT]]);
		$this->assertSame(self::MEAT, $this->store->objects['registration'][$saved['id']]['lines'][0]['ref']);
	}//end testAFullOptionIsRefused()

	/**
	 * The kitchen plans: counts per ticket type and option over accepted registrations, no money total.
	 *
	 * @return void
	 */
	public function testTheKitchenPlans(): void {
		$rows = [['accepted', [self::VEGAN]], ['accepted', [self::VEGAN]], ['accepted', [self::MEAT]], ['waitlisted', [self::VEGAN]]];
		foreach ($rows as $index => [$status, $options]) {
			$this->store->seed('registration', ['id' => sprintf('e3000000-0000-4000-8000-%012d', $index), 'event' => self::EVENT, 'status' => $status, 'ticketType' => self::PLAYER, 'options' => $options]);
		}

		$counts = (new TicketChoiceService($this->fetcher(), $this->service, new NullLogger()))->counts(eventId: self::EVENT);

		$options = array_column($counts['options'], 'count', 'name');
		$this->assertSame(2, $options['Full catering, vegan']);
		$this->assertSame(1, $options['Full catering, meat']);
		$tickets = array_column($counts['ticketTypes'], 'count', 'name');
		$this->assertSame(3, $tickets['Player']);
		$this->assertSame(0, $tickets['Crew']);
		$this->assertArrayNotHasKey('total', $counts);
		foreach (array_merge($counts['ticketTypes'], $counts['options']) as $row) {
			$this->assertArrayNotHasKey('amount', $row, 'the counts carry no money');
		}
	}//end testTheKitchenPlans()

	/**
	 * The registration larpinq stores, choices and price lines included, validates against the real merged schema.
	 *
	 * @return void
	 */
	public function testTheStoredRegistrationValidatesAgainstTheRegisterSchema(): void {
		$saved = $this->register(player: self::SANNE, choices: ['ticketType' => self::CREW_FRIENDS, 'options' => [self::VEGAN, self::MEAT], 'code' => 'LANTERN']);
		$row = $this->store->objects['registration'][$saved['id']];
		unset($row['id']);

		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		$schema = $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath)['components']['schemas']['registration'];
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation'], $schema['properties'][$name]['x-relation-filter']);
		}

		unset($schema['authorization'], $schema['configuration']);
		$result = (new Validator())->validate(json_decode((string)json_encode($row)), json_decode((string)json_encode($schema)));

		$this->assertTrue($result->isValid(), 'the stored registration must validate: ' . json_encode($row));
		$this->assertSame(3, count($row['lines']));
		$this->assertSame(self::LANTERN, $row['accessCode']);
	}//end testTheStoredRegistrationValidatesAgainstTheRegisterSchema()
}//end class
