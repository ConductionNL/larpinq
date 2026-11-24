<?php

/**
 * Larpinq Event Care Records
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
 * @spec openspec/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\Kernel\Contracts\EventCareRecordsInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Keeps one copy of a player's care details per accepted registration, for
 * the event's game masters (REQ-PCE-002). The copy is made when the
 * registration is accepted and follows the player's details until the event
 * starts; after the start it is never written again. Each copy carries
 * `eraseAfter`, the end of the event plus the event's `careRetentionDays`,
 * from which OpenRegister's retention puts it on a destruction list
 * (REQ-PCE-003). Reads and writes run with the app's authority, so the copy
 * is made whoever accepted the registration.
 *
 * @spec openspec/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */
class EventCareRecords implements EventCareRecordsInterface {

	/**
	 * Days a copy is kept after the event when the event sets none.
	 *
	 * @var integer
	 */
	public const DEFAULT_RETENTION_DAYS = 30;

	/**
	 * The most accepted registrations of one player refreshed at once.
	 *
	 * @var integer
	 */
	private const MAX_REGISTRATIONS = 200;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param ITimeFactory $time The clock that decides whether an event has started.
	 * @param LoggerInterface $logger The logger; it never receives care details.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Create or update the copy for an accepted registration, unless its event has started.
	 *
	 * @param array<string, mixed> $registration The registration, with its `id`, `event` and `player`.
	 *
	 * @return bool Whether a copy was written.
	 *
	 * @spec openspec/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
	 */
	public function copyForRegistration(array $registration): bool {
		$registrationId = (string)($registration['id'] ?? '');
		if (self::hasLinks(registration: $registration) === false) {
			return false;
		}

		try {
			return $this->writeCopy(registration: $registration);
		} catch (Throwable $e) {
			$this->logger->error(
				'Larpinq: the care details of registration {registration} could not be copied for its event.',
				['registration' => $registrationId, 'exception' => $e]
			);
			return false;
		}
	}//end copyForRegistration()

	/**
	 * Whether a registration names itself, its event and its player.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return bool
	 */
	private static function hasLinks(array $registration): bool {
		foreach (['id', 'event', 'player'] as $field) {
			if ((string)($registration[$field] ?? '') === '') {
				return false;
			}
		}

		return true;
	}//end hasLinks()

	/**
	 * Write the copy for a registration whose event has not started.
	 *
	 * @param array<string, mixed> $registration The registration, with its `id`, `event` and `player`.
	 *
	 * @return bool Whether a copy was written.
	 */
	private function writeCopy(array $registration): bool {
		$event = $this->fetcher->getObjectWithAppAuthority(objectType: 'event', id: (string)$registration['event']);
		if ($this->hasStarted(event: $event) === true) {
			return false;
		}

		$player = $this->fetcher->getObjectWithAppAuthority(objectType: 'player', id: (string)$registration['player']);
		$record = self::recordFor(event: $event, registration: $registration, player: $player);
		if ($record === null) {
			return false;
		}

		$uuid = $this->existingCopyId(registrationId: (string)$registration['id']);
		if ($uuid === null && self::isEmpty(record: $record) === true) {
			return false;
		}

		$this->fetcher->saveObjectWithAppAuthority(objectType: 'eventcarerecord', data: $record, uuid: $uuid);
		return true;
	}//end writeCopy()

	/**
	 * The id of the copy a registration already has, or null.
	 *
	 * @param string $registrationId The registration.
	 *
	 * @return string|null
	 */
	private function existingCopyId(string $registrationId): ?string {
		$existing = $this->fetcher->getObjectsWithAppAuthority(objectType: 'eventcarerecord', filters: ['registration' => $registrationId], limit: 1);
		$uuid = (string)($existing[0]['id'] ?? '');
		if ($uuid === '') {
			return null;
		}

		return $uuid;
	}//end existingCopyId()

