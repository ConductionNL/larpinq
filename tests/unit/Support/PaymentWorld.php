<?php

/**
 * Larpinq payment test world
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

require_once __DIR__ . '/RegisterAnswers.php';

require_once __DIR__ . '/InMemoryOpenRegister.php';

use DateTimeImmutable;
use OCA\Larpinq\Listener\PaymentRequestListener;
use OCA\Larpinq\Listener\RegistrationListener;
use OCA\Larpinq\Service\CancellationPolicy;
use OCA\Larpinq\Service\PaymentFollowUp;
use OCA\Larpinq\Service\PaymentLeaf;
use OCA\Larpinq\Service\PaymentRequestBuilder;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationChangeService;
use OCA\Larpinq\Service\RegistrationCharacterCheck;
use OCA\Larpinq\Service\RegistrationPaymentService;
use OCA\Larpinq\Service\RegistrationService;
use OCA\Larpinq\Service\RegistrationSettlement;
use OCA\Larpinq\Service\RegistrationWriteCheck;
use OCA\Larpinq\Service\TicketChoiceService;
use OCA\Larpinq\Service\TransferOffers;
use OCA\OpenRegister\Service\Integration\IntegrationProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * A fake of shillinq's `shillinq-payment-requests` leaf
 * (lib/Integration/PaymentRequestLeafProvider.php at shillinq development
 * d4d3f14f): create() refuses without the payment.request action, builds the
 * request exactly as shillinq does and answers with its id; list() projects
 * the requests on one object.
 */
class FakePaymentLeaf implements IntegrationProvider {

	/**
	 * Whether the signed-in user carries payment.request.
	 *
	 * @var boolean
	 */
	public bool $mayRequest = true;

	/** @var bool Whether fail list. */
	public bool $failList = false;

	/**
	 * The requests shillinq would have saved, by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $requests = [];

	/**
	 * The arguments of every create() call.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $creates = [];

	/** @var int The next. */
	private int $next = 1;

	/**
	 * The provider id.
	 *
	 * @return string The provider id.
	 */
	public function getId(): string {
		return 'shillinq-payment-requests';
	}

	/**
	 * The provider label.
	 *
	 * @return string The provider label.
	 */
	public function getLabel(): string {
		return 'Payment requests';
	}

	/**
	 * The provider icon.
	 *
	 * @return string The provider icon.
	 */
	public function getIcon(): string {
		return 'CreditCardOutline';
	}

	/**
	 * The provider group.
	 *
	 * @return ?string The provider group.
	 */
	public function getGroup(): ?string {
		return 'Finance';
	}

	/**
	 * The app the provider needs.
	 *
	 * @return ?string The app the provider needs.
	 */
	public function getRequiredApp(): ?string {
		return 'shillinq';
	}

	/**
	 * Where the provider keeps its rows.
	 *
	 * @return string Where the provider keeps its rows.
	 */
	public function getStorageStrategy(): string {
		return 'app-local';
	}

	/**
	 * The OpenConnector source; none.
	 *
	 * @return ?string The OpenConnector source; none.
	 */
	public function getOpenConnectorSource(): ?string {
		return null;
	}

	/**
	 * Whether the provider is enabled.
	 *
	 * @return bool True when the provider is enabled.
	 */
	public function isEnabled(): bool {
		return true;
	}

	/**
	 * The permission the provider needs.
	 *
	 * @return ?string The permission the provider needs.
	 */
	public function requiresPermission(): ?string {
		return 'payment.request';
	}

	/**
	 * The authentication the provider needs; none.
	 *
	 * @return array The authentication the provider needs; none.
	 */
	public function authRequirements(): array {
		return [];
	}

