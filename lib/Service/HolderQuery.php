<?php

/**
 * HolderQuery for Larpinq
 *
 * The question a holder lookup answers: who, other than one character, holds
 * this item or condition, up to how many.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * One holder lookup: the kind and id held, the character never counted and the limit.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
final class HolderQuery {

	/**
	 * Constructor.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param string $id The item or condition id.
	 * @param string|null $exceptCharacter The character being written, never counted.
	 * @param int $max Stop after this many holders.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly string $id,
		public readonly ?string $exceptCharacter = null,
		public readonly int $max = 2,
	) {
	}//end __construct()

	/**
	 * The character field that holds this kind.
	 *
	 * @return string The field, `items` or `conditions`.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function field(): string {
		return UniqueHolderService::CHARACTER_FIELD[$this->kind];
	}//end field()
}//end class
