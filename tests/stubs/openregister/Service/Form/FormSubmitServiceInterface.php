<?php

/**
 * Test-only stub of OpenRegister's FormSubmitServiceInterface, reduced to submit() as read at
 * openregister main (lib/Service/Form/FormSubmitServiceInterface.php). Appended to the test
 * autoloader only, so a real OpenRegister wins.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Form;

/**
 * Submit forms into their destinations.
 */
interface FormSubmitServiceInterface {
	/**
	 * Submit one payload into one or several destinations, all or none.
	 *
	 * @param FormSubmission $submission     The writes, the payload and the submitter.
	 * @param string|null    $idempotencyKey The Idempotency-Key, when the caller sent one.
	 *
	 * @return array<string, mixed> The answer: reference, id, receivedAt, confirmation, objects.
	 */
	public function submit(FormSubmission $submission, ?string $idempotencyKey = null): array;
}