	/**
	 * The payments of an object.
	 *
	 * @param string $register The register.
	 * @param string $schema The schema.
	 * @param string $objectId The object id.
	 * @param array $filters The filters.
	 *
	 * @return array The payments of an object.
	 */
	public function list(string $register, string $schema, string $objectId, array $filters = []): array {
		if ($this->failList === true) {
			throw new RuntimeException('shillinq is down');
		}

		$items = [];
		foreach ($this->requests as $request) {
			if ($request['subject']['id'] === $objectId && $request['subject']['register'] === $register && $request['subject']['schema'] === $schema) {
				$items[] = [
					'id' => $request['id'],
					'state' => $request['state'],
					'amount' => (float)$request['amount'],
					'currency' => $request['currency'],
					'requestType' => $request['requestType'],
					'description' => $request['description'],
					'dueAt' => (string)($request['dueAt'] ?? ''),
					'paymentLink' => (string)($request['paymentLink'] ?? ''),
				];
			}
		}

		return ['items' => $items, 'total' => count($items), 'nextCursor' => null, 'fee' => null];
	}

	/**
	 * One payment of an object.
	 *
	 * @param string $register The register.
	 * @param string $schema The schema.
	 * @param string $objectId The object id.
	 * @param string $entityId The entity id.
	 *
	 * @return array One payment of an object.
	 */
	public function get(string $register, string $schema, string $objectId, string $entityId): array {
		throw new RuntimeException('not used');
	}

	/**
	 * Record a payment request for an object.
	 *
	 * @param string $register The register.
	 * @param string $schema The schema.
	 * @param string $objectId The object id.
	 * @param array $payload The payload.
	 *
	 * @return array Record a payment request for an object.
	 */
	public function create(string $register, string $schema, string $objectId, array $payload): array {
		if ($this->mayRequest === false) {
			throw new RuntimeException('403 Raising a payment request needs the payment.request action.');
		}

		$this->creates[] = ['register' => $register, 'schema' => $schema, 'objectId' => $objectId, 'payload' => $payload];
		$request = [
			'subjectKind' => 'object',
			'subject' => ['type' => (string)($payload['subjectType'] ?? 'object'), 'register' => $register, 'schema' => $schema, 'id' => $objectId],
			'requestType' => (string)($payload['requestType'] ?? ''),
			'amount' => $payload['amount'] ?? null,
			'currency' => (string)($payload['currency'] ?? 'EUR'),
			'description' => (string)($payload['description'] ?? ''),
			'state' => 'pending',
			'paymentGateway' => (string)($payload['paymentGateway'] ?? 'mollie'),
			'requestedBy' => 'gm',
		];
		if (isset($payload['debtor']) === true && is_array($payload['debtor']) === true) {
			$request['debtor'] = $payload['debtor'];
		}

		if ((string)($payload['dueAt'] ?? '') !== '') {
			$request['dueAt'] = (string)$payload['dueAt'];
		}

		$id = sprintf('f0000000-0000-4000-8000-%012d', $this->next++);
		$this->requests[$id] = array_merge($request, ['id' => $id, 'paymentLink' => 'https://pay.example.org/r/' . $id]);
		return $this->requests[$id];
	}

	/**
	 * Update a payment; not used here.
	 *
	 * @param string $register The register.
	 * @param string $schema The schema.
	 * @param string $objectId The object id.
	 * @param string $entityId The entity id.
	 * @param array $payload The payload.
	 *
	 * @return array Update a payment; not used here.
	 */
	public function update(string $register, string $schema, string $objectId, string $entityId, array $payload): array {
		throw new RuntimeException('not used');
	}

	/**
	 * Delete a payment; not used here.
	 *
	 * @param string $register The register.
	 * @param string $schema The schema.
	 * @param string $objectId The object id.
	 * @param string $entityId The entity id.
	 *
	 * @return void
	 */
	public function delete(string $register, string $schema, string $objectId, string $entityId): void {
		throw new RuntimeException('not used');
	}

	/**
	 * The provider health.
	 *
	 * @return array The provider health.
	 */
	public function health(): array {
		return ['status' => 'ok'];
	}
}

/**
 * One instance: the in-memory register with the registration listeners, the
 * fake leaf in OpenRegister's integration registry, a signed-in user and a clock.
 */
