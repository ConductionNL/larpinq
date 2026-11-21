<?php

/**
 * Test-only stub of OpenRegister's FormDestinationValidatorInterface, in the shape read at openregister main
 * (lib/Service/Form/FormDestinationValidatorInterface.php). Appended to the test autoloader only, so a real OpenRegister wins.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Form;

/**
 * Judges a form's mapping against its destination schema.
 */
interface FormDestinationValidatorInterface {
	/**
	 * @param array<string, mixed> $mapping     The mapping: `fields` and `fixed`.
	 * @param FormDestination      $destination The destination schema.
	 *
	 * @return array<int, array{field?: string, property: string, code: string, message: string}> The findings; empty when valid.
	 */
	public function validate(array $mapping, FormDestination $destination): array;
}
