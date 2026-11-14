<?php

/**
 * Larpinq Registration Write Check
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

/**
 * What is checked before a registration is stored: the character a player
 * brings, then the ticket type, options and code with their price lines.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationWriteCheck {

	/**
	 * Constructor.
	 *
	 * @param RegistrationCharacterCheck $characterCheck Whether a player may bring a character.
	 * @param TicketChoiceService $ticketChoices The ticket type, options and code, and their price lines.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegistrationCharacterCheck $characterCheck,
		private readonly TicketChoiceService $ticketChoices,
	) {
	}//end __construct()

	/**
	 * Check a registration before it is stored.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed>|null $stored The stored registration, or null on create.
	 *
	 * @return array{refusal: string|null, changes: array<string, mixed>} The reason it is refused, or the fields to set.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function check(array $new, ?array $stored): array {
		$character = (string)($new['character'] ?? '');
		if ($character !== '' && $character !== (string)($stored['character'] ?? '')) {
			$refusal = $this->characterCheck->refusal(registration: $new);
			if ($refusal !== null) {
				return ['refusal' => $refusal, 'changes' => []];
			}
		}

		return $this->ticketChoices->check(new: $new, old: $stored);
	}//end check()
}//end class
