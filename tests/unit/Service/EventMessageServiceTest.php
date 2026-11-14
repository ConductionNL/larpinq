<?php

/**
 * Larpinq Event Message Service Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTime;
use OCA\Larpinq\BackgroundJob\SendEventMessageJob;
use OCA\Larpinq\Service\EventAudience;
use OCA\Larpinq\Service\EventMessageRefusedException;
use OCA\Larpinq\Service\EventMessageService;
use OCA\Larpinq\Service\ParticipantContact;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Who a message reaches, the preview, the draft, the single send and the
 * batched mail run, against a fake register and a fake mailer.
 */
#[CoversClass(EventMessageService::class)]
#[CoversClass(EventMessageRefusedException::class)]
#[UsesClass(EventAudience::class)]
#[CoversClass(ParticipantContact::class)]
class EventMessageServiceTest extends TestCase {

	private const EVENT = 'a0000000-0000-4000-8000-000000000001';

	private const MESSAGE = 'b0000000-0000-4000-8000-000000000001';

	/** @var array<string, list<array<string, mixed>>> Rows per object type, as the app-authority read returns them. */
	private array $rows = [];

	/** @var array<string, array<string, mixed>> Players by id. */
	private array $players = [];

	/** @var array<string, string> Account emails by uid ('' = no address). */
	private array $emails = [];

	/** @var list<array{to: array, subject: string, body: string}> The mails handed to the mailer. */
	private array $sent = [];

	/** @var list<string> Addresses the mailer refuses. */
	private array $refuse = [];

	/** @var list<array{0: string, 1: array}> Jobs added. */
	private array $jobs = [];

	/** @var list<array{type: string, data: array, uuid: ?string, app: bool}> Saved objects. */
	private array $saved = [];

	private RegisterObjectFetcher&MockObject $fetcher;

