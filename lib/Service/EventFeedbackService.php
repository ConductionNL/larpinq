<?php

/**
 * Larpinq Event Feedback Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\Kernel\Contracts\EventFeedbackServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The daily round of post-event feedback (design D2): every participant who
 * was checked in at an event whose feedback is open is asked once, and five
 * days before it closes those who have not answered are reminded once. Each
 * step is stamped on the attendance record, so a later run never repeats it.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */
class EventFeedbackService implements EventFeedbackServiceInterface {

	/**
	 * How long feedback stays open when the event does not say.
	 *
	 * @var string
	 */
	public const DEFAULT_OPEN_FOR = 'P21D';

	/**
	 * How long before closing the reminder goes out.
	 *
	 * @var string
	 */
	public const REMIND_BEFORE = 'P5D';

	/**
	 * The attendance status of a participant who was there.
	 *
	 * @var string
	 */
	public const CHECKED_IN = 'checked-in';

	/**
	 * Rows read per query: the job is bounded, and what it does not reach
	 * today it reaches tomorrow.
	 *
	 * @var int
	 */
	private const PAGE = 200;

	/**
	 * The invitation stamp on an attendance.
	 *
	 * @var string
	 */
	private const INVITED_AT = 'feedbackInvitedAt';

	/**
	 * The reminder stamp on an attendance.
	 *
	 * @var string
	 */
	private const REMINDED_AT = 'feedbackRemindedAt';

	/**
	 * The count each stamp adds to.
	 *
	 * @var array<string, string>
	 */
	private const COUNTED_AS = [self::INVITED_AT => 'invited', self::REMINDED_AT => 'reminded'];

	/**
	 * Who answered each form, read once per event run.
	 *
	 * @var array<int, list<string>|null>
	 */
	private array $answered = [];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher  Reads and stamps the register with the app's authority.
	 * @param FeedbackDelivery      $delivery Sends the notification and the mail.
	 * @param FeedbackAnswers       $answers  Tells who answered.
	 * @param LoggerInterface       $logger   The logger.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly FeedbackDelivery $delivery,
		private readonly FeedbackAnswers $answers,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Invite and remind for every event whose feedback is open at $now.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return array{events: int, invited: int, reminded: int}
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function daily(DateTimeImmutable $now): array {
		$counts = ['events' => 0, 'invited' => 0, 'reminded' => 0];
		$events = $this->fetcher->getObjectsWithAppAuthority(objectType: 'event', filters: ['feedbackForm' => 'IS NOT NULL'], limit: self::PAGE);
		foreach ($events as $event) {
			$run = self::openRun(event: $event, now: $now);
			if ($run === null) {
				continue;
			}

			$counts['events']++;
			try {
				$done = $this->forEvent(run: $run);
				$counts['invited']  += $done['invited'];
				$counts['reminded'] += $done['reminded'];
			} catch (Throwable $e) {
				$this->logger->error('Larpinq: feedback for event {id} was not sent.', ['id' => (string)($event['id'] ?? ''), 'exception' => $e]);
			}
		}

		return $counts;
	}//end daily()

	/**
	 * Who was invited to give feedback on an event and who answered, for game masters (REQ-EPS-004).
	 *
	 * `answered` and `notAnswered` are null when Forms cannot tell who
	 * submitted: the page then shows the invitations only.
	 *
	 * @param array $event The event.
	 *
	 * @return array<string, mixed> formId, opensAt, closesAt, invited, answered (int|null) and notAnswered (list of uid and character, or null).
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
	 */
	public function summary(array $event): array {
		$formId  = (int)($event['feedbackForm'] ?? 0);
		$window  = self::window(event: $event);
		$summary = [
			'formId' => $formId,
			'opensAt' => $window['opens']?->format(DateTimeInterface::ATOM),
			'closesAt' => $window['closes']?->format(DateTimeInterface::ATOM),
			'invited' => 0,
			'answered' => null,
			'notAnswered' => null,
		];
		if ($formId <= 0 || (string)($event['id'] ?? '') === '') {
			return $summary;
		}

		return $this->withAnswers(summary: $summary, invited: $this->invitees(eventId: (string)$event['id']));
	}//end summary()

	/**
	 * The summary with how many invitees answered and who has not, when Forms can tell.
	 *
	 * @param array<string, mixed>  $summary The summary so far.
	 * @param array<string, string> $invited Character name by invited user id.
	 *
	 * @return array<string, mixed>
	 */
	private function withAnswers(array $summary, array $invited): array {
		$summary['invited'] = count($invited);
		$uids = $this->answers->answeredBy(formId: $summary['formId']);
		if ($uids === null) {
			return $summary;
		}

		$summary['answered']    = count(array_intersect(array_keys($invited), $uids));
		$summary['notAnswered'] = [];
		foreach ($invited as $uid => $character) {
			if (in_array((string)$uid, $uids, true) === false) {
				$summary['notAnswered'][] = ['uid' => (string)$uid, 'character' => $character];
			}
		}

		return $summary;
	}//end summary()

