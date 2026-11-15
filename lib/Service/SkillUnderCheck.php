<?php

/**
 * SkillUnderCheck for Larpinq
 *
 * One assigned skill while its requirements are checked: its id, the skill
 * object and whether a game master override covers it.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * The skill whose requirements are being checked.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */
final class SkillUnderCheck {
	/**
	 * Constructor.
	 *
	 * @param string $skillId The skill being assigned.
	 * @param array<string,mixed> $skill The skill object.
	 * @param bool $overridden Whether a GM override covers this skill.
	 */
	public function __construct(
		public readonly string $skillId,
		public readonly array $skill,
		public readonly bool $overridden,
	) {
	}//end __construct()

	/**
	 * The status of one requirement of this skill.
	 *
	 * An overridden requirement is reported as "overridden" (never "passed"),
	 * an unresolvable target as "unresolvable", otherwise passed/unmet.
	 *
	 * @param bool $passed Whether the requirement is satisfied.
	 * @param bool $resolved Whether the required target resolves to an object.
	 *
	 * @return string The status.
	 *
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 */
	public function status(bool $passed, bool $resolved): string {
		if ($resolved === false) {
			return 'unresolvable';
		}

		if ($passed === true) {
			return 'passed';
		}

		if ($this->overridden === true) {
			return 'overridden';
		}

		return 'unmet';
	}//end status()
}//end class
