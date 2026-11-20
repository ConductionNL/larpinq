<?php

/**
 * Messages from game masters to the participants of an event.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use OCA\Larpinq\BackgroundJob\SendEventMessageJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves who a message goes to, keeps the message as a record of the event
 * and sends one mail per recipient on a queued job, 50 per run.
 *
 * The audience is read from the event's registrations, its ticket types and
 * its attendance with the app's authority, always scoped to the one event:
 * the controller has made the game-master check first.
 */
class EventMessageService {

	/**
	 * Upper bound on the rows read per schema for one event.
	 */
	private const MAX_ROWS = 2000;

	/**
	 * Mails sent per run of the queued job.
	 */
	public const BATCH_SIZE = 50;

	/**
	 * Names shown in a preview.
	 */
	private const PREVIEW_NAMES = 10;

	/**
	 * Registration statuses a message can be addressed to.
	 */
	public const STATUSES = ['accepted', 'waitlisted', 'pending'];

	/**
	 * Ticket roles a message can be addressed to.
	 */
	public const TICKET_ROLES = ['player', 'crew', 'npc'];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher The OpenRegister reader and writer.
	 * @param EventAudience $audience The audience rules.
	 * @param ParticipantContact $contacts Who a registration's person is and their address.
	 * @param IMailer $mailer The platform mailer.
	 * @param IJobList $jobs The background job list.
	 * @param ITimeFactory $time The clock.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly EventAudience $audience,
		private readonly ParticipantContact $contacts,
		private readonly IMailer $mailer,
		private readonly IJobList $jobs,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The audience of a message in its stored shape, from whatever the client
	 * sent: only known statuses and roles, booleans as booleans. Without a
	 * status the message goes to accepted registrations.
	 *
	 * @param array<string, mixed> $raw The audience as sent.
	 *
	 * @return array{statuses: list<string>, checkedIn: bool, paymentOpen: bool, ticketRoles: list<string>}
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
	 */
	public function normaliseAudience(array $raw): array {
		return $this->audience->normalise(raw: $raw);
	}//end normaliseAudience()

	/**
	 * Who a message for the event reaches: one entry per person with an
	 * address, and the people without one, each counted once however many
	 * registrations they hold.
	 *
	 * @param string $eventId The event UUID.
	 * @param array<string, mixed> $audience The audience (see normaliseAudience()).
	 *
	 * @return array{recipients: list<array{name: string, email: string}>, noAddress: list<array{name: string}>}
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	public function recipients(string $eventId, array $audience): array {
		$audience = $this->normaliseAudience(raw: $audience);
		$recipients = [];
		$noAddress = [];
		$seen = [];
		foreach ($this->audienceRegistrations(eventId: $eventId, audience: $audience) as $registration) {
			$person = $this->contacts->forRegistration(registration: $registration);
			if (isset($seen[$person['key']]) === true) {
				continue;
			}

			$seen[$person['key']] = true;
			if ($person['email'] === '') {
				$noAddress[] = ['name' => $person['name']];
				continue;
			}

			$recipients[] = ['name' => $person['name'], 'email' => $person['email']];
		}

		return ['recipients' => $recipients, 'noAddress' => $noAddress];
	}//end recipients()

	/**
	 * How many people a message would reach, the first ten names, and who has
	 * no address.
	 *
	 * @param string $eventId The event UUID.
	 * @param array<string, mixed> $audience The audience as sent.
	 *
	 * @return array{count: int, names: list<string>, noAddressCount: int, noAddressNames: list<string>}
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	public function preview(string $eventId, array $audience): array {
		$resolved = $this->recipients(eventId: $eventId, audience: $audience);
		$names = array_map(static fn (array $r): string => $r['name'], $resolved['recipients']);

		return [
			'count'          => count($resolved['recipients']),
			'names'          => array_slice($names, 0, self::PREVIEW_NAMES),
			'noAddressCount' => count($resolved['noAddress']),
			'noAddressNames' => array_map(static fn (array $r): string => $r['name'], $resolved['noAddress']),
		];
	}//end preview()

	/**
	 * Save a message for the event as a draft.
	 *
	 * @param string $eventId The event UUID.
	 * @param array<string, mixed> $input `{subject, body, audience}`.
	 * @param string $uid The game master writing it.
	 *
	 * @return array<string, mixed> The saved message.
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
	 */
	public function createDraft(string $eventId, array $input, string $uid): array {
		return $this->fetcher->saveObject(
			objectType: 'eventmessage',
			data: [
				'event'    => $eventId,
				'subject'  => mb_substr(trim((string)($input['subject'] ?? '')), 0, 255),
				'body'     => (string)($input['body'] ?? ''),
				'audience' => $this->normaliseAudience(raw: $this->arrayField(data: $input, key: 'audience')),
				'state'    => 'draft',
				'sentBy'   => $uid,
			]
		);
	}//end createDraft()

