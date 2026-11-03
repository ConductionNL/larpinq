<?php

/**
 * ReadsRelationId for Larpinq
 *
 * Reads the UUID out of an OpenRegister relation value, which may be a plain
 * string or an object with an id.
 *
 * @category  Support
 * @package   OCA\Larpinq\Support
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-builds/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Support;

/**
 * The UUID of a relation value.
 *
 * @category Support
 * @package  OCA\Larpinq\Support
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-builds/spec.md
 */
trait ReadsRelationId {
	/**
	 * The UUID of a relation value, which OpenRegister may hand over as a
	 * string or as an object with an id.
	 *
	 * @param mixed $value The relation value.
	 *
	 * @return string The UUID, or ''.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 * @spec openspec/specs/events-players/spec.md
	 * @spec openspec/specs/character-connections/spec.md
	 */
	private function idOf(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? $value['uuid'] ?? '');
		}

		if (is_string($value) === false) {
			return '';
		}

		return $value;
	}//end idOf()
}//end trait
