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

use DateTimeImmutable;
use DateTimeInterface;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
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
class RegistrationService {

	public const PENDING = 'pending';

	public const ACCEPTED = 'accepted';

	public const WAITLISTED = 'waitlisted';

	/**
	 * Who a decision by the capacity is stamped with.
	 *
	 * @var string
	 */
	public const AUTOMATIC = 'larpinq';

	/**
	 * Lock key prefix, one lock per event.
	 *
	 * @var string
	 */
	private const LOCK_PREFIX = 'larpinq/event-capacity/';

	/**
	 * Upper bound of the registrations one event is read with.
	 *
	 * @var integer
	 */
	private const MAX_ROWS = 2000;

	/**
	 * Events whose lock this request holds.
	 *
	 * @var array<string, true>
	 */
	private array $held = [];

	/**
	 * Whether the service itself is moving a waitlisted registration up.
	 *
	 * @var boolean
	 */
	private bool $promoting = false;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param ILockingProvider $locking The lock per event.
	 * @param LoggerInterface $logger The logger.
	 * @param int $lockAttempts How often a busy lock is tried before giving up.
	 * @param int $lockWaitMicros Pause between two tries, in microseconds.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly ILockingProvider $locking,
		private readonly LoggerInterface $logger,
		private readonly int $lockAttempts = 30,
		private readonly int $lockWaitMicros = 100000,
	) {
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
			$changes['submittedAt'] = $this->now();
		}

		$event = $this->event(eventId: (string)($registration['event'] ?? ''));
		if ($event === null || ($event['approvalRequired'] ?? false) === true) {
			return array_merge($changes, ['status' => self::PENDING]);
		}

		return array_merge(
			$changes,
			[
				'status' => $this->placeOutcome(event: $event, excludeId: ''),
				'decidedAt' => $this->now(),
				'decidedBy' => self::AUTOMATIC,
			]
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

		$stamp = ['decidedAt' => $this->now(), 'decidedBy' => $actingUid];
		if ($this->promoting === true || $actingUid === '') {
			$stamp['decidedBy'] = self::AUTOMATIC;
		}

		if ($status !== self::ACCEPTED) {
			return $stamp;
		}

		$event = $this->event(eventId: (string)($new['event'] ?? ''));
		if ($event === null) {
			return $stamp;
		}

		return array_merge($stamp, ['status' => $this->placeOutcome(event: $event, excludeId: (string)($new['id'] ?? ''))]);
	}//end beforeUpdate()

	/**
	 * Why a player may not bring this character, or null when they may.
	 *
	 * The character must be the registration's player's, active, and of the
	 * event's world.
	 *
	 * @param array<string, mixed> $registration The registration as it will be.
	 *
	 * @return string|null The reason, untranslated, or null.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function characterRefusal(array $registration): ?string {
		$characterId = (string)($registration['character'] ?? '');
		try {
			$character = $this->fetcher->getObject(objectType: 'character', id: $characterId);
		} catch (Throwable $e) {
			return 'This character does not exist or is not yours.';
		}

		$player = (string)($registration['player'] ?? '');
		if ($player === '' || (string)($character['ocName'] ?? '') !== $player) {
			return 'You can only bring one of your own characters.';
		}

		if ((string)($character['status'] ?? 'active') !== 'active') {
			return 'A retired or dead character cannot be brought to an event.';
		}

		$event = $this->event(eventId: (string)($registration['event'] ?? ''));
		$world = (string)($event['setting'] ?? '');
		if ($world !== '' && (string)($character['setting'] ?? '') !== $world) {
			return 'The character must belong to the world of the event.';
		}

		return null;
	}//end characterRefusal()

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

		if ($wasAccepted === true && (string)($new['status'] ?? '') !== self::ACCEPTED) {
			$this->promoteNext(eventId: $eventId);
		}
	}//end afterWrite()

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
		if (isset($this->held[$eventId]) === false) {
			return;
		}

		unset($this->held[$eventId]);
		$this->locking->releaseLock(self::LOCK_PREFIX . $eventId, ILockingProvider::LOCK_EXCLUSIVE);
	}//end release()

	/**
	 * Accepted when a place is free, else waitlisted; decided under the event's lock.
	 *
	 * Without a capacity every registration has a place. When the lock cannot
	 * be had the registration is waitlisted: the event is never overbooked, and
	 * the next freed place moves it up.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param string $excludeId The registration being decided (not counted).
	 *
	 * @return string The status.
	 */
	private function placeOutcome(array $event, string $excludeId): string {
		$capacity = $event['capacity'] ?? null;
		if (is_int($capacity) === false && is_numeric($capacity) === false) {
			return self::ACCEPTED;
		}

		$eventId = (string)($event['id'] ?? '');
		if ($this->acquire(eventId: $eventId) === false) {
			$this->logger->warning('Larpinq: event {event} was busy; the registration waits for a place.', ['event' => $eventId]);
			return self::WAITLISTED;
		}

		if ($this->placesTaken(event: $event, excludeId: $excludeId) < (int)$capacity) {
			return self::ACCEPTED;
		}

		return self::WAITLISTED;
	}//end placeOutcome()

	/**
	 * Accepted registrations plus characters in the event that no accepted registration brings.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param string $excludeId A registration not to count.
	 *
	 * @return int The places taken.
	 */
	private function placesTaken(array $event, string $excludeId): int {
		$accepted = $this->registrations(eventId: (string)($event['id'] ?? ''), status: self::ACCEPTED);
		$brought = [];
		$count = 0;
		foreach ($accepted as $registration) {
			if ((string)($registration['id'] ?? '') === $excludeId) {
				continue;
			}

			$count++;
			$brought[(string)($registration['character'] ?? '')] = true;
		}

		foreach ((array)($event['players'] ?? []) as $character) {
			if (isset($brought[(string)$character]) === false) {
				$count++;
			}
		}

		return $count;
	}//end placesTaken()

	/**
	 * Put the character of an accepted registration in the event, and take it out when the registration leaves accepted.
	 *
	 * @param string $eventId The event.
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before.
	 *
	 * @return void
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

		$players = array_map('strval', (array)($event['players'] ?? []));
		$next = array_values(array_filter($players, static fn (string $id): bool => $id !== $remove));
		if ($add !== '' && in_array($add, $next, true) === false) {
			$next[] = $add;
		}

		if ($next === $players) {
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
		$next = $waiting[0];
		$this->promoting = true;
		try {
			$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: ['status' => self::ACCEPTED], uuid: (string)$next['id']);
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: the waiting list of event {event} did not move up.', ['event' => $eventId, 'exception' => $e]);
		} finally {
			$this->promoting = false;
		}
	}//end promoteNext()

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

	/**
	 * Take the event's lock, trying a few times while another request holds it.
	 *
	 * @param string $eventId The event.
	 *
	 * @return bool Whether this request holds the lock.
	 */
	private function acquire(string $eventId): bool {
		if (isset($this->held[$eventId]) === true) {
			return true;
		}

		for ($attempt = 1; $attempt <= max(1, $this->lockAttempts); $attempt++) {
			try {
				$this->locking->acquireLock(self::LOCK_PREFIX . $eventId, ILockingProvider::LOCK_EXCLUSIVE);
				$this->held[$eventId] = true;
				return true;
			} catch (LockedException $e) {
				usleep($this->lockWaitMicros);
			}
		}

		return false;
	}//end acquire()

	/**
	 * Now, as stored.
	 *
	 * @return string The moment.
	 */
	private function now(): string {
		return (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
	}//end now()
}//end class
