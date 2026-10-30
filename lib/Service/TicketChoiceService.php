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

use Psr\Log\LoggerInterface;

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
	 * The event's ticket types, options and codes.
	 *
	 * @var TicketCatalog
	 */
	private readonly TicketCatalog $catalog;

	/**
	 * How many places of a ticket type are taken.
	 *
	 * @var RegistrationPlaces
	 */
	private readonly RegistrationPlaces $places;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the event's ticket types, options, codes and registrations.
	 * @param RegistrationService $registrations The event lock and the ticket type place limits.
	 * @param LoggerInterface $logger The logger.
	 * @param TicketCatalog|null $catalog The event's ticket types, options and codes; built from the fetcher when absent.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		RegisterObjectFetcher $fetcher,
		private readonly RegistrationService $registrations,
		LoggerInterface $logger,
		?TicketCatalog $catalog = null,
	) {
		$this->catalog = ($catalog ?? new TicketCatalog(fetcher: $fetcher, logger: $logger));
		$this->places = new RegistrationPlaces(fetcher: $fetcher);
	}//end __construct()

	/**
	 * How many accepted registrations of an event chose each ticket type and
	 * each option. Counts only: no money total.
	 *
	 * @param string $eventId The event.
	 *
	 * @return array<string, list<array<string, mixed>>> `{ticketTypes: [...], options: [...]}`.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function counts(string $eventId): array {
		return $this->catalog->counts(eventId: $eventId);
	}//end counts()

	/**
	 * Check and price the choices of a registration, as the fields to set or
	 * the reason it is refused. A refusal releases the event's lock.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed>|null $old The registration as stored, or null on create.
	 *
	 * @return array{refusal: string|null, changes: array<string, mixed>} The outcome.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function check(array $new, ?array $old): array {
		try {
			return ['refusal' => null, 'changes' => $this->choose(new: $new, old: $old)];
		} catch (TicketChoiceRefusedException $e) {
			$this->registrations->release(eventId: (string)($new['event'] ?? ''));
			return ['refusal' => $e->getMessage(), 'changes' => []];
		}
	}//end check()

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

		$codeRow = $this->catalog->matchCode(eventId: $eventId, code: $code, registration: $new, keptCode: (string)($before['accessCode'] ?? ''));
		$lines = [];
		if ($ticketId !== '') {
			$lines[] = $this->ticketLine(new: $new, old: $before, ticketId: $ticketId, codeRow: $codeRow);
		}

		return $this->changes(lines: array_merge($lines, $this->optionLines(new: $new, old: $before, optionIds: $optionIds)), codeRow: $codeRow);
	}//end choose()

	/**
	 * The fields to set from checked lines and the matched code.
	 *
	 * @param list<array<string, mixed>> $lines The price lines.
	 * @param array<string, mixed>|null $codeRow The matched code.
	 *
	 * @return array<string, mixed> The fields to set.
	 *
	 * @throws TicketChoiceRefusedException When the lines are in more than one currency.
	 */
	private function changes(array $lines, ?array $codeRow): array {
		if (count(array_unique(array_column($lines, 'currency'))) > 1) {
			throw new TicketChoiceRefusedException('All choices of a registration must be in one currency.');
		}

		$changes = ['lines' => $lines];
		if ($codeRow !== null) {
			$changes['accessCode'] = (string)($codeRow['id'] ?? '');
		}

		return $changes;
	}//end changes()

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
				$codeRow = $this->catalog->matchCode(
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
		foreach ($this->catalog->rows(objectType: 'tickettype', eventId: $eventId) as $ticket) {
			$id = (string)($ticket['id'] ?? '');
			if ($id !== $chosen && $this->catalog->offered(ticket: $ticket, codeRow: $codeRow) === false) {
				continue;
			}

			$ticketTypes[] = array_merge(
				$this->catalog->summary(row: $ticket, fields: ['role']),
				['full' => $this->places->ticketTypeFull(registration: array_merge($registration, ['ticketType' => $id]))]
			);
		}

		usort($ticketTypes, static fn (array $one, array $two): int => ($one['order'] <=> $two['order']));
		$options = [];
		foreach ($this->catalog->rows(objectType: 'registrationoption', eventId: $eventId) as $option) {
			$full = $this->places->optionFull(option: $option, registration: $registration);
			$options[] = array_merge($this->catalog->summary(row: $option, fields: ['category']), ['full' => $full]);
		}

		return [
			'ticketTypes' => $ticketTypes,
			'options' => $options,
			'code' => $state,
			'chosen' => ['ticketType' => $chosen, 'options' => $this->catalog->ids(value: $registration['options'] ?? [])],
		];
	}//end offer()

	/**
	 * The chosen ticket type, options (sorted) and code of a registration.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array{0: string, 1: list<string>, 2: string} The choices.
	 */
	private function choicesOf(array $registration): array {
		$options = $this->catalog->ids(value: $registration['options'] ?? []);
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
		$ticket = $this->catalog->find(objectType: 'tickettype', eventId: (string)($new['event'] ?? ''), id: $ticketId);
		if ($ticket === null) {
			throw new TicketChoiceRefusedException('This ticket type is not offered for this event.');
		}

		if (($ticket['hidden'] ?? false) === true && $this->catalog->unlocks(codeRow: $codeRow, ticketId: $ticketId) === false) {
			throw new TicketChoiceRefusedException('This ticket type needs a code that unlocks it.');
		}

		$kept = $this->keptLine(old: $old, kind: 'ticket', ref: $ticketId);
		if ($kept !== null) {
			return $kept;
		}

		if ($this->catalog->onSale(ticket: $ticket) === false) {
			throw new TicketChoiceRefusedException('This ticket type is not on sale now.');
		}

		if ((string)($new['status'] ?? '') === RegistrationService::ACCEPTED && $this->places->ticketTypeFull(registration: $new) === true) {
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
			$option = $this->catalog->find(objectType: 'registrationoption', eventId: (string)($new['event'] ?? ''), id: $optionId);
			if ($option === null) {
				throw new TicketChoiceRefusedException('This option is not offered for this event.');
			}

			$kept = $this->keptLine(old: $old, kind: 'option', ref: $optionId);
			if ($kept !== null) {
				$lines[] = $kept;
				continue;
			}

			if ($this->places->optionFull(option: $option, registration: $new) === true) {
				throw new TicketChoiceRefusedException('This option is full.');
			}

			$lines[] = $this->line(kind: 'option', row: $option);
		}

		return $lines;
	}//end optionLines()


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

}//end class
