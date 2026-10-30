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
		$fetcher = new RegisterObjectFetcher($container, $apps, $config, new NullLogger());
		$service = new RegistrationService($fetcher, $this->createMock(ILockingProvider::class), new NullLogger(), 1, 0);

		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(static fn (string $who, string $group): bool => $gameMaster === true && $group === Application::GM_GROUP);
		$groups->method('isAdmin')->willReturn(false);

		return new RegistrationChoicesController('larpinq', $this->createMock(IRequest::class), new TicketChoiceService($fetcher, $service, new NullLogger()), $fetcher, $session, $groups);
	}//end controllerAs()

	/**
	 * The player of the registration and a game master get the offer; another player does not.
	 *
	 * @return void
	 */
	public function testTheOfferIsForThePlayerAndGameMasters(): void {
		$own = $this->controllerAs(uid: 'anna')->offer(id: self::ANNAS, code: '');
		$this->assertSame(200, $own->getStatus());
		$this->assertSame(['Player'], array_column($own->getData()['ticketTypes'], 'name'));
		$this->assertSame('none', $own->getData()['code']);

		$this->assertSame(200, $this->controllerAs(uid: 'gerrit', gameMaster: true)->offer(id: self::ANNAS, code: '')->getStatus());
		$this->assertSame(403, $this->controllerAs(uid: 'pieter')->offer(id: self::ANNAS, code: '')->getStatus());
		$this->assertSame(401, $this->controllerAs(uid: null)->offer(id: self::ANNAS, code: '')->getStatus());
		$this->assertSame(404, $this->controllerAs(uid: 'anna')->offer(id: 'e0000000-0000-4000-8000-000000000099', code: '')->getStatus());
	}//end testTheOfferIsForThePlayerAndGameMasters()

	/**
	 * The counts are for game masters only.
	 *
	 * @return void
	 */
	public function testTheCountsAreForGameMasters(): void {
		$counts = $this->controllerAs(uid: 'gerrit', gameMaster: true)->counts(id: self::EVENT);
		$this->assertSame(200, $counts->getStatus());
		$this->assertSame([1], array_column($counts->getData()['ticketTypes'], 'count'));

		$this->assertSame(403, $this->controllerAs(uid: 'anna')->counts(id: self::EVENT)->getStatus());
		$this->assertSame(401, $this->controllerAs(uid: null)->counts(id: self::EVENT)->getStatus());
	}//end testTheCountsAreForGameMasters()
}//end class