	/**
	 * Queue a draft for sending: count its recipients now, mark it queued and
	 * add the job that sends it. A message is sent once.
	 *
	 * @param array<string, mixed> $message The stored message.
	 * @param array<string, mixed> $event The message's event.
	 * @param string $uid The game master sending it.
	 *
	 * @return array<string, mixed> The queued message.
	 *
	 * @throws EventMessageRefusedException When the message is not a draft.
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
	 */
	public function queue(array $message, array $event, string $uid): array {
		if (($message['state'] ?? '') !== 'draft') {
			throw new EventMessageRefusedException('This message was already sent; send a copy instead.');
		}

		$eventId = (string)($message['event'] ?? '');
		$resolved = $this->recipients(eventId: $eventId, audience: $this->arrayField(data: $message, key: 'audience'));
		$data = array_merge(
			$this->storedFields(message: $message),
			[
				'state'          => 'queued',
				'sentBy'         => $uid,
				'recipientCount' => count($resolved['recipients']),
				'noAddressCount' => count($resolved['noAddress']),
				'sentCount'      => 0,
				'failedCount'    => 0,
			]
		);
		$id = (string)($message['id'] ?? '');
		$saved = $this->fetcher->saveObject(objectType: 'eventmessage', data: $data, uuid: $id);

		// Only ids, names and counts travel with the job: a job argument is
		// stored in a short column, and the body can be long.
		$this->jobs->add(
			SendEventMessageJob::class,
			[
				'messageId' => $id,
				'eventId'   => $eventId,
				'eventName' => mb_substr((string)($event['name'] ?? ''), 0, 255),
				'eventDate' => (string)($event['startDate'] ?? ''),
				'offset'    => 0,
				'sent'      => 0,
				'failed'    => 0,
			]
		);

		return $saved;
	}//end queue()

	/**
	 * Send one batch of a queued message, then queue the next batch or, after
	 * the last one, store the counts and the final state. Runs without a
	 * session: every read and write uses the app's authority, scoped to the
	 * message's event.
	 *
	 * @param array<string, mixed> $argument `{messageId, eventId, eventName, eventDate, offset, sent, failed}`.
	 *
	 * @return array{sent: int, failed: int, done: bool} What this batch did.
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
	 */
	public function sendBatch(array $argument): array {
		$eventId = (string)($argument['eventId'] ?? '');
		$message = $this->queuedMessage(eventId: $eventId, messageId: (string)($argument['messageId'] ?? ''));
		if ($message === null) {
			$this->logger->warning('Larpinq: a queued event message is gone or no longer queued; nothing sent.', ['message' => $argument['messageId'] ?? '']);
			return ['sent' => 0, 'failed' => 0, 'done' => true];
		}

		$offset = max(0, (int)($argument['offset'] ?? 0));
		$resolved = $this->recipients(eventId: $eventId, audience: $this->arrayField(data: $message, key: 'audience'));
		$subject = (string)($message['subject'] ?? '');
		$body = $this->fillPlaceholders(
			text: (string)($message['body'] ?? ''),
			eventName: (string)($argument['eventName'] ?? ''),
			eventDate: (string)($argument['eventDate'] ?? '')
		);
		$sent = 0;
		$failed = 0;
		foreach (array_slice($resolved['recipients'], $offset, self::BATCH_SIZE) as $recipient) {
			if ($this->sendOne(recipient: $recipient, subject: $subject, body: $body) === true) {
				$sent++;
				continue;
			}

			$failed++;
		}

		$totalSent = (int)($argument['sent'] ?? 0) + $sent;
		$totalFailed = (int)($argument['failed'] ?? 0) + $failed;
		$next = $offset + self::BATCH_SIZE;
		if ($next < count($resolved['recipients'])) {
			$this->jobs->add(SendEventMessageJob::class, array_merge($argument, ['offset' => $next, 'sent' => $totalSent, 'failed' => $totalFailed]));
			return ['sent' => $sent, 'failed' => $failed, 'done' => false];
		}

		$message['sentCount'] = $totalSent;
		$message['failedCount'] = $totalFailed;
		$message['state'] = 'sent';
		if ($totalFailed > 0 && $totalSent === 0) {
			$message['state'] = 'failed';
		}
		$message['sentAt'] = $this->time->getDateTime()->format(DATE_ATOM);
		$this->fetcher->saveObjectWithAppAuthority(
			objectType: 'eventmessage',
			data: $this->storedFields(message: $message),
			uuid: (string)($message['id'] ?? '')
		);

		return ['sent' => $sent, 'failed' => $failed, 'done' => true];
	}//end sendBatch()

