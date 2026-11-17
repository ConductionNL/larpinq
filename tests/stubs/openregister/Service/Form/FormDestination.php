<?php

/**
 * Test-only stub of OpenRegister's FormDestination, in the shape read at openregister main
 * (lib/Service/Form/FormDestination.php). Appended to the test autoloader only, so a real OpenRegister wins.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Form;

use OCA\OpenRegister\Db\Schema;

/**
 * Where a form submits: the destination schema, and the audience when the form is not internal.
 */
final class FormDestination {
	/**
	 * @param Schema      $schema   The destination schema.
	 * @param string|null $audience The audience, when the form is not internal.
	 */
	public function __construct(
		public readonly Schema $schema,
		public readonly ?string $audience = null,
	) {
	}
}
