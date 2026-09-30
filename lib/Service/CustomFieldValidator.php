<?php

/**
 * Checks extra character field values against their definitions.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Pure checks of custom field values (characters-custom-fields, design D3).
 *
 * A value must have a definition in the character's world, sit in the
 * property its visibility names, and match its type. Null clears a value and
 * is always allowed.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
class CustomFieldValidator {

	/**
	 * The property each visibility keeps its values in.
	 *
	 * @var array<string, string>
	 */
	public const PROPERTY_OF = [
		'owner' => 'customFields',
		'gamemasters' => 'customFieldsPrivate',
	];

	/**
	 * The errors for the given values, by key.
	 *
	 * @param string $property The property the values sit in (customFields or customFieldsPrivate).
	 * @param array<string, mixed> $values The values to check, by key.
	 * @param array<string, array<string, mixed>> $definitions The definitions of the world, by key.
	 *
	 * @return array<string, string> A message per refused key; empty when all pass.
	 *
	 * @spec openspec/specs/character-custom-fields/spec.md
	 */
	public function validate(string $property, array $values, array $definitions): array {
		$errors = [];
		foreach ($values as $key => $value) {
			$key = (string)$key;
			$error = $this->errorFor(property: $property, definition: ($definitions[$key] ?? null), value: $value);
			if ($error !== null) {
				$errors[$key] = $error;
			}
		}

		return $errors;
	}//end validate()

	/**
	 * The error for one value, or null.
	 *
	 * @param string $property The property the value sits in.
	 * @param array<string, mixed>|null $definition The definition, or null when there is none.
	 * @param mixed $value The value.
	 *
	 * @return string|null The message, or null when the value passes.
	 */
	private function errorFor(string $property, ?array $definition, mixed $value): ?string {
		if ($value === null) {
			return null;
		}

		if ($definition === null) {
			return 'There is no such field for characters of this world.';
		}

		$visibility = (string)($definition['visibility'] ?? 'owner');
		if ((self::PROPERTY_OF[$visibility] ?? 'customFields') !== $property) {
			return 'This field belongs in ' . (self::PROPERTY_OF[$visibility] ?? 'customFields') . '.';
		}

		return $this->typeError(definition: $definition, value: $value);
	}//end errorFor()

	/**
	 * The error when the value does not match the field type, or null.
	 *
	 * @param array<string, mixed> $definition The definition.
	 * @param mixed $value The value.
	 *
	 * @return string|null The message, or null when the value passes.
	 */
	private function typeError(array $definition, mixed $value): ?string {
		$type = (string)($definition['fieldType'] ?? 'text');
		$ok = match ($type) {
			'number' => is_int($value) === true || is_float($value) === true,
			'yes-no' => is_bool($value) === true,
			'choice' => is_string($value) === true && in_array($value, (array)($definition['choices'] ?? []), true) === true,
			default => is_string($value) === true,
		};
		if ($ok === true) {
			return null;
		}

		return match ($type) {
			'number' => 'The value must be a number.',
			'yes-no' => 'The value must be yes or no (true or false).',
			'choice' => 'The value must be one of the choices of the field.',
			default => 'The value must be text.',
		};
	}//end typeError()
}//end class
