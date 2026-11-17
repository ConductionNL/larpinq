<?php

/**
 * Test-only stub of OpenRegister's FormSubmission, in the shape read at openregister main
 * (lib/Service/Form/FormSubmission.php). Appended to the test autoloader only, so a real OpenRegister wins.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Form;

/**
 * One request to the submit path: what to write, what was filled in, and by whom.
 */
final class FormSubmission {
	/**
	 * @param array<int, array<string, mixed>> $writes    The writes, in order.
	 * @param array<string, mixed>             $payload   What the submitter filled in.
	 * @param FormSubmitter                    $submitter Who submits it and where it lands.
	 */
	public function __construct(
		public readonly array $writes,
		public readonly array $payload,
		public readonly FormSubmitter $submitter = new FormSubmitter(),
	) {
	}
}
