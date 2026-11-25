<?php

/**
 * Larpinq Registration Service
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
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\Larpinq\Kernel\Contracts\RegistrationServiceInterface;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Capacity, the waiting list and the participants of an event.
 *
 * A decision that depends on free places is taken under a lock per event,
 * acquired in the registration's pre-write handler and released by its
 * post-write handler, so two sign-ups for the last place cannot both see it
 * free. Places taken are accepted registrations plus characters a game master
 * put in the event by hand.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationService implements RegistrationServiceInterface {

	public const WAITLISTED = 'waitlisted';

	/**
	 * Who a decision by the capacity is stamped with.
	 *
	 * @var string
	 */
	public const AUTOMATIC = 'larpinq';

	/**
	 * Upper bound of the registrations one event is read with.
	 *
	 * @var integer
	 */
	private const MAX_ROWS = 2000;

	/**
	 * The lock per event that place decisions are taken under.
	 *
	 * @var EventCapacityLock
	 */
	private readonly EventCapacityLock $lock;

	/**
	 * Whether the service itself is moving a waitlisted registration up.
	 *
	 * @var boolean
	 */
	private bool $promoting = false;

	/**
	 * How many places of the event and of a ticket type are taken.
	 *
	 * @var RegistrationPlaces
	 */
	private readonly RegistrationPlaces $places;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param ILockingProvider $locking The lock per event.
	 * @param LoggerInterface $logger The logger.
	 * @param int $lockAttempts How often a busy lock is tried before giving up.
	 * @param int $lockWaitMicros Pause between two tries, in microseconds.
	 * @param EventCareRecords|null $care Copies the player's care details for the event on acceptance (players-care-details-and-erasure).
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		ILockingProvider $locking,
		private readonly LoggerInterface $logger,
		int $lockAttempts = 30,
		int $lockWaitMicros = 100000,
		private readonly ?EventCareRecords $care = null,
	) {
		$this->places = new RegistrationPlaces(fetcher: $fetcher);
		$this->lock   = new EventCapacityLock(locking: $locking, retry: ['attempts' => $lockAttempts, 'waitMicros' => $lockWaitMicros]);
	}//end __construct()

	/**
	 * What a new registration gets before it is stored.
	 *
	 * With approval it waits as pending; without, it is accepted while a place
	 * is free and waitlisted when the event is full.
	 *
	 * @param array<string, mixed> $registration The registration as submitted.
	 *
	 * @return array<string, mixed> The fields to set.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function beforeCreate(array $registration): array {
		$changes = [];
		if ((string)($registration['submittedAt'] ?? '') === '') {
			$changes['submittedAt'] = date(DATE_ATOM);
		}

		$event = $this->event(eventId: (string)($registration['event'] ?? ''));
		if ($event === null || ($event['approvalRequired'] ?? false) === true) {
			return array_merge($changes, ['status' => self::PENDING]);
		}

		$placement = $this->placement(event: $event, registration: $registration);

		return array_merge(
			$changes,
			['status' => $placement['status'], 'decidedAt' => date(DATE_ATOM), 'decidedBy' => self::AUTOMATIC],
			$placement
		);
	}//end beforeCreate()

	/**
	 * What a changed registration gets before it is stored.
	 *
	 * An accept gives a place when one is free and the waiting list otherwise;
	 * every status change is stamped with who and when.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed> $old The registration as stored.
	 * @param string $actingUid Who asked for the change.
	 *
	 * @return array<string, mixed> The fields to set.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function beforeUpdate(array $new, array $old, string $actingUid): array {
		$status = (string)($new['status'] ?? '');
		if ($status === (string)($old['status'] ?? '')) {
			return [];
		}

		$stamp = $this->stamp(actingUid: $actingUid);
		if ($status !== self::ACCEPTED) {
			return $stamp;
		}

		return array_merge($stamp, $this->acceptance(new: $new, old: $old));
	}//end beforeUpdate()

	/**
	 * Who and when a status change is decided: the acting user, or automatic
	 * for a promotion or a change without a user.
	 *
	 * @param string $actingUid Who asked for the change.
	 *
	 * @return array<string, string> `decidedAt` and `decidedBy`.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	private function stamp(string $actingUid): array {
		$decidedBy = $actingUid;
		if ($this->promoting === true || $actingUid === '') {
			$decidedBy = self::AUTOMATIC;
		}

		return ['decidedAt' => date(DATE_ATOM), 'decidedBy' => $decidedBy];
	}//end stamp()

	/**
	 * What an accept gets: a place or the waiting list, and a check-in code
	 * when it is placed. Nothing when the event is gone.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed> $old The registration as stored.
	 *
	 * @return array<string, mixed> The fields to set.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	private function acceptance(array $new, array $old): array {
		$event = $this->event(eventId: (string)($new['event'] ?? ''));
		if ($event === null) {
			return [];
		}

		$outcome = $this->placeOutcome(event: $event, registration: $new, excludeId: (string)($new['id'] ?? ''));

		return array_merge(['status' => $outcome], self::checkinCodeFor(registration: array_merge($old, $new), status: $outcome));
	}//end acceptance()

	/**
	 * The status a new registration gets at an event without approval, and
	 * its check-in code when that status is accepted.
	 *
	 * @param array<string, mixed> $event        The event.
	 * @param array<string, mixed> $registration The registration as submitted.
	 *
	 * @return array<string, mixed> `status`, and `checkinCode` when accepted.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
	 */
	private function placement(array $event, array $registration): array {
		$outcome = $this->placeOutcome(event: $event, registration: $registration, excludeId: '');

		return array_merge(['status' => $outcome], self::checkinCodeFor(registration: $registration, status: $outcome));
	}//end placement()

	/**
	 * A check-in code for a registration that becomes accepted and has none (REQ-EQC-001).
	 *
	 * A registration that already has a code keeps it, also when it moves to
	 * another player.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string $status The status it gets.
	 *
	 * @return array<string, string> `['checkinCode' => ...]`, or nothing.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) CheckinCode is a pure helper.
	 */
	private static function checkinCodeFor(array $registration, string $status): array {
		if ($status !== self::ACCEPTED || (string)($registration['checkinCode'] ?? '') !== '') {
			return [];
		}

		return ['checkinCode' => CheckinCode::generate()];
	}//end checkinCodeFor()

	/**
	 * After a registration is stored: the participants follow it, the lock is
	 * released, and a freed place goes to the oldest waitlisted registration.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before, or null on create.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function afterWrite(array $new, ?array $old): void {
		$eventId = (string)($new['event'] ?? '');
		$wasAccepted = (string)($old['status'] ?? '') === self::ACCEPTED;
		try {
			$this->syncParticipants(eventId: $eventId, new: $new, old: $old);
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: the participants of event {event} did not follow a registration.', ['event' => $eventId, 'exception' => $e]);
		} finally {
			$this->release(eventId: $eventId);
		}

		$this->followAcceptance(new: $new, wasAccepted: $wasAccepted);
	}//end afterWrite()

	/**
	 * Follow a change of acceptance: a freed place goes to the oldest waitlisted
	 * registration, and a newly accepted registration carries a copy of the
	 * player's care details for the event (REQ-PCE-002). The copy never fails
	 * the registration.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param bool $wasAccepted Whether the registration was accepted before.
	 *
	 * @return void
	 */
	private function followAcceptance(array $new, bool $wasAccepted): void {
		$isAccepted = (string)($new['status'] ?? '') === self::ACCEPTED;
		if ($wasAccepted === $isAccepted) {
			return;
		}

		if ($wasAccepted === true) {
			$this->promoteNext(eventId: (string)($new['event'] ?? ''));
			return;
		}

		$this->care?->copyForRegistration(registration: $new);
	}//end followAcceptance()

	/**
	 * Release the event's lock when this request holds it.
	 *
	 * @param string $eventId The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function release(string $eventId): void {
		$this->lock->release(eventId: $eventId);
	}//end release()

	/**
	 * Take the event's lock for this request, as a decision that counts places does.
	 *
	 * The lock is released by the registration's post-write handler, or by
	 * release() when the write is refused.
	 *
	 * @param string $eventId The event.
	 *
	 * @return bool Whether this request holds the lock.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function hold(string $eventId): bool {
		return $this->lock->acquire(eventId: $eventId);
	}//end hold()

	/**
	 * Accepted when a place is free, else waitlisted; decided under the event's lock.
	 *
	 * A place must be free in the event and, when the chosen ticket type has a
	 * place limit, in that ticket type. Without either limit every
	 * registration has a place. When the lock cannot be had the registration
	 * is waitlisted: the event is never overbooked, and the next freed place
	 * moves it up.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param array<string, mixed> $registration The registration being decided.
	 * @param string $excludeId The registration being decided (not counted).
	 *
	 * @return string The status.
	 * @spec openspec/specs/event-registration/spec.md
	 */
	private function placeOutcome(array $event, array $registration, string $excludeId): string {
		$capacity = $event['capacity'] ?? null;
		$eventLimited = is_int($capacity) === true || is_numeric($capacity) === true;
		if ($eventLimited === false && $this->places->ticketLimit(registration: $registration) === null) {
			return self::ACCEPTED;
		}

		$eventId = (string)($event['id'] ?? '');
		if ($this->lock->acquire(eventId: $eventId) === false) {
			$this->logger->warning('Larpinq: event {event} was busy; the registration waits for a place.', ['event' => $eventId]);
			return self::WAITLISTED;
		}

		if ($eventLimited === true && $this->places->placesTaken(event: $event, excludeId: $excludeId) >= (int)$capacity) {
			return self::WAITLISTED;
		}

		if ($this->places->ticketTypeFull(registration: array_merge($registration, ['id' => $excludeId])) === true) {
			return self::WAITLISTED;
		}

		return self::ACCEPTED;
	}//end placeOutcome()

	/**
	 * Put the character of an accepted registration in the event, and take it out when the registration leaves accepted.
	 *
	 * @param string $eventId The event.
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before.
	 *
	 * @return void
	 * @spec openspec/specs/event-registration/spec.md
	 */
	private function syncParticipants(string $eventId, array $new, ?array $old): void {
		$remove = '';
		if ((string)($old['status'] ?? '') === self::ACCEPTED) {
			$remove = (string)($old['character'] ?? '');
		}

		$add = '';
		if ((string)($new['status'] ?? '') === self::ACCEPTED) {
			$add = (string)($new['character'] ?? '');
		}

		if ($remove === $add) {
			return;
		}

		$event = $this->event(eventId: $eventId);
		if ($event === null) {
			return;
		}

		$next = $this->places->playersAfter(event: $event, move: ['remove' => $remove, 'add' => $add]);
		if ($next === array_map('strval', (array)($event['players'] ?? []))) {
			return;
		}

		$this->fetcher->saveObjectWithAppAuthority(objectType: 'event', data: ['players' => $next], uuid: $eventId);
	}//end syncParticipants()

	/**
	 * Accept the oldest waitlisted registration of the event, through the same decision.
	 *
	 * @param string $eventId The event.
	 *
	 * @return void
	 */
	private function promoteNext(string $eventId): void {
		$waiting = $this->registrations(eventId: $eventId, status: self::WAITLISTED);
		if ($waiting === []) {
			return;
		}

		usort(
			$waiting,
			static fn (array $one, array $two): int => strcmp((string)($one['submittedAt'] ?? ''), (string)($two['submittedAt'] ?? ''))
		);
		// The oldest registration whose ticket type still has a place gets it;
		// one write, and the decision itself still checks the event's places.
		foreach ($waiting as $next) {
			if ($this->places->ticketTypeFull(registration: $next) === false) {
				$this->promote(eventId: $eventId, registrationId: (string)$next['id']);
				return;
			}
		}
	}//end promoteNext()

	/**
	 * Accept one waitlisted registration through the same decision.
	 *
	 * @param string $eventId The event.
	 * @param string $registrationId The registration.
	 *
	 * @return void
	 */
	private function promote(string $eventId, string $registrationId): void {
		$this->promoting = true;
		try {
			$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: ['status' => self::ACCEPTED], uuid: $registrationId);
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: the waiting list of event {event} did not move up.', ['event' => $eventId, 'exception' => $e]);
		} finally {
			$this->promoting = false;
		}
	}//end promote()

	/**
	 * The event's registrations with one status.
	 *
	 * @param string $eventId The event.
	 * @param string $status The status.
	 *
	 * @return array<int, array<string, mixed>> The registrations.
	 */
	private function registrations(string $eventId, string $status): array {
		return $this->fetcher->getObjectsWithAppAuthority(
			objectType: 'registration',
			filters: ['event' => $eventId, 'status' => $status],
			limit: self::MAX_ROWS
		);
	}//end registrations()

	/**
	 * The event, or null when it cannot be read.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, mixed>|null The event.
	 */
	private function event(string $eventId): ?array {
		if ($eventId === '') {
			return null;
		}

		try {
			return $this->fetcher->getObject(objectType: 'event', id: $eventId);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: event {event} of a registration could not be read.', ['event' => $eventId, 'exception' => $e]);
			return null;
		}
	}//end event()
}//end class
