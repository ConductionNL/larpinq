<?php

/**
 * Larpinq Ticket Choice Service
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
 * The ticket type, options and code of a registration.
 *
 * Before a registration is stored its choices are checked (same event, sale
 * window, hidden ticket types only with a code that unlocks them, the code's
 * window and uses, full options) and its price lines are written from the
 * chosen objects, so a client never sets its own price. A choice that did not
 * change keeps the price it was made at. Larpinq never adds the lines up: the
 * amount due is shillinq's (hydra ADR-107).
 *
 * Ticket types and codes are read with the app's authority: a player cannot
 * read a hidden ticket type or any code, yet may use one.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class TicketChoiceService {

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
	 * @param RegistrationService $registrations The event lock and the ticket type place limits.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly RegistrationService $registrations,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Check the choices of a registration and write its price lines.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed>|null $old The registration as stored, or null on create.
	 *
	 * @return array<string, mixed> The fields to set (`lines`, `accessCode`).
	 *
	 * @throws TicketChoiceRefusedException When a choice is not allowed.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function choose(array $new, ?array $old): array {
		$before = ($old ?? []);
		if ($old !== null && $this->choicesOf(registration: $new) === $this->choicesOf(registration: $before)) {
			return $this->keptLines(new: $new, old: $before);
		}

		[$ticketId, $optionIds, $code] = $this->choicesOf(registration: $new);
		if ($ticketId === '' && $optionIds === [] && $code === '') {
			return $this->noLines(new: $new);
		}

		$eventId = (string)($new['event'] ?? '');
		if ($this->registrations->hold(eventId: $eventId) === false) {
			throw new TicketChoiceRefusedException('The event is busy. Try again in a moment.');
		}

		$codeRow = $this->matchCode(eventId: $eventId, code: $code, registration: $new, keptCode: (string)($before['accessCode'] ?? ''));
		$lines = [];
		if ($ticketId !== '') {
			$lines[] = $this->ticketLine(new: $new, old: $before, ticketId: $ticketId, codeRow: $codeRow);
		}

		$lines = array_merge($lines, $this->optionLines(new: $new, old: $before, optionIds: $optionIds));
		if (count(array_unique(array_column($lines, 'currency'))) > 1) {
			throw new TicketChoiceRefusedException('All choices of a registration must be in one currency.');
		}

		$changes = ['lines' => $lines];
		if ($codeRow !== null) {
			$changes['accessCode'] = (string)($codeRow['id'] ?? '');
		}

		return $changes;
	}//end choose()

	/**
	 * What a registration's player may choose now: the ticket types on sale
	 * (hidden ones only with a valid code), every option, and whether the code works.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string $code The code the player typed, or empty.
	 *
	 * @return array<string, mixed> `{ticketTypes: [...], options: [...], code: none|valid|invalid, chosen: {...}}`.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function offer(array $registration, string $code): array {
		$eventId = (string)($registration['event'] ?? '');
		$state = 'none';
		$codeRow = null;
		if (trim($code) !== '') {
			try {
				$codeRow = $this->matchCode(
					eventId: $eventId,
					code: trim($code),
					registration: $registration,
					keptCode: (string)($registration['accessCode'] ?? '')
				);
				$state = 'valid';
			} catch (TicketChoiceRefusedException $e) {
				$state = 'invalid';
			}
		}

		$chosen = (string)($registration['ticketType'] ?? '');
		$ticketTypes = [];
		foreach ($this->rows(objectType: 'tickettype', eventId: $eventId) as $ticket) {
			$id = (string)($ticket['id'] ?? '');
			if ($id !== $chosen && $this->offered(ticket: $ticket, codeRow: $codeRow) === false) {
				continue;
			}

			$ticketTypes[] = array_merge(
				$this->summary(row: $ticket, fields: ['role']),
				['full' => $this->registrations->ticketTypeFull(registration: array_merge($registration, ['ticketType' => $id]))]
			);
		}

		usort($ticketTypes, static fn (array $one, array $two): int => ($one['order'] <=> $two['order']));
		$options = [];
		foreach ($this->rows(objectType: 'registrationoption', eventId: $eventId) as $option) {
			$full = $this->optionFull(option: $option, registration: $registration);
			$options[] = array_merge($this->summary(row: $option, fields: ['category']), ['full' => $full]);
		}

		return [
			'ticketTypes' => $ticketTypes,
			'options' => $options,
			'code' => $state,
			'chosen' => ['ticketType' => $chosen, 'options' => $this->ids(value: $registration['options'] ?? [])],
		];
	}//end offer()

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
	 * The chosen ticket type, options (sorted) and code of a registration.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array{0: string, 1: list<string>, 2: string} The choices.
	 */
	private function choicesOf(array $registration): array {
		$options = $this->ids(value: $registration['options'] ?? []);
		sort($options);
		return [(string)($registration['ticketType'] ?? ''), $options, trim((string)($registration['code'] ?? ''))];
	}//end choicesOf()

	/**
	 * Unchanged choices keep their lines and code, whatever a client sent.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed> $old The registration as stored.
	 *
	 * @return array<string, mixed> The fields to set.
	 */
	private function keptLines(array $new, array $old): array {
		$changes = [];
		if (($new['lines'] ?? null) !== ($old['lines'] ?? null)) {
			$changes['lines'] = (array)($old['lines'] ?? []);
		}

		if (isset($old['accessCode']) === true && ($new['accessCode'] ?? null) !== $old['accessCode']) {
			$changes['accessCode'] = $old['accessCode'];
		}

		return $changes;
	}//end keptLines()

	/**
	 * Without choices there are no lines, whatever a client sent.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 *
	 * @return array<string, mixed> The fields to set.
	 */
	private function noLines(array $new): array {
		if (empty($new['lines']) === true) {
			return [];
		}

		return ['lines' => []];
	}//end noLines()

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
	 */
	private function matchCode(string $eventId, string $code, array $registration, string $keptCode): ?array {
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
		if ($this->before(moment: $row['validFrom'] ?? null) === true) {
			throw new TicketChoiceRefusedException('This code is not valid yet.');
		}

		if ($this->after(moment: $row['validUntil'] ?? null) === true) {
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
	 * The price line of the chosen ticket type, checked.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed> $old The registration as stored.
	 * @param string $ticketId The chosen ticket type.
	 * @param array<string, mixed>|null $codeRow The matched code.
	 *
	 * @return array<string, mixed> The line.
	 *
	 * @throws TicketChoiceRefusedException When the ticket type may not be chosen.
	 */
	private function ticketLine(array $new, array $old, string $ticketId, ?array $codeRow): array {
		$ticket = $this->find(objectType: 'tickettype', eventId: (string)($new['event'] ?? ''), id: $ticketId);
		if ($ticket === null) {
			throw new TicketChoiceRefusedException('This ticket type is not offered for this event.');
		}

		if (($ticket['hidden'] ?? false) === true && $this->unlocks(codeRow: $codeRow, ticketId: $ticketId) === false) {
			throw new TicketChoiceRefusedException('This ticket type needs a code that unlocks it.');
		}

		$kept = $this->keptLine(old: $old, kind: 'ticket', ref: $ticketId);
		if ($kept !== null) {
			return $kept;
		}

		if ($this->onSale(ticket: $ticket) === false) {
			throw new TicketChoiceRefusedException('This ticket type is not on sale now.');
		}

		if ((string)($new['status'] ?? '') === RegistrationService::ACCEPTED && $this->registrations->ticketTypeFull(registration: $new) === true) {
			throw new TicketChoiceRefusedException('This ticket type is full.');
		}

		return $this->line(kind: 'ticket', row: $ticket);
	}//end ticketLine()

	/**
	 * The price lines of the chosen options, checked.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed> $old The registration as stored.
	 * @param list<string> $optionIds The chosen options.
	 *
	 * @return list<array<string, mixed>> The lines.
	 *
	 * @throws TicketChoiceRefusedException When an option may not be chosen.
	 */
	private function optionLines(array $new, array $old, array $optionIds): array {
		$lines = [];
		foreach ($optionIds as $optionId) {
			$option = $this->find(objectType: 'registrationoption', eventId: (string)($new['event'] ?? ''), id: $optionId);
			if ($option === null) {
				throw new TicketChoiceRefusedException('This option is not offered for this event.');
			}

			$kept = $this->keptLine(old: $old, kind: 'option', ref: $optionId);
			if ($kept !== null) {
				$lines[] = $kept;
				continue;
			}

			if ($this->optionFull(option: $option, registration: $new) === true) {
				throw new TicketChoiceRefusedException('This option is full.');
			}

			$lines[] = $this->line(kind: 'option', row: $option);
		}

		return $lines;
	}//end optionLines()

	/**
	 * Whether an option with a place limit has no place left for this registration.
	 *
	 * @param array<string, mixed> $option The option.
	 * @param array<string, mixed> $registration The registration (not counted).
	 *
	 * @return bool True when full.
	 */
	private function optionFull(array $option, array $registration): bool {
		if (is_numeric($option['placeLimit'] ?? null) === false) {
			return false;
		}

		$optionId = (string)($option['id'] ?? '');
		$taken = 0;
		foreach ($this->accepted(eventId: (string)($option['event'] ?? '')) as $other) {
			$chose = in_array($optionId, $this->ids(value: $other['options'] ?? []), true);
			if ($chose === true && (string)($other['id'] ?? '') !== (string)($registration['id'] ?? '')) {
				$taken++;
			}
		}

		return $taken >= (int)$option['placeLimit'];
	}//end optionFull()

	/**
	 * Whether a ticket type is offered: on sale, and not hidden unless the code unlocks it.
	 *
	 * @param array<string, mixed> $ticket The ticket type.
	 * @param array<string, mixed>|null $codeRow The matched code.
	 *
	 * @return bool True when offered.
	 */
	private function offered(array $ticket, ?array $codeRow): bool {
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
	 */
	private function unlocks(?array $codeRow, string $ticketId): bool {
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
	 */
	private function onSale(array $ticket): bool {
		return $this->before(moment: $ticket['saleFrom'] ?? null) === false && $this->after(moment: $ticket['saleUntil'] ?? null) === false;
	}//end onSale()

	/**
	 * Whether now is before a moment; false without a readable moment.
	 *
	 * @param mixed $moment The moment.
	 *
	 * @return bool True when the moment is still to come.
	 */
	private function before(mixed $moment): bool {
		$at = $this->moment(value: $moment);
		return $at !== null && $at > new DateTimeImmutable();
	}//end before()

	/**
	 * Whether now is after a moment; false without a readable moment.
	 *
	 * @param mixed $moment The moment.
	 *
	 * @return bool True when the moment has passed.
	 */
	private function after(mixed $moment): bool {
		$at = $this->moment(value: $moment);
		return $at !== null && $at < new DateTimeImmutable();
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
	 * The stored line of an unchanged choice.
	 *
	 * @param array<string, mixed> $old The registration as stored.
	 * @param string $kind ticket or option.
	 * @param string $ref The chosen object.
	 *
	 * @return array<string, mixed>|null The line, or null when the choice is new.
	 */
	private function keptLine(array $old, string $kind, string $ref): ?array {
		foreach ((array)($old['lines'] ?? []) as $line) {
			if (is_array($line) === true && ($line['kind'] ?? '') === $kind && ($line['ref'] ?? '') === $ref) {
				return $line;
			}
		}

		return null;
	}//end keptLine()

	/**
	 * A price line from a ticket type or option as listed now.
	 *
	 * @param string $kind ticket or option.
	 * @param array<string, mixed> $row The ticket type or option.
	 *
	 * @return array<string, mixed> The line.
	 */
	private function line(string $kind, array $row): array {
		return [
			'kind' => $kind,
			'ref' => (string)($row['id'] ?? ''),
			'name' => (string)($row['name'] ?? ''),
			'amount' => (int)($row['amount'] ?? 0),
			'currency' => (string)($row['currency'] ?? 'EUR'),
		];
	}//end line()

	/**
	 * What a player is shown of a ticket type or option.
	 *
	 * @param array<string, mixed> $row The ticket type or option.
	 * @param list<string> $fields Further fields to copy.
	 *
	 * @return array<string, mixed> The summary.
	 */
	private function summary(array $row, array $fields): array {
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
	 * One object of the event, read with the app's authority.
	 *
	 * @param string $objectType The type.
	 * @param string $eventId The event.
	 * @param string $id The object.
	 *
	 * @return array<string, mixed>|null The object, or null when the event has no such object.
	 */
	private function find(string $objectType, string $eventId, string $id): ?array {
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
	 */
	private function rows(string $objectType, string $eventId): array {
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
	 */
	private function ids(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter(array_map('strval', array_filter($value, 'is_scalar')), static fn (string $id): bool => $id !== ''));
	}//end ids()
}//end class
