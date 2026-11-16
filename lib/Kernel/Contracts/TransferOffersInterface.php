<?php

/**
 * Contract of TransferOffers
 *
 * The public surface of TransferOffers that other components call, so they depend
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

use DateTimeImmutable;

/**
 * Contract of TransferOffers.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TransferOffersInterface {

	/**
	 * Offer a registration to another player's account.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The participant.
	 * @param string $recipientUid The account of the player it goes to.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws \OCA\Larpinq\Service\RegistrationChangeRefusedException When it cannot be offered.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function offer(string $registrationId, string $actingUid, string $recipientUid): array;

	/**
	 * Accept an offered registration: it becomes the caller's.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The account it was offered to.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws \OCA\Larpinq\Service\RegistrationChangeRefusedException When there is no open offer for the caller.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function accept(string $registrationId, string $actingUid): array;

	/**
	 * Withdraw an open offer: the participant or a game master.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid Who withdraws.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws \OCA\Larpinq\Service\RegistrationChangeRefusedException When there is no open offer, or not theirs.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function withdraw(string $registrationId, string $actingUid): array;

	/**
	 * The daily pass: lapse every offer that stood 7 days or reached the cancel-by date.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return int How many offers lapsed.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function lapse(DateTimeImmutable $now): int;
}//end interface