	/**
	 * Three accepted players of Winter Court 2026, one without an address.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->rows = [
			'registration' => [
				['id' => 'r1', 'event' => self::EVENT, 'player' => 'p1', 'playerUid' => 'anna', 'status' => 'accepted', 'paymentState' => 'paid', 'ticketType' => 't-player', 'character' => 'c1'],
				['id' => 'r2', 'event' => self::EVENT, 'player' => 'p2', 'playerUid' => 'bram', 'status' => 'accepted', 'paymentState' => 'open', 'ticketType' => 't-crew', 'character' => 'c2'],
				['id' => 'r3', 'event' => self::EVENT, 'player' => 'p3', 'playerUid' => 'cees', 'status' => 'accepted', 'paymentState' => 'paid', 'ticketType' => 't-player', 'character' => 'c3'],
				['id' => 'r4', 'event' => self::EVENT, 'player' => 'p4', 'playerUid' => 'dirk', 'status' => 'waitlisted', 'paymentState' => 'open', 'ticketType' => 't-player', 'character' => 'c4'],
				['id' => 'r5', 'event' => self::EVENT, 'player' => 'p5', 'playerUid' => 'eva', 'status' => 'declined', 'ticketType' => 't-player', 'character' => 'c5'],
			],
			'tickettype' => [
				['id' => 't-player', 'role' => 'player'],
				['id' => 't-crew', 'role' => 'crew'],
			],
			'attendance' => [
				['event' => self::EVENT, 'character' => 'c1', 'status' => 'checked-in'],
				['event' => self::EVENT, 'character' => 'c2', 'status' => 'no-show'],
			],
			'eventmessage' => [],
		];
		$this->players = [
			'p1' => ['name' => 'Anna'],
			'p2' => ['name' => 'Bram'],
			'p3' => ['name' => 'Cees'],
			'p4' => ['name' => 'Dirk'],
		];
		$this->emails = ['anna' => 'anna@example.org', 'bram' => 'bram@example.org', 'cees' => '', 'dirk' => 'dirk@example.org', 'eva' => 'eva@example.org'];
		$this->sent = [];
		$this->refuse = [];
		$this->jobs = [];
		$this->saved = [];

		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters, ?int $limit = null): array {
				TestCase::assertSame(['event' => self::EVENT], $filters, 'every read is scoped to the event');
				return $this->rows[$objectType] ?? [];
			}
		);
		$this->fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id): array {
				if ($objectType !== 'player' || isset($this->players[$id]) === false) {
					throw new RuntimeException('not found');
				}

				return $this->players[$id];
			}
		);
		$this->fetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid = null): array {
				$this->saved[] = ['type' => $objectType, 'data' => $data, 'uuid' => $uuid, 'app' => false];
				return array_merge($data, ['id' => $uuid ?? self::MESSAGE]);
			}
		);
		$this->fetcher->method('saveObjectWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid = null): array {
				$this->saved[] = ['type' => $objectType, 'data' => $data, 'uuid' => $uuid, 'app' => true];
				return array_merge($data, ['id' => $uuid]);
			}
		);
	}//end setUp()

	/**
	 * The service with fake users, mailer, job list and clock.
	 *
	 * @return EventMessageService
	 */
	private function service(): EventMessageService {
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(
			function (string $uid): ?IUser {
				if (array_key_exists($uid, $this->emails) === false) {
					return null;
				}

				$user = $this->createMock(IUser::class);
				$user->method('getEMailAddress')->willReturn($this->emails[$uid] === '' ? null : $this->emails[$uid]);
				$user->method('getDisplayName')->willReturn(ucfirst($uid).' (account)');
				return $user;
			}
		);

		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturnCallback(fn (): IMessage => $this->fakeMessage());
		$mailer->method('send')->willReturnCallback(
			function (IMessage $message): array {
				$to = array_keys($message->getTo());
				if (in_array($to[0], $this->refuse, true) === true) {
					return $to;
				}

				$this->sent[] = ['to' => $message->getTo(), 'subject' => $message->getSubject(), 'body' => $message->body];
				return [];
			}
		);

		$jobs = $this->createMock(IJobList::class);
		$jobs->method('add')->willReturnCallback(
			function (string $job, mixed $argument = null): void {
				$this->jobs[] = [$job, $argument];
			}
		);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturn(new DateTime('2026-11-28T10:00:00+00:00'));

		return new EventMessageService(
			fetcher: $this->fetcher,
			audience: new EventAudience(),
			contacts: new ParticipantContact(fetcher: $this->fetcher, users: $users, logger: $this->createMock(LoggerInterface::class)),
			mailer: $mailer,
			jobs: $jobs,
			time: $time,
			logger: $this->createMock(LoggerInterface::class)
		);
	}//end service()

	/**
	 * A mail message double that remembers what it was given.
	 *
	 * @return IMessage
	 */
	private function fakeMessage(): IMessage {
		return new class () implements IMessage {
			/** @var array<string, string> */
			public array $to = [];
			public string $subject = '';
			public string $body = '';

			public function setTo(array $recipients): IMessage {
				$this->to = $recipients;
				return $this;
			}

			public function getTo(): array {
				return $this->to;
			}

			public function setSubject(string $subject): IMessage {
				$this->subject = $subject;
				return $this;
			}

			public function getSubject(): string {
				return $this->subject;
			}

			public function setPlainBody(string $body): IMessage {
				$this->body = $body;
				return $this;
			}

			public function setFrom(array $addresses): IMessage {
				return $this;
			}

			public function setReplyTo(array $addresses): IMessage {
				return $this;
			}

			public function setCc(array $recipients): IMessage {
				return $this;
			}

			public function setBcc(array $recipients): IMessage {
				return $this;
			}

			public function setHtmlBody(string $body): IMessage {
				return $this;
			}

			public function attach(\OCP\Mail\IAttachment $attachment): IMessage {
				return $this;
			}

			public function attachInline(string $body, string $name, ?string $contentType = null): IMessage {
				return $this;
			}

			public function useTemplate(\OCP\Mail\IEMailTemplate $emailTemplate): IMessage {
				return $this;
			}

			public function setAutoSubmitted(string $value): IMessage {
				return $this;
			}

			public function getAutoSubmitted(): string {
				return '';
			}
		};
	}//end fakeMessage()

