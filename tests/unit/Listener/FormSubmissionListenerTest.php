<?php

/**
 * Tests for the sign-up listener: a Forms submission on an event's sign-up
 * form becomes a registration (registration-intake-and-capacity REQ-RIC-001).
 *
 * The listener runs over larpinq's real RegisterObjectFetcher on an in-memory
 * OpenRegister, and receives Nextcloud Forms' FormSubmittedEvent in its real
 * shape.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

require_once __DIR__ . '/../Support/InMemoryOpenRegister.php';

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Larpinq\Listener\FormSubmissionListener;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Tests\Unit\Support\InMemoryOpenRegister;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * REQ-RIC-001.
 */
class FormSubmissionListenerTest extends TestCase {

	private const EVENT = 'a0000000-0000-4000-8000-000000000001';
	private const ANNA = 'c0000000-0000-4000-8000-000000000001';

	private InMemoryOpenRegister $store;

	/**
	 * @var list<string>
	 */
	private array $logged = [];

	/**
	 * An event taking sign-ups from form 12, and Anna's player.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store = new InMemoryOpenRegister();
		$this->store->seed('event', ['id' => self::EVENT, 'name' => 'Winter Court 2026', 'signupForm' => 12]);
		$this->store->seed('player', ['id' => self::ANNA, 'name' => 'Anna de Vries', 'userUid' => 'anna']);
	}//end setUp()

	/**
	 * The real listener over the store.
	 *
	 * @return FormSubmissionListener The listener.
	 */
	private function listener(): FormSubmissionListener {
		$store = $this->store;
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === 'OCA\OpenRegister\Service\ObjectService' ? $store : throw new \RuntimeException('not bound: ' . $id))
		);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => (str_ends_with($key, '_register') === true ? '3' : (str_ends_with($key, '_schema') === true ? substr($key, 0, -strlen('_schema')) : $default))
		);
		$fetcher = new RegisterObjectFetcher($container, $apps, $config, new \Psr\Log\NullLogger());
		$users = $this->createMock(IUserManager::class);
		$users->method('userExists')->willReturnCallback(static fn (string $uid): bool => in_array($uid, ['anna', 'nieuw'], true));
		$logger = $this->createMock(LoggerInterface::class);
		foreach (['info', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(
				function (string $message): void {
					$this->logged[] = $message;
				}
			);
		}

		return new FormSubmissionListener($fetcher, $users, $logger);
	}//end listener()

	/**
	 * A submission event.
	 *
	 * @param int $form The form id.
	 * @param string $uid The submitting user.
	 * @param int $submission The submission id.
	 *
	 * @return FormSubmittedEvent The event.
	 */
	private function submitted(int $form, string $uid, int $submission = 501): FormSubmittedEvent {
		return new FormSubmittedEvent(new Form($form, 'Winter Court sign-up'), new Submission($submission, $form, $uid, 1790000000));
	}//end submitted()

	/**
	 * Anna signs up: one registration with her player, her account and the submission id, written with the app's authority.
	 *
	 * @return void
	 */
	public function testAKnownUserSignsUp(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'anna'));

		$rows = array_values($this->store->objects['registration'] ?? []);
		$this->assertCount(1, $rows);
		$this->assertSame(self::EVENT, $rows[0]['event']);
		$this->assertSame(self::ANNA, $rows[0]['player']);
		$this->assertSame('anna', $rows[0]['submitterUid']);
		$this->assertSame(501, $rows[0]['submissionId']);
		$this->assertSame('pending', $rows[0]['status']);
		$this->assertNotEmpty($rows[0]['submittedAt']);
		$this->assertArrayNotHasKey('answers', $rows[0], 'the answers stay in Forms');
		$this->assertFalse($this->store->saves[0]['rbac'], 'a player may not create registrations; the intake writes as the app');
	}//end testAKnownUserSignsUp()

	/**
	 * A user without a player record still gets a registration, with no player.
	 *
	 * @return void
	 */
	public function testAUserWithoutAPlayerGetsARegistrationWithoutOne(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'nieuw'));

		$rows = array_values($this->store->objects['registration'] ?? []);
		$this->assertCount(1, $rows);
		$this->assertArrayNotHasKey('player', $rows[0], 'no player is linked yet');
		$this->assertSame('nieuw', $rows[0]['submitterUid']);
	}//end testAUserWithoutAPlayerGetsARegistrationWithoutOne()

	/**
	 * An anonymous submission creates nothing and is logged.
	 *
	 * @return void
	 */
	public function testAnAnonymousSubmissionCreatesNothing(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'anon-user-5f2a9c'));

		$this->assertArrayNotHasKey('registration', $this->store->objects);
		$this->assertNotEmpty($this->logged);
	}//end testAnAnonymousSubmissionCreatesNothing()

	/**
	 * A second submission by the same user for the same event leaves the first registration.
	 *
	 * @return void
	 */
	public function testASecondSubmissionKeepsTheFirstRegistration(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'anna', submission: 501));
		$this->listener()->handle($this->submitted(form: 12, uid: 'anna', submission: 502));

		$rows = array_values($this->store->objects['registration']);
		$this->assertCount(1, $rows);
		$this->assertSame(501, $rows[0]['submissionId']);
		$this->assertNotEmpty($this->logged);
	}//end testASecondSubmissionKeepsTheFirstRegistration()

	/**
	 * A form no event takes sign-ups from is not larpinq's business.
	 *
	 * @return void
	 */
	public function testAFormOfNoEventIsIgnored(): void {
		$this->listener()->handle($this->submitted(form: 99, uid: 'anna'));

		$this->assertArrayNotHasKey('registration', $this->store->objects);
	}//end testAFormOfNoEventIsIgnored()
}//end class
