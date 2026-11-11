<?php

/**
 * RequirementScan for Larpinq
 *
 * The skill whose requirements are being checked together with the resolved
 * character context it is checked in.
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
 * One requirement check in progress: the skill and the character context.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
final class RequirementScan {
	/**
	 * Constructor.
	 *
	 * @param SkillUnderCheck     $subject The skill being assigned.
	 * @param array<string,mixed> $context The resolved character context.
	 */
	public function __construct(
		public readonly SkillUnderCheck $subject,
		public readonly array $context,
	) {
	}//end __construct()
}//end class