	/**
	 * Bring the copies of a player's accepted registrations up to date; events that started are left alone.
	 *
	 * @param string $playerId The player whose details changed.
	 *
	 * @return int How many copies were written.
	 *
	 * @spec openspec/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
	 */
	public function refreshForPlayer(string $playerId): int {
		$written = 0;
		foreach ($this->acceptedRegistrations(playerId: $playerId) as $registration) {
			if ($this->copyForRegistration(registration: $registration) === true) {
				$written++;
			}
		}

		return $written;
	}//end refreshForPlayer()

	/**
	 * The accepted registrations of a player; none when there is no player or they cannot be read.
	 *
	 * @param string $playerId The player.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function acceptedRegistrations(string $playerId): array {
		if ($playerId === '') {
			return [];
		}

		try {
			return $this->fetcher->getObjectsWithAppAuthority(
				objectType: 'registration',
				filters: ['player' => $playerId, 'status' => RegistrationService::ACCEPTED],
				limit: self::MAX_REGISTRATIONS
			);
		} catch (Throwable $e) {
			$this->logger->error(
				'Larpinq: the registrations of player {player} could not be read to refresh their care copies.',
				['player' => $playerId, 'exception' => $e]
			);
			return [];
		}
	}//end acceptedRegistrations()

	/**
	 * The copy of a player's care details for one event, or null when the event has no end to count from.
	 *
	 * @param array<string, mixed> $event The event.
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $player The player.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/specs/player-care-details/spec.md#requirement-event-care-records-are-erased-after-the-event-req-pce-003
	 */
	public static function recordFor(array $event, array $registration, array $player): ?array {
		$end = self::eventEnd(event: $event);
		if ($end === null) {
			return null;
		}

		$record = [
			'event' => (string)($event['id'] ?? ($registration['event'] ?? '')),
			'registration' => (string)$registration['id'],
			'player' => (string)$registration['player'],
		];
		$record = array_merge($record, self::careFields(player: $player));
		$record['eventEndDate'] = $end->format(DateTimeInterface::ATOM);
		$record['eraseAfter'] = $end->modify('+' . self::retentionDays(event: $event) . ' days')->format(DateTimeInterface::ATOM);

		return $record;
	}//end recordFor()

	/**
	 * The player's care fields, each a non-blank string or null.
	 *
	 * @param array<string, mixed> $player The player.
	 *
	 * @return array<string, string|null>
	 */
	private static function careFields(array $player): array {
		$fields = [];
		foreach (self::FIELDS as $field) {
			$value = $player[$field] ?? null;
			$fields[$field] = null;
			if (is_string($value) === true && trim($value) !== '') {
				$fields[$field] = $value;
			}
		}

		return $fields;
	}//end careFields()

	/**
	 * The days a copy is kept after the event: the event's own, else the default.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return int
	 */
	private static function retentionDays(array $event): int {
		$days = $event['careRetentionDays'] ?? self::DEFAULT_RETENTION_DAYS;
		if (is_int($days) === false || $days < 0) {
			return self::DEFAULT_RETENTION_DAYS;
		}

		return $days;
	}//end retentionDays()

	/**
	 * Whether a copy carries no care detail at all.
	 *
	 * @param array<string, mixed> $record The copy.
	 *
	 * @return bool
	 */
	private static function isEmpty(array $record): bool {
		foreach (self::FIELDS as $field) {
			if ($record[$field] !== null) {
				return false;
			}
		}

		return true;
	}//end isEmpty()

	/**
	 * When the event ends: its end date, else its start date.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return DateTimeImmutable|null
	 */
	private static function eventEnd(array $event): ?DateTimeImmutable {
		foreach (['endDate', 'startDate'] as $field) {
			$moment = self::moment(value: ($event[$field] ?? null));
			if ($moment !== null) {
				return $moment;
			}
		}

		return null;
	}//end eventEnd()

	/**
	 * Whether the event has started; an event without a start date has not.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return bool
	 */
	private function hasStarted(array $event): bool {
		$start = self::moment(value: ($event['startDate'] ?? null));

		return $start !== null && $start->getTimestamp() <= $this->time->getTime();
	}//end hasStarted()

	/**
	 * A date or date-time value as a moment, or null.
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
		} catch (Throwable) {
			return null;
		}
	}//end moment()
}//end class
