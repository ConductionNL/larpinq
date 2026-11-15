<?php

/**
 * Larpinq Registration Reads
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Throwable;

/**
 * The reads a registration change starts from: the registration itself (or a
 * 404 refusal) and its event (or an empty event). Shared by the cancel and the
 * transfer flows, which check the caller's rights themselves.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
final class RegistrationReads {
	/**
	 * A registration, read by larpinq (the caller's rights are checked by the caller).
	 *
	 * @param RegisterObjectFetcher $fetcher The register object fetcher.
	 * @param string $registrationId The registration.
	 *
	 * @return array<string, mixed> The registration.
	 *
	 * @throws RegistrationChangeRefusedException When it does not exist.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public static function registration(RegisterObjectFetcher $fetcher, string $registrationId): array {
		try {
			return $fetcher->getObject(objectType: 'registration', id: $registrationId);
		} catch (Throwable $e) {
			throw new RegistrationChangeRefusedException('Registration not found.', 404);
		}
	}//end registration()

	/**
	 * The registration's event, or an empty event when it cannot be read.
	 *
	 * @param RegisterObjectFetcher $fetcher The register object fetcher.
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array<string, mixed> The event.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public static function event(RegisterObjectFetcher $fetcher, array $registration): array {
		try {
			return $fetcher->getObject(objectType: 'event', id: (string)($registration['event'] ?? ''));
		} catch (Throwable $e) {
			return [];
		}
	}//end event()
}//end class
