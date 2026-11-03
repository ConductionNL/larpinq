<?php

/**
 * Tests for the ticket offer of a registration and the choice counts of an
 * event (registration-ticket-types-and-options REQ-RTO-002, REQ-RTO-003,
 * REQ-RTO-006): who may ask.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

require_once __DIR__ . '/../Support/InMemoryOpenRegister.php';

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\RegistrationChoicesController;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationService;
use OCA\Larpinq\Service\TicketChoiceService;
use OCA\Larpinq\Tests\Unit\Support\InMemoryOpenRegister;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Who may read an offer and the counts.
 */
class RegistrationChoicesControllerTest extends TestCase {

	private const EVENT = 'a0000000-0000-4000-8000-000000000001';
	private const ANNAS = 'e0000000-0000-4000-8000-000000000001';

	private InMemoryOpenRegister $store;

	/**
	 * One event with one ticket type and Anna's registration.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = new InMemoryOpenRegister();
		$this->store->seed('event', ['id' => self::EVENT, 'name' => 'Winter Court 2026']);
		$this->store->seed('tickettype', ['id' => 'f0000000-0000-4000-8000-000000000002', 'event' => self::EVENT, 'name' => 'Player', 'role' => 'player', 'amount' => 11000, 'currency' => 'EUR', 'hidden' => false]);
		$this->store->seed('registration', ['id' => self::ANNAS, 'event' => self::EVENT, 'playerUid' => 'anna', 'submitterUid' => 'anna', 'status' => 'accepted', 'ticketType' => 'f0000000-0000-4000-8000-000000000002']);
	}//end setUp()

	/**
	 * The controller as a user.
	 *
	 * @param string|null $uid The user, or null when not signed in.
	 * @param bool $gameMaster Whether the user is a game master.
	 *
	 * @return RegistrationChoicesController The controller.
	 */
	private function controllerAs(?string $uid, bool $gameMaster = false): RegistrationChoicesController {
		$store = $this->store;
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\OpenRegister\Service\ObjectService' ? $store : throw new RuntimeException('not bound: ' . $id))
		);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => (str_ends_with($key, '_register') === true ? '3' : (str_ends_with($key, '_schema') === true ? substr($key, 0, -strlen('_schema')) : $default))
		);
		$fetcher = new RegisterObjectFetcher($container, $apps, $config, new NullLogger());
		$service = new RegistrationService($fetcher, $this->createMock(originalClassName: ILockingProvider::class), new NullLogger(), 1, 0);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(static fn (string $who, string $group): bool => $gameMaster === true && $group === Application::GM_GROUP);
		$groups->method('isAdmin')->willReturn(false);

		return new RegistrationChoicesController($this->createMock(originalClassName: IRequest::class), new TicketChoiceService($fetcher, $service, new NullLogger()), $fetcher, new CallerAccess($session, $groups));
	}//end controllerAs()

	/**
	 * The player of the registration and a game master get the offer; another player does not.
	 *
	 * @return void
	 */
	public function testTheOfferIsForThePlayerAndGameMasters(): void {
		$own = $this->controllerAs(uid: 'anna')->offer(id: self::ANNAS, code: '');
		$this->assertSame(expected: 200, actual: $own->getStatus());
		$this->assertSame(expected: ['Player'], actual: array_column($own->getData()['ticketTypes'], 'name'));
		$this->assertSame(expected: 'none', actual: $own->getData()['code']);

		$this->assertSame(expected: 200, actual: $this->controllerAs(uid: 'gerrit', gameMaster: true)->offer(id: self::ANNAS, code: '')->getStatus());
		$this->assertSame(expected: 403, actual: $this->controllerAs(uid: 'pieter')->offer(id: self::ANNAS, code: '')->getStatus());
		$this->assertSame(expected: 401, actual: $this->controllerAs(uid: null)->offer(id: self::ANNAS, code: '')->getStatus());
		$this->assertSame(expected: 404, actual: $this->controllerAs(uid: 'anna')->offer(id: 'e0000000-0000-4000-8000-000000000099', code: '')->getStatus());
	}//end testTheOfferIsForThePlayerAndGameMasters()

	/**
	 * The counts are for game masters only.
	 *
	 * @return void
	 */
	public function testTheCountsAreForGameMasters(): void {
		$counts = $this->controllerAs(uid: 'gerrit', gameMaster: true)->counts(id: self::EVENT);
		$this->assertSame(expected: 200, actual: $counts->getStatus());
		$this->assertSame(expected: [1], actual: array_column($counts->getData()['ticketTypes'], 'count'));

		$this->assertSame(expected: 403, actual: $this->controllerAs(uid: 'anna')->counts(id: self::EVENT)->getStatus());
		$this->assertSame(expected: 401, actual: $this->controllerAs(uid: null)->counts(id: self::EVENT)->getStatus());
	}//end testTheCountsAreForGameMasters()
}//end class
