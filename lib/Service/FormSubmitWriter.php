<?php

/**
 * Larpinq Form Submit Writer
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
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-submit-must-create-the-registration-through-the-submit-service
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Writes one larpinq object through OpenRegister's form submit service, with the app's authority.
 *
 * The submit service validates the object against its schema the way every
 * other form submit is validated, and keeps an idempotency key so a repeated
 * submit creates nothing new. The write is made as the submitting account
 * but with larpinq's authority (FormSubmitter::$appAuthority), because the
 * account may not create the object itself. An OpenRegister without that
 * flag is reported as unavailable, so the caller keeps its own write path.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-submit-must-create-the-registration-through-the-submit-service
 */
class FormSubmitWriter {

	private const SUBMIT = 'OCA\OpenRegister\Service\Form\FormSubmitServiceInterface';

	private const SUBMITTER = 'OCA\OpenRegister\Service\Form\FormSubmitter';

	private const SUBMISSION = 'OCA\OpenRegister\Service\Form\FormSubmission';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface   $container The container OpenRegister's submit service comes from.
	 * @param RegisterSchemaLookup $schemas   Resolves larpinq's register and schema per object type.
	 * @param IUserManager         $users     Finds the submitting account.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly RegisterSchemaLookup $schemas,
		private readonly IUserManager $users,
	) {
	}//end __construct()

	/**
	 * Whether OpenRegister offers a submit with an app's authority.
	 *
	 * @return bool True when the submit service and the appAuthority flag exist.
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-submit-must-create-the-registration-through-the-submit-service
	 */
	public function available(): bool {
		return interface_exists(self::SUBMIT) === true
			&& class_exists(self::SUBMISSION) === true
			&& class_exists(self::SUBMITTER) === true
			&& property_exists(self::SUBMITTER, 'appAuthority') === true
			&& $this->container->has(self::SUBMIT) === true;
	}//end available()

	/**
	 * Submit one object of a larpinq type as the account, with the app's authority.
	 *
	 * @param string               $objectType The larpinq object type, e.g. `registration`.
	 * @param array<string, mixed> $data       The object to create.
	 * @param string               $uid        The submitting account.
	 * @param string               $scope      What the idempotency key belongs to, e.g. the source form.
	 * @param string               $key        The idempotency key.
	 *
	 * @return array<string, mixed> The submit service's answer: reference, id, objects.
	 *
	 * @throws RuntimeException When OpenRegister offers no such submit, or the account is unknown.
	 * @throws \Throwable       The submit service's refusal (e.g. 422 when the object does not fit its schema).
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency; available() checked the classes.
	 * @psalm-suppress MixedMethodCall OpenRegister is an optional dependency resolved dynamically.
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-submit-must-create-the-registration-through-the-submit-service
	 */
	public function submit(string $objectType, array $data, string $uid, string $scope, string $key): array {
		if ($this->available() === false) {
			throw new RuntimeException('OpenRegister has no form submit with an app\'s authority.');
		}

		$subject = $this->users->get($uid);
		if ($subject === null) {
			throw new RuntimeException('The submitting account does not exist.');
		}

		[$register, $schema] = $this->schemas->resolve(objectTypeLower: $objectType);

		$submitterClass = self::SUBMITTER;
		$submissionClass = self::SUBMISSION;
		$submitter = new $submitterClass(subject: $subject, scope: $scope, appAuthority: true);
		$submission = new $submissionClass(
			writes: [['register' => $register, 'schema' => $schema, 'mapping' => null]],
			payload: $data,
			submitter: $submitter
		);

		// OpenRegister is an optional dependency, resolved dynamically.
		return (array)$this->container->get(self::SUBMIT)->submit(submission: $submission, idempotencyKey: $key);
	}//end submit()
}//end class