	/**
	 * Scenario "A player with no address": three accepted players, one without
	 * an address, give 2 recipients and 1 without an address.
	 *
	 * @return void
	 */
	public function testThePreviewCountsAcceptedPlayersAndListsTheOneWithoutAnAddress(): void {
		$preview = $this->service()->preview(eventId: self::EVENT, audience: ['statuses' => ['accepted']]);

		$this->assertSame(
			['count' => 2, 'names' => ['Anna', 'Bram'], 'noAddressCount' => 1, 'noAddressNames' => ['Cees']],
			$preview
		);
	}//end testThePreviewCountsAcceptedPlayersAndListsTheOneWithoutAnAddress()

	/**
	 * Without a status the audience is the accepted registrations.
	 *
	 * @return void
	 */
	public function testTheAudienceDefaultsToAcceptedAndDropsUnknownValues(): void {
		$audience = $this->service()->normaliseAudience(raw: ['statuses' => ['declined', 'x'], 'checkedIn' => 'true', 'paymentOpen' => '0', 'ticketRoles' => ['npc', 'admin', 'crew']]);

		$this->assertSame(['statuses' => ['accepted'], 'checkedIn' => true, 'paymentOpen' => false, 'ticketRoles' => ['crew', 'npc']], $audience);
	}//end testTheAudienceDefaultsToAcceptedAndDropsUnknownValues()

	/**
	 * Each filter narrows the audience: waitlisted, open payment, ticket role, checked in.
	 *
	 * @return void
	 */
	public function testEachFilterNarrowsTheAudience(): void {
		$service = $this->service();
		$emails = fn (array $audience): array => array_column($service->recipients(eventId: self::EVENT, audience: $audience)['recipients'], 'email');

		$this->assertSame(['dirk@example.org'], $emails(['statuses' => ['waitlisted']]));
		$this->assertSame(['bram@example.org', 'dirk@example.org'], $emails(['statuses' => ['accepted', 'waitlisted'], 'paymentOpen' => true]));
		$this->assertSame(['bram@example.org'], $emails(['statuses' => ['accepted'], 'ticketRoles' => ['crew']]));
		$this->assertSame(['anna@example.org'], $emails(['statuses' => ['accepted'], 'checkedIn' => true]));
	}//end testEachFilterNarrowsTheAudience()

	/**
	 * One person with two registrations gets one mail; a player without
	 * `playerUid` is reached through the player's account.
	 *
	 * @return void
	 */
	public function testAPersonWithTwoRegistrationsIsCountedOnce(): void {
		$this->rows['registration'][] = ['id' => 'r6', 'event' => self::EVENT, 'player' => 'p1', 'playerUid' => 'anna', 'status' => 'accepted', 'character' => 'c6'];
		$this->rows['registration'][] = ['id' => 'r7', 'event' => self::EVENT, 'player' => 'p7', 'status' => 'accepted', 'character' => 'c7'];
		$this->players['p7'] = ['name' => 'Fenna', 'userUid' => 'fenna'];
		$this->emails['fenna'] = 'fenna@example.org';

		$resolved = $this->service()->recipients(eventId: self::EVENT, audience: []);

		$this->assertSame(
			[
				['name' => 'Anna', 'email' => 'anna@example.org'],
				['name' => 'Bram', 'email' => 'bram@example.org'],
				['name' => 'Fenna', 'email' => 'fenna@example.org'],
			],
			$resolved['recipients']
		);
		$this->assertSame([['name' => 'Cees']], $resolved['noAddress']);
	}//end testAPersonWithTwoRegistrationsIsCountedOnce()

