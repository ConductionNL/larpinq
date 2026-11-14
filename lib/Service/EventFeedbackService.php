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

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
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
class EventFeedbackService {

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
			$window = self::window(event: $event);
			$formId = (int)($event['feedbackForm'] ?? 0);
			if ($formId <= 0 || $window === null || $now < $window['opens'] || $now >= $window['closes']) {
				continue;
			}

			$counts['events']++;
			try {
				$done = $this->forEvent(event: $event, formId: $formId, remindFrom: $window['closes']->sub(new DateInterval(self::REMIND_BEFORE)), now: $now);
				$counts['invited']  += $done['invited'];
				$counts['reminded'] += $done['reminded'];
			} catch (Throwable $e) {
				$this->logger->error('Larpinq: feedback for event {id} was not sent.', ['id' => (string)($event['id'] ?? ''), 'exception' => $e]);
			}
		}

		return $counts;
	}//end daily()

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

		$closes = self::moment(value: ($event['feedbackClosesAt'] ?? null)) ?? $opens->add(new DateInterval(self::DEFAULT_OPEN_FOR));

		return ['opens' => $opens, 'closes' => $closes];
	}//end window()

	/**
	 * Invite the participants of one event not yet invited, and remind those
	 * not yet reminded once the reminder moment passed.
	 *
	 * @param array             $event      The event.
	 * @param int               $formId     The feedback form.
	 * @param DateTimeImmutable $remindFrom When reminders start.
	 * @param DateTimeImmutable $now        The moment of the run.
	 *
	 * @return array{invited: int, reminded: int}
	 */
	private function forEvent(array $event, int $formId, DateTimeImmutable $remindFrom, DateTimeImmutable $now): array {
		$done = ['invited' => 0, 'reminded' => 0];
		$rows = $this->fetcher->getObjectsWithAppAuthority(
			objectType: 'attendance',
			filters: ['event' => (string)($event['id'] ?? ''), 'status' => self::CHECKED_IN],
			limit: self::PAGE
		);
		$answered = null;
		foreach ($rows as $attendance) {
			// The filter already asks for checked-in; a no-show is never invited even if a store ignores it.
			if ((string)($attendance['status'] ?? '') !== self::CHECKED_IN) {
				continue;
			}

			if ((string)($attendance['feedbackInvitedAt'] ?? '') === '') {
				$owner = $this->ownerOf(attendance: $attendance);
				$done['invited'] += $this->step(attendance: $attendance, owner: $owner, event: $event, formId: $formId, now: $now, field: 'feedbackInvitedAt');
				continue;
			}

			if ((string)($attendance['feedbackRemindedAt'] ?? '') !== '' || $now < $remindFrom) {
				continue;
			}

			$answered ??= ['uids' => $this->answers->answeredBy(formId: $formId)];
			$owner = $this->ownerOf(attendance: $attendance);
			if ($answered['uids'] !== null && in_array($owner, $answered['uids'], true) === true) {
				continue;
			}

			$done['reminded'] += $this->step(attendance: $attendance, owner: $owner, event: $event, formId: $formId, now: $now, field: 'feedbackRemindedAt');
		}//end foreach

		return $done;
	}//end forEvent()

	/**
	 * Send one invitation or reminder and stamp it. A participant whose
	 * character has no account is stamped too, so they are not retried daily.
	 *
	 * @param array             $attendance The attendance record.
	 * @param string            $owner      The participant's user id, or ''.
	 * @param array             $event      The event.
	 * @param int               $formId     The feedback form.
	 * @param DateTimeImmutable $now        The moment of the run.
	 * @param string            $field      The stamp: feedbackInvitedAt or feedbackRemindedAt.
	 *
	 * @return int 1 when something was sent, else 0.
	 */
	private function step(array $attendance, string $owner, array $event, int $formId, DateTimeImmutable $now, string $field): int {
		$sent  = $owner !== '' && $this->delivery->send(uid: $owner, event: $event, formId: $formId, reminder: $field === 'feedbackRemindedAt');
		$this->fetcher->saveObjectWithAppAuthority(
			objectType: 'attendance',
			data: [$field => $now->format(DateTimeInterface::ATOM)],
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
		$character = $attendance['character'] ?? '';
		if (is_array($character) === true) {
			$character = ($character['id'] ?? ($character['@self']['id'] ?? ''));
		}

		if (is_string($character) === false || $character === '') {
			return '';
		}

		try {
			return (string)($this->fetcher->getObject(objectType: 'character', id: $character)['ownerUid'] ?? '');
		} catch (Throwable $e) {
			return '';
		}
	}//end ownerOf()

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
			return new DateTimeImmutable($value);
		} catch (Throwable $e) {
			return null;
		}
	}//end moment()

}//end class