class PaymentWorld {

	public const EVENT = 'a0000000-0000-4000-8000-000000000001';
	public const ANNA = 'c0000000-0000-4000-8000-000000000001';
	public const SANNE = 'c0000000-0000-4000-8000-000000000003';
	public const PIETER = 'c0000000-0000-4000-8000-000000000004';
	public const TICKET = 'b1000000-0000-4000-8000-000000000001';
	public const MEALS = 'b2000000-0000-4000-8000-000000000001';

	/** @var InMemoryOpenRegister The store. */
	public InMemoryOpenRegister $store;

	/** @var FakePaymentLeaf The leaf. */
	public FakePaymentLeaf $leaf;

	/**
	 * Whether shillinq's leaf is registered.
	 *
	 * @var boolean
	 */
	public bool $shillinq = true;

	/** @var string The signed in. */
	public string $signedIn = 'gm';

	/** @var DateTimeImmutable The now. */
	public DateTimeImmutable $now;

	/**
	 * The typed events larpinq dispatched, in order.
	 *
	 * @var array<int, Event>
	 */
	public array $dispatched = [];

	/**
	 * The world for one test.
	 *
	 * @param TestCase $test The test.
	 */
	public function __construct(private TestCase $test) {
		$this->store = new InMemoryOpenRegister();
		$this->leaf = new FakePaymentLeaf();
		$this->now = new DateTimeImmutable('2026-10-15T10:00:00+00:00');
		$this->store->seed('event', [
			'id' => self::EVENT,
			'name' => 'Winter Court 2026',
			'paymentRequired' => true,
			'payBy' => '2026-11-20T00:00:00+00:00',
			'paymentCode' => 'WC26',
			'players' => [],
		]);
		foreach ([self::ANNA => 'Anna', self::SANNE => 'Sanne', self::PIETER => 'Pieter'] as $id => $name) {
			$this->store->seed('player', ['id' => $id, 'name' => $name, 'userUid' => strtolower($name)]);
		}

		$this->store->listeners = [$this->registrationListener(), $this->paymentListener()];
	}

	/**
	 * A registration, stored without running the listeners.
	 *
	 * @param string $id The registration id.
	 * @param string $player The player id.
	 * @param string $status The status.
	 * @param array<string, mixed> $more More fields.
	 *
	 * @return void
	 */
	public function seedRegistration(string $id, string $player, string $status, array $more = []): void {
		$this->store->seed('registration', array_merge([
			'id' => $id,
			'event' => self::EVENT,
			'player' => $player,
			'playerUid' => strtolower((string)$this->store->objects['player'][$player]['name']),
			'status' => $status,
			'submittedAt' => '2026-10-01T10:00:00+00:00',
			'lines' => [
				['kind' => 'ticket', 'ref' => self::TICKET, 'name' => 'Player', 'amount' => 8500, 'currency' => 'EUR'],
				['kind' => 'option', 'ref' => self::MEALS, 'name' => 'Meals', 'amount' => 3500, 'currency' => 'EUR'],
			],
		], $more));
	}

	/**
	 * A stored registration.
	 *
	 * @param string $id The registration id.
	 *
	 * @return array<string, mixed> The row.
	 */
	public function registration(string $id): array {
		return $this->store->objects['registration'][$id];
	}

	/**
	 * Update a registration as the signed-in user, through the listeners.
	 *
	 * @param string $id The registration id.
	 * @param array<string, mixed> $data The fields.
	 *
	 * @return array<string, mixed> The stored row.
	 */
	public function update(string $id, array $data): array {
		return $this->fetcher()->saveObjectWithAppAuthority('registration', $data, $id);
	}

	/**
	 * The object fetcher over the in-memory register.
	 *
	 * @return RegisterObjectFetcher The object fetcher over the in-memory register.
	 */
	public function fetcher(): RegisterObjectFetcher {
		$store = $this->store;
		$container = $this->test->getMockBuilder(ContainerInterface::class)->getMock();
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => RegisterAnswers::onlyService(id: $id, bound: 'OCA\OpenRegister\Service\ObjectService', service: $store)
		);
		$apps = $this->test->getMockBuilder(IAppManager::class)->getMock();
		$apps->method('getInstalledApps')->willReturn(['openregister']);

