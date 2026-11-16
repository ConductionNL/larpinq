<?php

/**
 * Larpinq Sign-up Form Binding
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
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\Larpinq\Kernel\Contracts\SignupFormBindingInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Judges an event's sign-up form binding against the registration schema.
 *
 * The binding is the event's `signupForm` with its `signupFormMapping`: which
 * question fills which registration property. OpenRegister's form destination
 * validator judges that mapping, together with the values the sign-up listener
 * sets itself, against the live registration schema. Findings mean the binding
 * must not be saved.
 *
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
 */
class SignupFormBinding implements SignupFormBindingInterface {

	private const VALIDATOR = 'OCA\OpenRegister\Service\Form\FormDestinationValidatorInterface';

	private const SCHEMA_MAPPER = 'OCA\OpenRegister\Db\SchemaMapper';

	private const DESTINATION = 'OCA\OpenRegister\Service\Form\FormDestination';

	/**
	 * The properties the sign-up listener fills itself, with a value of the
	 * shape it writes, so the validator counts them as sourced and checks
	 * they still fit the schema.
	 */
	private const LISTENER_FIXED = [
		'event' => '00000000-0000-4000-8000-000000000000',
		'submitterUid' => 'uid',
		'status' => RegistrationService::PENDING,
		'submissionId' => 1,
		'submittedAt' => '2026-01-01T00:00:00+00:00',
	];

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface   $container The container OpenRegister's services come from.
	 * @param RegisterSchemaLookup $schemas   Resolves larpinq's registration schema.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly RegisterSchemaLookup $schemas,
	) {
	}//end __construct()

	/**
	 * Whether a write changes the event's binding: the form or its mapping.
	 *
	 * @param array<string, mixed> $candidate The event as it will be stored.
	 * @param array<string, mixed> $old       The event as stored now; empty on create.
	 *
	 * @return bool True when the form or the mapping changed and a form is bound.
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
	 */
	public function bindingChanged(array $candidate, array $old): bool {
		if (self::formOf(event: $candidate) === null) {
			return false;
		}

		return self::formOf(event: $candidate) !== self::formOf(event: $old)
			|| ($candidate['signupFormMapping'] ?? null) != ($old['signupFormMapping'] ?? null);
	}//end bindingChanged()

	/**
	 * The mapping the validator judges: the event's mapped questions, and the
	 * values the sign-up listener sets.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return array{fields: list<array{field: string, type: string, property: string}>, fixed: array<string, mixed>}
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
	 */
	public static function mapping(array $event): array {
		$fields = [];
		$rows = ($event['signupFormMapping'] ?? []);
		if (is_array($rows) === false) {
			$rows = [];
		}

		foreach ($rows as $row) {
			if (is_array($row) === false) {
				continue;
			}

			$fields[] = [
				'field' => (string)($row['field'] ?? ''),
				'type' => (string)($row['type'] ?? ''),
				'property' => (string)($row['property'] ?? ''),
			];
		}

		return ['fields' => $fields, 'fixed' => self::LISTENER_FIXED];
	}//end mapping()

	/**
	 * Every problem with the event's binding, judged by OpenRegister.
	 *
	 * @param array<string, mixed> $event The event as it will be stored.
	 *
	 * @return array<int, array<string, mixed>> The findings; empty when the binding is valid.
	 *
	 * @throws RuntimeException When the binding cannot be judged (no validator, no registration schema).
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
	 */
	public function findings(array $event): array {
		if (interface_exists(self::VALIDATOR) === false || class_exists(self::DESTINATION) === false) {
			throw new RuntimeException('OpenRegister has no form destination validator.');
		}

		$validator = $this->container->get(self::VALIDATOR);
		$destinationClass = self::DESTINATION;
		$destination = new $destinationClass(schema: $this->registrationSchema());

		return array_values((array)$validator->validate(mapping: self::mapping(event: $event), destination: $destination));
	}//end findings()

	/**
	 * The live registration schema.
	 *
	 * @return object The OpenRegister schema entity.
	 *
	 * @throws RuntimeException When larpinq has no registration schema.
	 */
	private function registrationSchema(): object {
		$schemaId = $this->schemas->resolve(objectTypeLower: 'registration')[1];
		if ($schemaId === '') {
			throw new RuntimeException('Larpinq has no registration schema.');
		}

		return $this->container->get(self::SCHEMA_MAPPER)->find($schemaId, _rbac: false, _multitenancy: false);
	}//end registrationSchema()

	/**
	 * The bound form id of an event, null when none.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return integer|null The form id.
	 */
	private static function formOf(array $event): ?int {
		$form = (int)($event['signupForm'] ?? 0);
		if ($form <= 0) {
			return null;
		}

		return $form;
	}//end formOf()
}//end class
