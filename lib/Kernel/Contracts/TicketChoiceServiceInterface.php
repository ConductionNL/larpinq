<?php

/**
 * Contract of TicketChoiceService
 *
 * The public surface of TicketChoiceService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

/**
 * Contract of TicketChoiceService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TicketChoiceServiceInterface {

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
	public function counts(string $eventId): array;

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
	public function offer(array $registration, string $code): array;
}//end interface
