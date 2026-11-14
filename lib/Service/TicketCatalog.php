<?php

/**
 * Larpinq Ticket Catalog
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
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * An event's ticket types, options, codes and accepted registrations, read
 * with the app's authority, and the rules of when a ticket type is on sale
 * and a code works.
 *
 * A player cannot read a hidden ticket type or any code, yet may use one, so
 * everything here reads as the app.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class TicketCatalog {

	/**
	 * Upper bound of the rows one event is read with.
	 *
	 * @var integer
	 */
	private const MAX_ROWS = 2000;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the event's ticket types, options, codes and registrations.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The code of the event that matches what was typed, checked; null when nothing was typed.
	 *
	 * @param string $eventId The event.
	 * @param string $code What was typed.
	 * @param array<string, mixed> $registration The registration (not counted as a use).
	 * @param string $keptCode The code the registration already uses (its window and uses are not checked again).
	 *
	 * @return array<string, mixed>|null The code.
	 *
	 * @throws TicketChoiceRefusedException When the code is unknown, outside its window or used up.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function matchCode(string $eventId, string $code, array $registration, string $keptCode): ?array {
		if ($code === '') {
			return null;
		}

		foreach ($this->rows(objectType: 'accesscode', eventId: $eventId) as $row) {
			if (strcasecmp((string)($row['code'] ?? ''), $code) !== 0) {
				continue;
			}

			if ((string)($row['id'] ?? '') !== $keptCode) {
				$this->checkCode(row: $row, registrationId: (string)($registration['id'] ?? ''));
			}

			return $row;
		}

		throw new TicketChoiceRefusedException('This code is not known for this event.');
	}//end matchCode()

	/**
	 * Refuse a code outside its window or past its uses.
	 *
	 * @param array<string, mixed> $row The code.
	 * @param string $registrationId The registration (not counted as a use).
	 *
	 * @return void
	 *
	 * @throws TicketChoiceRefusedException When the code does not work now.
	 */
	private function checkCode(array $row, string $registrationId): void {
		if ($this->before(value: $row['validFrom'] ?? null) === true) {
			throw new TicketChoiceRefusedException('This code is not valid yet.');
		}

		if ($this->after(value: $row['validUntil'] ?? null) === true) {
			throw new TicketChoiceRefusedException('This code has expired.');
		}

		if (is_numeric($row['maxUses'] ?? null) === false) {
			return;
		}

		$uses = $this->fetcher->getObjectsWithAppAuthority(
			objectType: 'registration',
			filters: ['event' => (string)($row['event'] ?? ''), 'accessCode' => (string)($row['id'] ?? '')],
			limit: self::MAX_ROWS
		);
		$counted = array_filter(
			$uses,
			static fn (array $use): bool => (string)($use['id'] ?? '') !== $registrationId && (string)($use['status'] ?? '') !== 'cancelled'
		);
		if (count($counted) >= (int)$row['maxUses']) {
			throw new TicketChoiceRefusedException('This code has been used up.');
		}
	}//end checkCode()

	/**
	 * Whether a ticket type is offered: on sale, and not hidden unless the code unlocks it.
	 *
	 * @param array<string, mixed> $ticket The ticket type.
	 * @param array<string, mixed>|null $codeRow The matched code.
	 *
	 * @return bool True when offered.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function offered(array $ticket, ?array $codeRow): bool {
		if (($ticket['hidden'] ?? false) === true && $this->unlocks(codeRow: $codeRow, ticketId: (string)($ticket['id'] ?? '')) === false) {
			return false;
		}

		return $this->onSale(ticket: $ticket);
	}//end offered()

	/**
	 * Whether the code unlocks the ticket type.
	 *
	 * @param array<string, mixed>|null $codeRow The matched code.
	 * @param string $ticketId The ticket type.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function unlocks(?array $codeRow, string $ticketId): bool {
		if ($codeRow === null) {
			return false;
		}

		return in_array($ticketId, $this->ids(value: $codeRow['unlocks'] ?? []), true);
	}//end unlocks()

	/**
	 * Whether now is inside the ticket type's sale window.
	 *
	 * @param array<string, mixed> $ticket The ticket type.
	 *
	 * @return bool True when on sale.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function onSale(array $ticket): bool {
		return $this->before(value: $ticket['saleFrom'] ?? null) === false && $this->after(value: $ticket['saleUntil'] ?? null) === false;
	}//end onSale()

	/**
	 * Whether now is before a moment; false without a readable moment.
	 *
	 * @param mixed $value The moment.
	 *
	 * @return bool True when the moment is still to come.
	 */
	private function before(mixed $value): bool {
		$moment = $this->moment(value: $value);
		return $moment !== null && $moment > new DateTimeImmutable();
	}//end before()

	/**
	 * Whether now is after a moment; false without a readable moment.
	 *
	 * @param mixed $value The moment.
	 *
	 * @return bool True when the moment has passed.
	 */
	private function after(mixed $value): bool {
		$moment = $this->moment(value: $value);
		return $moment !== null && $moment < new DateTimeImmutable();
	}//end after()

	/**
	 * A stored moment, or null when empty or unreadable.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return DateTimeImmutable|null The moment.
	 */
	private function moment(mixed $value): ?DateTimeImmutable {
		if (is_string($value) === false || $value === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: a ticket moment could not be read: {value}', ['value' => $value]);
			return null;
		}
	}//end moment()

	/**
	 * One object of the event, read with the app's authority.
	 *
	 * @param string $objectType The type.
	 * @param string $eventId The event.
	 * @param string $id The object.
	 *
	 * @return array<string, mixed>|null The object, or null when the event has no such object.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function find(string $objectType, string $eventId, string $id): ?array {
		foreach ($this->rows(objectType: $objectType, eventId: $eventId) as $row) {
			if ((string)($row['id'] ?? '') === $id) {
				return $row;
			}
		}

		return null;
	}//end find()

	/**
	 * The objects of one type of the event, read with the app's authority.
	 *
	 * @param string $objectType The type.
	 * @param string $eventId The event.
	 *
	 * @return list<array<string, mixed>> The objects.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function rows(string $objectType, string $eventId): array {
		if ($eventId === '') {
			return [];
		}

		return array_values($this->fetcher->getObjectsWithAppAuthority(objectType: $objectType, filters: ['event' => $eventId], limit: self::MAX_ROWS));
	}//end rows()

	/**
	 * The accepted registrations of the event.
	 *
	 * @param string $eventId The event.
	 *
	 * @return list<array<string, mixed>> The registrations.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	private function accepted(string $eventId): array {
		if ($eventId === '') {
			return [];
		}

		return array_values(
			$this->fetcher->getObjectsWithAppAuthority(
				objectType: 'registration',
				filters: ['event' => $eventId, 'status' => RegistrationService::ACCEPTED],
				limit: self::MAX_ROWS
			)
		);
	}//end accepted()

	/**
	 * A list of uuids from a stored value.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return list<string> The uuids.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function ids(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter(array_map('strval', array_filter($value, 'is_scalar')), static fn (string $id): bool => $id !== ''));
	}//end ids()

	/**
	 * What a player is shown of a ticket type or option.
	 *
	 * @param array<string, mixed> $row The ticket type or option.
	 * @param list<string> $fields Further fields to copy.
	 *
	 * @return array<string, mixed> The summary.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function summary(array $row, array $fields): array {
		$summary = [
			'id' => (string)($row['id'] ?? ''),
			'name' => (string)($row['name'] ?? ''),
			'amount' => (int)($row['amount'] ?? 0),
			'currency' => (string)($row['currency'] ?? 'EUR'),
			'order' => (int)($row['order'] ?? 0),
		];
		foreach ($fields as $field) {
			$summary[$field] = (string)($row[$field] ?? '');
		}

		return $summary;
	}//end summary()

	/**
	 * One counted row: id, name, its kind field and how many chose it.
	 *
	 * @param array<string, mixed> $row The ticket type or option.
	 * @param string $field role or category.
	 * @param array<string, int> $counts Counts by id.
	 *
	 * @return array<string, mixed> The row.
	 */
	private function counted(array $row, string $field, array $counts): array {
		$id = (string)($row['id'] ?? '');
		return ['id' => $id, 'name' => (string)($row['name'] ?? ''), $field => (string)($row[$field] ?? ''), 'count' => ($counts[$id] ?? 0)];
	}//end counted()

	/**
	 * How many accepted registrations of an event chose each ticket type and
	 * each option. Counts only: no money total.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, list<array<string, mixed>>> `{ticketTypes: [{id, name, role, count}], options: [{id, name, category, count}]}`.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function counts(string $eventId): array {
		$tickets = [];
		$options = [];
		foreach ($this->accepted(eventId: $eventId) as $registration) {
			$ticket = (string)($registration['ticketType'] ?? '');
			$tickets[$ticket] = (($tickets[$ticket] ?? 0) + 1);
			foreach ($this->ids(value: $registration['options'] ?? []) as $option) {
				$options[$option] = (($options[$option] ?? 0) + 1);
			}
		}

		$result = ['ticketTypes' => [], 'options' => []];
		foreach ($this->rows(objectType: 'tickettype', eventId: $eventId) as $row) {
			$result['ticketTypes'][] = $this->counted(row: $row, field: 'role', counts: $tickets);
		}

		foreach ($this->rows(objectType: 'registrationoption', eventId: $eventId) as $row) {
			$result['options'][] = $this->counted(row: $row, field: 'category', counts: $options);
		}

		return $result;
	}//end counts()
}//end class
