<?php

/**
 * Refuses character writes whose extra field values do not match their
 * definitions.
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
 * The custom field check the character pre-write listener runs
 * (characters-custom-fields REQ-CCF-004).
 *
 * Only the keys whose value changed are checked, so a value left behind by a
 * deleted definition never blocks the next edit.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */
class CustomFieldGuard {

	/**
	 * The most definitions one world is read with.
	 */
	private const MAX_DEFINITIONS = 500;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher Reads the definitions through OpenRegister.
	 * @param CustomFieldValidator $validator Checks the values.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly CustomFieldValidator $validator,
	) {
	}//end __construct()

	/**
	 * The veto payload for a character write, or null when it may pass.
	 *
	 * @param array<string, mixed> $candidate The character as it will be saved.
	 * @param array<string, mixed> $old The character as it is stored (empty on create).
	 *
	 * @return array<string, mixed>|null `{code, message, fields: {key: message}}`, or null.
	 *
	 * @spec openspec/specs/character-custom-fields/spec.md
	 */
	public function check(array $candidate, array $old): ?array {
		$changed = [];
		foreach (CustomFieldValidator::PROPERTY_OF as $property) {
			$changed[$property] = $this->changedValues(new: ($candidate[$property] ?? []), old: ($old[$property] ?? []));
		}

		if (array_filter($changed) === []) {
			return null;
		}

		$definitions = $this->definitionsFor(world: (string)($candidate['setting'] ?? ''));
		$errors = [];
		foreach ($changed as $property => $values) {
			$errors += $this->validator->validate(property: $property, values: $values, definitions: $definitions);
		}

		if ($errors === []) {
			return null;
		}

		ksort($errors);
		return [
			'code' => 'custom_field_invalid',
			'message' => 'One or more extra fields have a value that does not match the field.',
			'fields' => $errors,
		];
	}//end check()

	/**
	 * The values that differ from the stored ones.
	 *
	 * @param mixed $new The new values.
	 * @param mixed $old The stored values.
	 *
	 * @return array<string, mixed> The changed values, by key.
	 */
	private function changedValues(mixed $new, mixed $old): array {
		if (is_array($new) === false) {
			return [];
		}

		if (is_array($old) === false) {
			$old = [];
		}

		$changed = [];
		foreach ($new as $key => $value) {
			if (array_key_exists($key, $old) === false || $old[$key] !== $value) {
				$changed[(string)$key] = $value;
			}
		}

		return $changed;
	}//end changedValues()

	/**
	 * The definitions of the world and the ones for every world, by key. A
	 * world's own definition wins over a global one with the same key.
	 *
	 * @param string $world The world UUID, or empty.
	 *
	 * @return array<string, array<string, mixed>> The definitions.
	 */
	private function definitionsFor(string $world): array {
		$byKey = [];
		$own = [];
		foreach ($this->objectFetcher->getObjects(objectType: 'characterfield', limit: self::MAX_DEFINITIONS, offset: 0) as $definition) {
			$key = (string)($definition['key'] ?? '');
			$scope = (string)($definition['setting'] ?? '');
			if ($key === '' || ($scope !== '' && $scope !== $world) || isset($own[$key]) === true) {
				continue;
			}

			$byKey[$key] = $definition;
			if ($scope !== '') {
				$own[$key] = true;
			}
		}

		return $byKey;
	}//end definitionsFor()
}//end class
