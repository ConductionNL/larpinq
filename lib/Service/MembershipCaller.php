<?php

/**
 * MembershipCaller for Larpinq
 *
 * How the caller of a membership write stands to it: whether they lead the
 * group and whether they own the member character, both judged from the
 * stored faction and character.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-connections/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * The caller's standing in one membership write.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-connections/spec.md
 */
final class MembershipCaller {

	/**
	 * Constructor.
	 *
	 * @param bool $isLeader Whether the caller leads the group.
	 * @param bool $isOwner  Whether the caller owns the member character.
	 */
	public function __construct(
		public readonly bool $isLeader,
		public readonly bool $isOwner,
	) {
	}//end __construct()
}//end class
