<?php

/**
 * ObjectWrite for Larpinq
 *
 * The data of one OpenRegister pre-write event, as the write checks read it.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

/**
 * One write: the candidate data, the stored data, the object id and whether it is a create.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
final class ObjectWrite {

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $data The candidate object.
	 * @param array<string,mixed> $old The stored object ([] on a create).
	 * @param string $id The object's id ('' on a create without one).
	 * @param bool $isCreate Whether this is a create.
	 */
	public function __construct(
		public readonly array $data,
		public readonly array $old,
		public readonly string $id,
		public readonly bool $isCreate,
	) {
	}//end __construct()

	/**
	 * The object's id, or null when it has none yet.
	 *
	 * @return string|null The id.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function idOrNull(): ?string {
		if ($this->id === '') {
			return null;
		}

		return $this->id;
	}//end idOrNull()
}//end class
