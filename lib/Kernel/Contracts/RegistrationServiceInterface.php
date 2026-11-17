<?php

/**
 * Contract of RegistrationService
 *
 * The public surface of RegistrationService that other components call, so they depend
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
 * Contract of RegistrationService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface RegistrationServiceInterface {

	public const PENDING = 'pending';

	public const ACCEPTED = 'accepted';

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
	public function beforeCreate(array $registration): array;

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
	public function beforeUpdate(array $new, array $old, string $actingUid): array;

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
	public function afterWrite(array $new, ?array $old): void;
}//end interface