	/**
	 * The queued message of the event with this id, read with the app's
	 * authority scoped to the event, or null when it is gone or not queued.
	 *
	 * @param string $eventId The event UUID.
	 * @param string $messageId The message UUID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function queuedMessage(string $eventId, string $messageId): ?array {
		if ($eventId === '' || $messageId === '') {
			return null;
		}

		$messages = $this->fetcher->getObjectsWithAppAuthority(objectType: 'eventmessage', filters: ['event' => $eventId], limit: self::MAX_ROWS);
		foreach ($messages as $message) {
			$id = (string)($message['id'] ?? ($message['@self']['id'] ?? ''));
			if ($id !== $messageId) {
				continue;
			}

			if (($message['state'] ?? '') !== 'queued') {
				return null;
			}

			return array_merge($message, ['id' => $id]);
		}

		return null;
	}//end queuedMessage()

	/**
	 * The body with the event's name and date in place of `{event}` and `{date}`.
	 *
	 * @param string $text The body as written.
	 * @param string $eventName The event's name.
	 * @param string $eventDate The event's start, ISO-8601.
	 *
	 * @return string The body to send.
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
	 */
	public function fillPlaceholders(string $text, string $eventName, string $eventDate): string {
		$date = $eventDate;
		try {
			if ($eventDate !== '') {
				$date = (new DateTimeImmutable($eventDate))->format('Y-m-d');
			}
		} catch (Throwable $e) {
			$date = $eventDate;
		}

		return strtr($text, ['{event}' => $eventName, '{date}' => $date]);
	}//end fillPlaceholders()

	/**
	 * The event's registrations the audience selects: by status, open payment,
	 * ticket role and check-in.
	 *
	 * @param string $eventId The event UUID.
	 * @param array{statuses: list<string>, checkedIn: bool, paymentOpen: bool, ticketRoles: list<string>} $audience The audience.
	 *
	 * @return list<array<string, mixed>> The registrations.
	 */
	private function audienceRegistrations(string $eventId, array $audience): array {
		if ($eventId === '') {
			return [];
		}

		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['event' => $eventId], limit: self::MAX_ROWS);
		$roles = [];
		if ($audience['ticketRoles'] !== []) {
			$roles = $this->ticketRoles(eventId: $eventId);
		}

		$checkedIn = [];
		if ($audience['checkedIn'] === true) {
			$checkedIn = $this->checkedInCharacters(eventId: $eventId);
		}

		$selected = [];
		foreach ($rows as $registration) {
			if ($this->audience->matches(registration: $registration, audience: $audience, roles: $roles, checkedIn: $checkedIn) === true) {
				$selected[] = $registration;
			}
		}

		return $selected;
	}//end audienceRegistrations()

	/**
	 * The role of each ticket type of the event, by ticket type id.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array<string, string>
	 */
	private function ticketRoles(string $eventId): array {
		$roles = [];
		foreach ($this->fetcher->getObjectsWithAppAuthority(objectType: 'tickettype', filters: ['event' => $eventId], limit: self::MAX_ROWS) as $ticket) {
			$roles[(string)($ticket['id'] ?? '')] = (string)($ticket['role'] ?? '');
		}

		return $roles;
	}//end ticketRoles()

	/**
	 * The characters checked in at the event, as a set.
	 *
	 * @param string $eventId The event UUID.
	 *
	 * @return array<string, true>
	 */
	private function checkedInCharacters(string $eventId): array {
		$characters = [];
		foreach ($this->fetcher->getObjectsWithAppAuthority(objectType: 'attendance', filters: ['event' => $eventId], limit: self::MAX_ROWS) as $row) {
			if (($row['status'] ?? '') === 'checked-in') {
				$characters[(string)($row['character'] ?? '')] = true;
			}
		}

		return $characters;
	}//end checkedInCharacters()

	/**
	 * Send one mail to one recipient, addressed to them only.
	 *
	 * @param array{name: string, email: string} $recipient The recipient.
	 * @param string $subject The subject.
	 * @param string $body The plain-text body.
	 *
	 * @return bool Whether the mailer accepted it.
	 */
	private function sendOne(array $recipient, string $subject, string $body): bool {
		try {
			$mail = $this->mailer->createMessage();
			$mail->setTo([$recipient['email'] => $recipient['name']]);
			$mail->setSubject($subject);
			$mail->setPlainBody($body);
			$refused = $this->mailer->send($mail);

			return $refused === [];
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: an event message could not be handed to the mailer.', ['exception' => $e]);
			return false;
		}
	}//end sendOne()

	/**
	 * The fields of a message as stored, without OpenRegister's own keys.
	 *
	 * @param array<string, mixed> $message The message.
	 *
	 * @return array<string, mixed>
	 */
	private function storedFields(array $message): array {
		$keys = ['event', 'subject', 'body', 'audience', 'state', 'recipientCount', 'sentCount', 'failedCount', 'noAddressCount', 'sentBy', 'sentAt'];

		return array_intersect_key($message, array_flip($keys));
	}//end storedFields()

	/**
	 * A field of a record that must be an array, or [] when it is not one.
	 *
	 * @param array<string, mixed> $data The record.
	 * @param string $key The field.
	 *
	 * @return array<array-key, mixed>
	 */
	private function arrayField(array $data, string $key): array {
		$value = $data[$key] ?? null;
		if (is_array($value) === true) {
			return $value;
		}

		return [];
	}//end arrayField()
}//end class