	/**
	 * An event by id, read with the app's authority, or null when it does not exist.
	 *
	 * @param string $id The event id.
	 *
	 * @return array|null The event.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
	 */
	public function event(string $id): ?array {
		try {
			return $this->fetcher->getObjectOfType(objectType: 'event', id: $id);
		} catch (Throwable $e) {
			return null;
		}
	}//end event()

	/**
	 * When an event's feedback opens and closes: the stored moments, else the
	 * end of the event and 21 days later. Null when there is no moment to open on.
	 *
	 * @param array $event The event.
	 *
	 * @return array{opens: DateTimeImmutable, closes: DateTimeImmutable}|null
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-an-event-has-a-feedback-form-req-eps-001
	 */
	public static function window(array $event): ?array {
		$opens = self::moment(value: ($event['feedbackOpensAt'] ?? null)) ?? self::moment(value: ($event['endDate'] ?? null));
		if ($opens === null) {
			return null;
		}

		$closes = self::moment(value: ($event['feedbackClosesAt'] ?? null)) ?? $opens->add(new \DateInterval(self::DEFAULT_OPEN_FOR));

		return ['opens' => $opens, 'closes' => $closes];
	}//end window()

	/**
	 * The feedback run of an event whose window is open at $now, or null.
	 *
	 * @param array             $event The event.
	 * @param DateTimeImmutable $now   The moment of the run.
	 *
	 * @return array{event: array, formId: int, remindFrom: DateTimeImmutable, now: DateTimeImmutable}|null
	 */
	private static function openRun(array $event, DateTimeImmutable $now): ?array {
		$window = self::window(event: $event);
		$formId = (int)($event['feedbackForm'] ?? 0);
		if ($formId <= 0 || self::isOpen(window: $window, now: $now) === false) {
			return null;
		}

		return [
			'event'      => $event,
			'formId'     => $formId,
			'remindFrom' => $window['closes']->sub(new \DateInterval(self::REMIND_BEFORE)),
			'now'        => $now,
		];
	}//end openRun()

	/**
	 * Whether a feedback window is open at $now.
	 *
	 * @param array{opens: DateTimeImmutable, closes: DateTimeImmutable}|null $window The window, or null.
	 * @param DateTimeImmutable                                             $now    The moment.
	 *
	 * @return bool
	 */
	private static function isOpen(?array $window, DateTimeImmutable $now): bool {
		return $window !== null && $now >= $window['opens'] && $now < $window['closes'];
	}//end isOpen()

	/**
	 * Invite the participants of one event not yet invited, and remind those
	 * not yet reminded once the reminder moment passed.
	 *
	 * @param array{event: array, formId: int, remindFrom: DateTimeImmutable, now: DateTimeImmutable} $run The event's run.
	 *
	 * @return array{invited: int, reminded: int}
	 */
	private function forEvent(array $run): array {
		$done           = ['invited' => 0, 'reminded' => 0];
		$this->answered = [];
		foreach ($this->checkedIn(eventId: (string)($run['event']['id'] ?? '')) as $attendance) {
			$due = $this->dueFor(attendance: $attendance, run: $run);
			if ($due !== null) {
				$done[self::COUNTED_AS[$due['field']]] += $this->step(attendance: $attendance, ask: $run + $due);
			}
		}

		return $done;
	}//end forEvent()

	/**
	 * What an attendance is due: its participant and the stamp to send, or null.
	 *
	 * A reminder is not due for a participant who already answered.
	 *
	 * @param array                                                                    $attendance The checked-in attendance.
	 * @param array{formId: int, remindFrom: DateTimeImmutable, now: DateTimeImmutable} $run        The event's run.
	 *
	 * @return array{owner: string, field: string}|null
	 */
	private function dueFor(array $attendance, array $run): ?array {
		$field = self::dueStamp(attendance: $attendance, run: $run);
		if ($field === null) {
			return null;
		}

		$owner = $this->ownerOf(attendance: $attendance);
		if ($field === self::REMINDED_AT) {
			$field = self::unlessAnswered(owner: $owner, uids: $this->answeredUids(formId: $run['formId']));
		}

		if ($field === null) {
			return null;
		}

		return ['owner' => $owner, 'field' => $field];
	}//end dueFor()

	/**
	 * Who answered a form, asked once per event run.
	 *
	 * @param int $formId The form.
	 *
	 * @return list<string>|null
	 */
	private function answeredUids(int $formId): ?array {
		if (array_key_exists($formId, $this->answered) === false) {
			$this->answered[$formId] = $this->answers->answeredBy(formId: $formId);
		}

		return $this->answered[$formId];
	}//end answeredUids()

	/**
	 * The reminder stamp, or null when the participant already answered.
	 *
	 * @param string            $owner The participant's user id.
	 * @param list<string>|null $uids  Who answered; null when Forms cannot tell.
	 *
	 * @return string|null
	 */
	private static function unlessAnswered(string $owner, ?array $uids): ?string {
		if ($uids !== null && in_array($owner, $uids, true) === true) {
			return null;
		}

		return self::REMINDED_AT;
	}//end unlessAnswered()

