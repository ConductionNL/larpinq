<?php

/**
 * IdListNormaliser for Larpinq
 *
 * OpenRegister relation fields reach the app in three shapes depending on how
 * they were written and whether they were expanded on read: a list of UUID
 * strings, a list of `{id: ...}` objects, or a single bare value. Normalising
 * that in one place keeps every consumer free of the shape check.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Normalises a relation value to a flat list of string ids.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
class IdListNormaliser {
	/**
	 * Normalise a value to a list of string ids.
	 *
	 * Tolerates arrays of strings, arrays of {id} objects, or a single value.
	 * Null and empty-string entries are dropped rather than becoming ''.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return array<int,string> The id list.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function normalise(mixed $value): array {
		if (is_array($value) === false) {
			if ($value === null || $value === '') {
				return [];
			}

			return [(string)$value];
		}

		$ids = [];
		foreach ($value as $entry) {
			if (is_array($entry) === true) {
				if (isset($entry['id']) === true) {
					$ids[] = (string)$entry['id'];
				}

				continue;
			}

			if ($entry !== null && $entry !== '') {
				$ids[] = (string)$entry;
			}
		}

		return $ids;
	}//end normalise()
}//end class
