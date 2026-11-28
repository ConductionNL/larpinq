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

require_once __DIR__ . '/../Support/RegisterAnswers.php';

require_once __DIR__ . '/../Support/InMemoryOpenRegister.php';

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Larpinq\Listener\FormSubmissionListener;
use OCA\Larpinq\Service\FormSubmitWriter;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Service\Form\FormSubmission;
use OCA\Larpinq\Tests\Unit\Support\InMemoryOpenRegister;
use OCA\Larpinq\Tests\Unit\Support\RegisterAnswers;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
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

	/** @var InMemoryOpenRegister The store. */
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
	private function listener(?FormSubmitWriter $writer = null): FormSubmissionListener {
		$store = $this->store;
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => RegisterAnswers::onlyService(id: $id, bound: 'OCA\OpenRegister\Service\ObjectService', service: $store)
		);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => RegisterAnswers::registerConfig(key: $key, default: $default)
		);
		$fetcher = new RegisterObjectFetcher(container: $container, appManager: $apps, config: $config, logger: new \Psr\Log\NullLogger());
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('userExists')->willReturnCallback(static fn (string $uid): bool => in_array($uid, ['anna', 'nieuw'], true));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(
				function (string $message): void {
					$this->logged[] = $message;
				}
			);
		}

		foreach (['error'] as $level) {
			$logger->method($level)->willReturnCallback(
				function (string $message): void {
					$this->logged[] = $message;
				}
			);
		}

		return new FormSubmissionListener(fetcher: $fetcher, users: $users, logger: $logger, writer: $writer);
	}//end listener()

	/**
	 * What OpenRegister's submit service was asked, by idempotency key.
	 *
	 * @var array<string, list<FormSubmission>>
	 */
	private array $submits = [];

	/**
	 * A submit writer over a fake submit service that stores into the
	 * in-memory register, keeps idempotency keys, and refuses when told to.
	 *
	 * @param \Throwable|null $refusal What the submit service throws instead of writing.
	 * @param bool            $bound   Whether OpenRegister binds the submit service at all.
	 *
	 * @return FormSubmitWriter The writer.
	 */
	private function writer(?\Throwable $refusal = null, bool $bound = true): FormSubmitWriter {
		$store = $this->store;
		$submitted = &$this->submits;
		$service = new class($store, $submitted, $refusal) {
			/** @param array<string, list<FormSubmission>> $submits */
			public function __construct(private InMemoryOpenRegister $store, private array &$submits, private ?\Throwable $refusal) {
			}

			/** @return array<string, mixed> */
			public function submit(FormSubmission $submission, ?string $idempotencyKey = null): array {
				$key = (string)$idempotencyKey;
				$first = isset($this->submits[$key]);
				$this->submits[$key][] = $submission;
				if ($this->refusal !== null) {
					throw $this->refusal;
				}

				if ($first === false) {
					$this->store->seed('registration', array_merge(['id' => 'r-' . count($this->submits)], $submission->payload));
				}

				return ['id' => 'r-' . count($this->submits)];
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willReturn($bound);
		$container->method('get')->willReturn($service);
		$schemas = $this->createMock(originalClassName: \OCA\Larpinq\Service\RegisterSchemaLookup::class);
		$schemas->method('resolve')->willReturn(['3', 'registration']);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturn($this->createMock(originalClassName: \OCP\IUser::class));

		return new FormSubmitWriter(container: $container, schemas: $schemas, users: $users);
	}//end writer()

	/**
	 * Through the submit service: the event fired twice for one answer leaves
	 * exactly one registration, under one idempotency key, with the app's authority.
	 *
	 * @return void
	 */
	public function testASubmitThroughTheSubmitServiceCreatesOneRegistration(): void {
		$writer = $this->writer();
		$this->listener(writer: $writer)->handle($this->submitted(form: 12, uid: 'anna', submission: 501));
		$this->listener(writer: $writer)->handle($this->submitted(form: 12, uid: 'anna', submission: 501));

		$rows = array_values($this->store->objects['registration']);
		$this->assertCount(expectedCount: 1, haystack: $rows);
		$this->assertSame(expected: ['forms-submission:501'], actual: array_keys($this->submits));
		$submission = $this->submits['forms-submission:501'][0];
		$this->assertTrue(condition: $submission->submitter->appAuthority);
		$this->assertSame(expected: 'forms:12', actual: $submission->submitter->scope);
		$this->assertSame(expected: [['register' => '3', 'schema' => 'registration', 'mapping' => null]], actual: $submission->writes);
		$this->assertSame(expected: self::EVENT, actual: $submission->payload['event']);
		$this->assertSame(expected: self::ANNA, actual: $submission->payload['player']);
		$this->assertSame(expected: 'pending', actual: $submission->payload['status']);
		$this->assertSame(expected: 501, actual: $submission->payload['submissionId']);
		$this->assertSame(expected: '2026-09-21T14:13:20+00:00', actual: $submission->payload['submittedAt'], message: 'Forms\' own moment, so a repeat carries the same object');
		$this->assertSame(expected: [], actual: $this->store->saves, message: 'no direct write beside the submit');
	}//end testASubmitThroughTheSubmitServiceCreatesOneRegistration()

	/**
	 * A repeated event the duplicate check misses (a race) reaches the submit
	 * service with the same key and the same object, so nothing new is made.
	 *
	 * @return void
	 */
	public function testARepeatedSubmitCarriesTheSameKeyAndObject(): void {
		$writer = $this->writer();
		$this->listener(writer: $writer)->handle($this->submitted(form: 12, uid: 'anna', submission: 501));
		unset($this->store->objects['registration']);
		$this->listener(writer: $writer)->handle($this->submitted(form: 12, uid: 'anna', submission: 501));

		$this->assertCount(expectedCount: 2, haystack: $this->submits['forms-submission:501']);
		$this->assertEquals(expected: $this->submits['forms-submission:501'][0]->payload, actual: $this->submits['forms-submission:501'][1]->payload);
		$this->assertArrayNotHasKey(key: 'registration', array: $this->store->objects, message: 'the remembered key creates nothing new');
	}//end testARepeatedSubmitCarriesTheSameKeyAndObject()

	/**
	 * Control: a submit the service refuses (the binding no longer fits the
	 * schema) creates no registration, falls back to nothing, and is logged.
	 *
	 * @return void
	 */
	public function testARefusedSubmitCreatesNothing(): void {
		$this->listener(writer: $this->writer(refusal: new \RuntimeException('required-unmapped: playerName')))->handle($this->submitted(form: 12, uid: 'anna'));

		$this->assertArrayNotHasKey(key: 'registration', array: $this->store->objects);
		$this->assertSame(expected: [], actual: $this->store->saves, message: 'a refusal is not retried as a direct write');
		$this->assertContains(needle: 'Larpinq: a sign-up on form {form} did not become a registration.', haystack: $this->logged);
	}//end testARefusedSubmitCreatesNothing()

	/**
	 * An OpenRegister without the submit service keeps the direct write with the app's authority.
	 *
	 * @return void
	 */
	public function testWithoutTheSubmitServiceTheDirectWriteStays(): void {
		$this->listener(writer: $this->writer(bound: false))->handle($this->submitted(form: 12, uid: 'anna'));

		$this->assertCount(expectedCount: 1, haystack: $this->store->objects['registration']);
		$this->assertFalse(condition: $this->store->saves[0]['rbac']);
		$this->assertSame(expected: [], actual: $this->submits);
	}//end testWithoutTheSubmitServiceTheDirectWriteStays()

	/**
	 * A submission event.
	 *
	 * @param int $form The form id.
	 * @param string $uid The user id.
	 * @param int $submission The submission id.
	 *
	 * @return FormSubmittedEvent The event.
	 */
	private function submitted(int $form, string $uid, int $submission = 501): FormSubmittedEvent {
		// 1790000000 = 2026-09-21T14:13:20Z, Forms' own moment of the answer.
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
		$this->assertCount(expectedCount: 1, haystack: $rows);
		$this->assertSame(expected: self::EVENT, actual: $rows[0]['event']);
		$this->assertSame(expected: self::ANNA, actual: $rows[0]['player']);
		$this->assertSame(expected: 'anna', actual: $rows[0]['submitterUid']);
		$this->assertSame(expected: 501, actual: $rows[0]['submissionId']);
		$this->assertSame(expected: 'pending', actual: $rows[0]['status']);
		$this->assertNotEmpty(actual: $rows[0]['submittedAt']);
		$this->assertArrayNotHasKey(key: 'answers', array: $rows[0], message: 'the answers stay in Forms');
		$this->assertFalse(condition: $this->store->saves[0]['rbac'], message: 'a player may not create registrations; the intake writes as the app');
	}//end testAKnownUserSignsUp()

	/**
	 * A user without a player record still gets a registration, with no player.
	 *
	 * @return void
	 */
	public function testAUserWithoutAPlayerGetsARegistrationWithoutOne(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'nieuw'));

		$rows = array_values($this->store->objects['registration'] ?? []);
		$this->assertCount(expectedCount: 1, haystack: $rows);
		$this->assertArrayNotHasKey(key: 'player', array: $rows[0], message: 'no player is linked yet');
		$this->assertSame(expected: 'nieuw', actual: $rows[0]['submitterUid']);
	}//end testAUserWithoutAPlayerGetsARegistrationWithoutOne()

	/**
	 * An anonymous submission creates nothing and is logged.
	 *
	 * @return void
	 */
	public function testAnAnonymousSubmissionCreatesNothing(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'anon-user-5f2a9c'));

		$this->assertArrayNotHasKey(key: 'registration', array: $this->store->objects);
		$this->assertNotEmpty(actual: $this->logged);
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
		$this->assertCount(expectedCount: 1, haystack: $rows);
		$this->assertSame(expected: 501, actual: $rows[0]['submissionId']);
		$this->assertNotEmpty(actual: $this->logged);
	}//end testASecondSubmissionKeepsTheFirstRegistration()

	/**
	 * Events other than a Forms submission are ignored.
	 *
	 * @return void
	 */
	public function testOtherEventsAreIgnored(): void {
		$this->listener()->handle(new Event());

		$this->assertArrayNotHasKey(key: 'registration', array: $this->store->objects);
		$this->assertSame(expected: [], actual: $this->logged);
	}//end testOtherEventsAreIgnored()

	/**
	 * A submission without a form id asks the register nothing.
	 *
	 * @return void
	 */
	public function testASubmissionWithoutAFormIdIsIgnored(): void {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->expects(self::never())->method('getObjectsWithAppAuthority');
		$fetcher->expects(self::never())->method('saveObjectWithAppAuthority');
		$listener = new FormSubmissionListener(
			fetcher: $fetcher,
			users: $this->createMock(originalClassName: IUserManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$listener->handle($this->submitted(form: 0, uid: 'anna'));
	}//end testASubmissionWithoutAFormIdIsIgnored()

	/**
	 * A failing intake is logged with the form, and never breaks the submission.
	 *
	 * @return void
	 */
	public function testAFailingIntakeIsLoggedNotThrown(): void {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willThrowException(new \RuntimeException('register offline'));
		$fetcher->expects(self::never())->method('saveObjectWithAppAuthority');
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())->method('error')->with(
			'Larpinq: a sign-up on form {form} did not become a registration.',
			self::callback(static fn (array $context): bool => $context['form'] === 12 && $context['exception'] instanceof \RuntimeException)
		);
		$listener = new FormSubmissionListener(
			fetcher: $fetcher,
			users: $this->createMock(originalClassName: IUserManager::class),
			logger: $logger
		);

		$listener->handle($this->submitted(form: 12, uid: 'anna'));
	}//end testAFailingIntakeIsLoggedNotThrown()

	/**
	 * A form no event takes sign-ups from is not larpinq's business.
	 *
	 * @return void
	 */
	public function testAFormOfNoEventIsIgnored(): void {
		$this->listener()->handle($this->submitted(form: 99, uid: 'anna'));

		$this->assertArrayNotHasKey(key: 'registration', array: $this->store->objects);
	}//end testAFormOfNoEventIsIgnored()
	/**
	 * A uid that is no account here, or no uid at all, creates nothing and
	 * logs the anonymous sign-up; a repeat logs which registration stands.
	 *
	 * @return void
	 */
	public function testUnknownAccountsCreateNothingAndTheLogNamesWhy(): void {
		$this->listener()->handle($this->submitted(form: 12, uid: 'ghost'));
		$this->listener()->handle($this->submitted(form: 12, uid: ''));
		$this->listener()->handle($this->submitted(form: 12, uid: 'anna', submission: 501));
		$this->listener()->handle($this->submitted(form: 12, uid: 'anna', submission: 502));

		$this->assertCount(expectedCount: 1, haystack: $this->store->objects['registration']);
		$this->assertSame(
			expected: [
				'Larpinq: an anonymous sign-up for event {event} is not a registration.',
				'Larpinq: an anonymous sign-up for event {event} is not a registration.',
				'Larpinq: {user} signed up for event {event} again; the first registration stands.',
			],
			actual: $this->logged
		);
	}//end testUnknownAccountsCreateNothingAndTheLogNamesWhy()
}//end class
