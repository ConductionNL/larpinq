<?php

/**
 * EffectRun for Larpinq
 *
 * The state of one pass of effects over a character's abilities: the
 * abilities as they change, and the non-cumulative effects already applied.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * One pass of effects over abilities.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */
final class EffectRun {
	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $abilities The abilities, by id.
	 * @param array<string, bool> $applied The effect ids already applied in this pass.
	 */
	public function __construct(
		public array $abilities,
		public array $applied,
	) {
	}//end __construct()
}//end class
