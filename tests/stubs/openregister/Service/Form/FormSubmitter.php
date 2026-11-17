<?php

/**
 * Test-only stub of OpenRegister's FormSubmitter, in the shape of openregister's
 * build/forms-registration-is-checked-at-binding (lib/Service/Form/FormSubmitter.php), which adds
 * the trailing appAuthority flag. Appended to the test autoloader only, so a real OpenRegister wins.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service\Form;

use OCP\IUser;

/**
 * Who a submit is by and where it lands.
 */
final class FormSubmitter {
	/**
	 * @param IUser|null  $subject      The signed-in subject; null for anonymous.
	 * @param string      $scope        The form id the key and upload tokens belong to.
	 * @param string|null $draftId      The draft saved into or left, or null.
	 * @param object|null $owner        The draft's asserted owner; null takes the subject.
	 * @param bool        $appAuthority Whether an in-process app vouches for this fresh submit.
	 */
	public function __construct(
		public readonly ?IUser $subject = null,
		public readonly string $scope = '',
		public readonly ?string $draftId = null,
		public readonly ?object $owner = null,
		public readonly bool $appAuthority = false,
	) {
	}
}