		return new RegisterObjectFetcher(container: $container, appManager: $apps, config: $this->config(), logger: new NullLogger());
	}

	/**
	 * The app config: register 3 and one schema per object type.
	 *
	 * @return IAppConfig The app config: register 3 and one schema per object type.
	 */
	public function config(): IAppConfig {
		$config = $this->test->getMockBuilder(IAppConfig::class)->getMock();
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => RegisterAnswers::registerConfig(key: $key, default: $default)
		);
		return $config;
	}

	/**
	 * OpenRegister's integration registry as the container hands it out, with
	 * shillinq's leaf in it while shillinq is installed.
	 *
	 * @return PaymentLeaf The leaf wrapper.
	 */
	public function paymentLeaf(): PaymentLeaf {
		$world = $this;
		$registry = new class($world) {
			/**
			 * The registry over the world.
			 *
			 * @param PaymentWorld $world The world.
			 */
			public function __construct(private PaymentWorld $world) {
			}

			/**
			 * The integration provider for an id.
			 *
			 * @param string $id The id.
			 *
			 * @return ?IntegrationProvider The integration provider for an id.
			 */
			public function get(string $id): ?IntegrationProvider {
				if ($this->world->shillinq === false || $id !== 'shillinq-payment-requests') {
					return null;
				}

				return $this->world->leaf;
			}
		};
		$container = $this->test->getMockBuilder(ContainerInterface::class)->getMock();
		$container->method('has')->willReturnCallback(static fn (string $id): bool => $id === PaymentLeaf::REGISTRY);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => RegisterAnswers::onlyService(id: $id, bound: PaymentLeaf::REGISTRY, service: $registry)
		);

		return new PaymentLeaf(container: $container, config: $this->config(), logger: new NullLogger());
	}

	/**
	 * The group manager.
	 *
	 * @return IGroupManager The group manager.
	 */
	public function groups(): IGroupManager {
		$groups = $this->test->getMockBuilder(IGroupManager::class)->getMock();
		$groups->method('isInGroup')->willReturnCallback(static fn (string $uid, string $group): bool => $uid === 'gm' && $group === 'gamemasters');
		$groups->method('isAdmin')->willReturn(false);
		return $groups;
	}

	/**
	 * The clock.
	 *
	 * @return ITimeFactory The clock.
	 */
	public function clock(): ITimeFactory {
		$clock = $this->test->getMockBuilder(ITimeFactory::class)->getMock();
		$clock->method('now')->willReturnCallback(fn (): DateTimeImmutable => $this->now);
		$clock->method('getTime')->willReturnCallback(fn (): int => $this->now->getTimestamp());
		return $clock;
	}

	/**
	 * The user manager.
	 *
	 * @return IUserManager The user manager.
	 */
	public function users(): IUserManager {
		$users = $this->test->getMockBuilder(IUserManager::class)->getMock();
		$users->method('get')->willReturnCallback(
			function (string $uid): ?IUser {
				$user = $this->test->getMockBuilder(IUser::class)->getMock();
				$user->method('getEMailAddress')->willReturn($uid . '@example.org');
				return $user;
			}
		);
		return $users;
	}

	/**
	 * The user session.
	 *
	 * @return IUserSession The user session.
	 */
	public function session(): IUserSession {
		$session = $this->test->getMockBuilder(IUserSession::class)->getMock();
		$session->method('getUser')->willReturnCallback(
			function (): ?IUser {
				if ($this->signedIn === '') {
					return null;
				}

				$user = $this->test->getMockBuilder(IUser::class)->getMock();
				$user->method('getUID')->willReturn($this->signedIn);
				return $user;
			}
		);
		return $session;
	}

	/**
	 * The payment service.
	 *
	 * @return RegistrationPaymentService The payment service.
	 */
	public function payments(): RegistrationPaymentService {
		$builder = new PaymentRequestBuilder(fetcher: $this->fetcher(), users: $this->users(), time: $this->clock(), logger: new NullLogger());
		return new RegistrationPaymentService(
			fetcher: $this->fetcher(),
			leaf: $this->paymentLeaf(),
			builder: $builder,
			groups: $this->groups(),
			logger: new NullLogger()
		);
	}

	/**
	 * The payment follow-up.
	 *
	 * @return PaymentFollowUp The payment follow-up.
	 */
	public function followUp(): PaymentFollowUp {
		return new PaymentFollowUp(fetcher: $this->fetcher(), leaf: $this->paymentLeaf(), logger: new NullLogger());
	}

	/**
	 * The payment request listener.
	 *
	 * @return PaymentRequestListener The payment request listener.
	 */
	public function paymentListener(): PaymentRequestListener {
		return new PaymentRequestListener(
			config: $this->config(),
			payments: $this->payments(),
			followUp: $this->followUp(),
			leaf: $this->paymentLeaf(),
			session: $this->session(),
			settlement: $this->settlement()
		);
	}

	/**
	 * The cancellation policy.
	 *
	 * @return CancellationPolicy The cancellation policy.
	 */
	public function policy(): CancellationPolicy {
		return new CancellationPolicy(groups: $this->groups());
	}

	/**
	 * The event dispatcher.
	 *
	 * @return IEventDispatcher The event dispatcher.
	 */
	public function dispatcher(): IEventDispatcher {
		$dispatcher = $this->test->getMockBuilder(IEventDispatcher::class)->getMock();
		$dispatcher->method('dispatchTyped')->willReturnCallback(function (Event $event): void {
			$this->dispatched[] = $event;
		});
		return $dispatcher;
	}

	/**
	 * The registration settlement.
	 *
	 * @return RegistrationSettlement The registration settlement.
	 */
	public function settlement(): RegistrationSettlement {
		$builder = new PaymentRequestBuilder(fetcher: $this->fetcher(), users: $this->users(), time: $this->clock(), logger: new NullLogger());
		return new RegistrationSettlement(
			fetcher: $this->fetcher(),
			policy: $this->policy(),
			builder: $builder,
			leaf: $this->paymentLeaf(),
			dispatcher: $this->dispatcher(),
			logger: new NullLogger()
		);
	}

	/**
	 * The registration change service.
	 *
	 * @return RegistrationChangeService The registration change service.
	 */
	public function changes(): RegistrationChangeService {
		return new RegistrationChangeService(fetcher: $this->fetcher(), policy: $this->policy(), time: $this->clock());
	}

	/**
	 * The transfer offers.
	 *
	 * @return TransferOffers The transfer offers.
	 */
	public function transfers(): TransferOffers {
		return new TransferOffers(fetcher: $this->fetcher(), policy: $this->policy(), time: $this->clock(), logger: new NullLogger());
	}

	/**
	 * The registration listener.
	 *
	 * @return RegistrationListener The registration listener.
	 */
	private function registrationListener(): RegistrationListener {
		$l10n = $this->test->getMockBuilder(IL10N::class)->getMock();
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$locks = $this->test->getMockBuilder(ILockingProvider::class)->getMock();
		$service = new RegistrationService(fetcher: $this->fetcher(), locking: $locks, logger: new NullLogger(), lockAttempts: 1, lockWaitMicros: 0);
		$check = new RegistrationWriteCheck(
			characterCheck: new RegistrationCharacterCheck(fetcher: $this->fetcher()),
			ticketChoices: new TicketChoiceService(fetcher: $this->fetcher(), registrations: $service, logger: new NullLogger())
		);

		return new RegistrationListener(
			config: $this->config(),
			service: $service,
			writeCheck: $check,
			session: $this->session(),
			l10n: $l10n,
			logger: new NullLogger()
		);
	}
}