	/**
	 * Scenario "The practical letter": the message is saved as a draft for the event.
	 *
	 * @return void
	 */
	public function testAMessageIsSavedAsADraft(): void {
		$this->service()->createDraft(
			eventId: self::EVENT,
			input: ['subject' => '  Practical letter ', 'body' => 'See you at {event}', 'audience' => ['statuses' => ['accepted']]],
			uid: 'gm'
		);

		$this->assertSame(
			[[
				'type' => 'eventmessage',
				'data' => [
					'event'    => self::EVENT,
					'subject'  => 'Practical letter',
					'body'     => 'See you at {event}',
					'audience' => ['statuses' => ['accepted'], 'checkedIn' => false, 'paymentOpen' => false, 'ticketRoles' => []],
					'state'    => 'draft',
					'sentBy'   => 'gm',
				],
				'uuid' => null,
				'app'  => false,
			]],
			$this->saved
		);
	}//end testAMessageIsSavedAsADraft()

	/**
	 * Sending a draft counts its recipients, marks it queued and adds the job
	 * with ids only; a second send is refused.
	 *
	 * @return void
	 */
	public function testSendingQueuesTheMessageOnceWithIdsOnly(): void {
		$message = $this->draft();
		$service = $this->service();

		$service->queue(message: $message, event: ['name' => 'Winter Court 2026', 'startDate' => '2026-12-05T18:00:00+01:00'], uid: 'gm');

		$this->assertSame('queued', $this->saved[0]['data']['state']);
		$this->assertSame(2, $this->saved[0]['data']['recipientCount']);
		$this->assertSame(1, $this->saved[0]['data']['noAddressCount']);
		$this->assertSame(self::MESSAGE, $this->saved[0]['uuid']);
		$this->assertSame(
			[[SendEventMessageJob::class, ['messageId' => self::MESSAGE, 'eventId' => self::EVENT, 'eventName' => 'Winter Court 2026', 'eventDate' => '2026-12-05T18:00:00+01:00', 'offset' => 0, 'sent' => 0, 'failed' => 0]]],
			$this->jobs
		);

		$this->expectException(EventMessageRefusedException::class);
		$service->queue(message: array_merge($message, ['state' => 'sent']), event: [], uid: 'gm');
	}//end testSendingQueuesTheMessageOnceWithIdsOnly()

	/**
	 * Scenario "Sending": each recipient gets one mail addressed to them only,
	 * with the event's name and date filled in, and the message shows 2 sent,
	 * 0 failed.
	 *
	 * @return void
	 */
	public function testTheJobMailsEachRecipientAndRecordsTheCounts(): void {
		$this->rows['eventmessage'] = [array_merge($this->draft(), ['state' => 'queued', 'recipientCount' => 2])];

		$result = $this->service()->sendBatch(argument: $this->jobArgument());

		$this->assertSame(['sent' => 2, 'failed' => 0, 'done' => true], $result);
		$this->assertSame(
			[
				['to' => ['anna@example.org' => 'Anna'], 'subject' => 'Practical letter', 'body' => 'Welcome to Winter Court 2026 on 2026-12-05.'],
				['to' => ['bram@example.org' => 'Bram'], 'subject' => 'Practical letter', 'body' => 'Welcome to Winter Court 2026 on 2026-12-05.'],
			],
			$this->sent
		);
		$final = end($this->saved);
		$this->assertTrue($final['app']);
		$this->assertSame(self::MESSAGE, $final['uuid']);
		$this->assertSame('sent', $final['data']['state']);
		$this->assertSame(2, $final['data']['sentCount']);
		$this->assertSame(0, $final['data']['failedCount']);
		$this->assertSame('2026-11-28T10:00:00+00:00', $final['data']['sentAt']);
		$this->assertArrayNotHasKey('id', $final['data']);
		$this->assertSame([], $this->jobs);
	}//end testTheJobMailsEachRecipientAndRecordsTheCounts()