	/**
	 * Which stamp an attendance is due: the invitation, the reminder, or none.
	 *
	 * @param array                                                        $attendance The checked-in attendance.
	 * @param array{remindFrom: DateTimeImmutable, now: DateTimeImmutable} $run        The event's run.
	 *
	 * @return string|null feedbackInvitedAt, feedbackRemindedAt or null.
	 */
	private static function dueStamp(array $attendance, array $run): ?string {
		if ((string)($attendance['feedbackInvitedAt'] ?? '') === '') {
			return self::INVITED_AT;
		}

		if ((string)($attendance['feedbackRemindedAt'] ?? '') !== '' || $run['now'] < $run['remindFrom']) {
			return null;
		}

		return self::REMINDED_AT;
	}//end dueStamp()

	/**
	 * The checked-in attendances of an event.
	 *
	 * The filter already asks for checked-in; a no-show is never included even if a store ignores it.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<int, array> The attendance records.
	 */
	private function checkedIn(string $eventId): array {
		$rows = $this->fetcher->getObjectsWithAppAuthority(
			objectType: 'attendance',
			filters: ['event' => $eventId, 'status' => self::CHECKED_IN],
			limit: self::PAGE
		);

		return array_values(array_filter($rows, static fn (array $row): bool => (string)($row['status'] ?? '') === self::CHECKED_IN));
	}//end checkedIn()

	/**
	 * Send one invitation or reminder and stamp it. A participant whose
	 * character has no account is stamped too, so they are not retried daily.
	 *
	 * @param array $attendance The attendance record.
	 * @param array $ask        The event's run with the participant's user id (owner, or '') and the stamp (field).
	 *
	 * @return int 1 when something was sent, else 0.
	 */
	private function step(array $attendance, array $ask): int {
		$invite = ['event' => $ask['event'], 'formId' => $ask['formId'], 'reminder' => $ask['field'] === self::REMINDED_AT];
		$sent   = $ask['owner'] !== '' && $this->delivery->send(uid: $ask['owner'], invite: $invite);
		// A save replaces the stored object, so the whole record is written back with the stamp.
		$record = $attendance;
		unset($record['@self'], $record['id']);
		$record[$ask['field']] = $ask['now']->format(DateTimeInterface::ATOM);
		$this->fetcher->saveObjectWithAppAuthority(
			objectType: 'attendance',
			data: $record,
			uuid: (string)($attendance['id'] ?? '')
		);

		if ($sent === true) {
			return 1;
		}

		return 0;
	}//end step()

	/**
	 * The account that plays the attendance's character, or ''.
	 *
	 * @param array $attendance The attendance record.
	 *
	 * @return string The user id.
	 */
	private function ownerOf(array $attendance): string {
		return $this->characterField(attendance: $attendance, field: 'ownerUid');
	}//end ownerOf()

	/**
	 * One field of the attendance's character, read from the register, or ''.
	 *
	 * @param array  $attendance The attendance record.
	 * @param string $field      The character field.
	 *
	 * @return string The value.
	 */
	private function characterField(array $attendance, string $field): string {
		$character = $attendance['character'] ?? '';
		if (is_array($character) === true) {
			$character = ($character['id'] ?? ($character['@self']['id'] ?? ''));
		}

		if (is_string($character) === false || $character === '') {
			return '';
		}

		try {
			return (string)($this->fetcher->getObject(objectType: 'character', id: $character)[$field] ?? '');
		} catch (Throwable $e) {
			return '';
		}
	}//end characterField()

	/**
	 * The participants of an event who were invited, by user id, with the name of their character.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, string> Character name by user id.
	 */
	private function invitees(string $eventId): array {
		$invited = [];
		foreach ($this->checkedIn(eventId: $eventId) as $attendance) {
			if ((string)($attendance['feedbackInvitedAt'] ?? '') === '') {
				continue;
			}

			$owner = $this->ownerOf(attendance: $attendance);
			if ($owner !== '') {
				$invited[$owner] = $this->characterName(attendance: $attendance);
			}
		}

		return $invited;
	}//end invitees()

	/**
	 * The name of the attendance's character, or ''.
	 *
	 * @param array $attendance The attendance record.
	 *
	 * @return string The name.
	 */
	private function characterName(array $attendance): string {
		$character = $attendance['character'] ?? '';
		if (is_array($character) === true && (string)($character['name'] ?? '') !== '') {
			return (string)$character['name'];
		}

		return $this->characterField(attendance: $attendance, field: 'name');
	}//end characterName()

	/**
	 * A stored date-time, or null.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return DateTimeImmutable|null
	 */
	private static function moment(mixed $value): ?DateTimeImmutable {
		if (is_string($value) === false || trim($value) === '') {
			return null;
		}

		try {
			$moment = new DateTimeImmutable($value);
		} catch (Throwable $e) {
			$moment = null;
		}

		return $moment;
	}//end moment()

}//end class