	/**
	 * A refused mail is counted as failed; when nothing went out the message is failed.
	 *
	 * @return void
	 */
	public function testRefusedMailsAreCountedAsFailed(): void {
		$this->rows['eventmessage'] = [array_merge($this->draft(), ['state' => 'queued'])];
		$this->refuse = ['anna@example.org', 'bram@example.org'];

		$result = $this->service()->sendBatch(argument: $this->jobArgument());

		$this->assertSame(['sent' => 0, 'failed' => 2, 'done' => true], $result);
		$final = end($this->saved);
		$this->assertSame('failed', $final['data']['state']);
		$this->assertSame(2, $final['data']['failedCount']);
	}//end testRefusedMailsAreCountedAsFailed()

	/**
	 * More than 50 recipients: the first run sends 50 and queues the next
	 * batch with the running counts; the message is stored only at the end.
	 *
	 * @return void
	 */
	public function testALargeAudienceIsSentInBatchesOfFifty(): void {
		$this->rows['registration'] = [];
		for ($i = 1; $i <= 60; $i++) {
			$this->rows['registration'][] = ['id' => 'r'.$i, 'event' => self::EVENT, 'playerUid' => 'u'.$i, 'status' => 'accepted'];
			$this->emails['u'.$i] = 'u'.$i.'@example.org';
		}

		$this->rows['eventmessage'] = [array_merge($this->draft(), ['state' => 'queued'])];
		$service = $this->service();

		$first = $service->sendBatch(argument: $this->jobArgument());

		$this->assertSame(['sent' => 50, 'failed' => 0, 'done' => false], $first);
		$this->assertSame([], $this->saved);
		$this->assertSame(50, $this->jobs[0][1]['offset']);
		$this->assertSame(50, $this->jobs[0][1]['sent']);

		$second = $service->sendBatch(argument: $this->jobs[0][1]);

		$this->assertSame(['sent' => 10, 'failed' => 0, 'done' => true], $second);
		$this->assertCount(60, $this->sent);
		$this->assertSame(60, end($this->saved)['data']['sentCount']);
	}//end testALargeAudienceIsSentInBatchesOfFifty()

	/**
	 * A message that is gone or no longer queued sends nothing.
	 *
	 * @return void
	 */
	public function testAMessageThatIsNotQueuedSendsNothing(): void {
		$this->rows['eventmessage'] = [array_merge($this->draft(), ['state' => 'sent'])];

		$result = $this->service()->sendBatch(argument: $this->jobArgument());

		$this->assertSame(['sent' => 0, 'failed' => 0, 'done' => true], $result);
		$this->assertSame([], $this->sent);
		$this->assertSame([], $this->saved);
	}//end testAMessageThatIsNotQueuedSendsNothing()

	/**
	 * The placeholders take the event's name and day; an unreadable date is kept.
	 *
	 * @return void
	 */
	public function testThePlaceholdersAreFilledIn(): void {
		$service = $this->service();

		$this->assertSame('Winter Court 2026, 2026-12-05', $service->fillPlaceholders(text: '{event}, {date}', eventName: 'Winter Court 2026', eventDate: '2026-12-05T18:00:00+01:00'));
		$this->assertSame('X on soon', $service->fillPlaceholders(text: '{event} on {date}', eventName: 'X', eventDate: 'soon'));
	}//end testThePlaceholdersAreFilledIn()

	/**
	 * The draft of the practical letter to accepted registrations.
	 *
	 * @return array<string, mixed>
	 */
	private function draft(): array {
		return [
			'id'       => self::MESSAGE,
			'event'    => self::EVENT,
			'subject'  => 'Practical letter',
			'body'     => 'Welcome to {event} on {date}.',
			'audience' => ['statuses' => ['accepted']],
			'state'    => 'draft',
			'sentBy'   => 'gm',
		];
	}//end draft()

	/**
	 * The first job argument for the practical letter.
	 *
	 * @return array<string, mixed>
	 */
	private function jobArgument(): array {
		return ['messageId' => self::MESSAGE, 'eventId' => self::EVENT, 'eventName' => 'Winter Court 2026', 'eventDate' => '2026-12-05T18:00:00+01:00', 'offset' => 0, 'sent' => 0, 'failed' => 0];
	}//end jobArgument()
}//end class
