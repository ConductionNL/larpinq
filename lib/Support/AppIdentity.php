<?php

/**
 * AppIdentity for Larpinq
 *
 * The app id that config keys, user preferences, register imports and the
 * admin section are stored under. It lives apart from the Application
 * bootstrap class, so code that only needs the id does not depend on the
 * class that registers it.
 *
 * @category  Support
 * @package   OCA\Larpinq\Support
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-1
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Support;

/**
 * The app id.
 *
 * @category Support
 * @package  OCA\Larpinq\Support
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-1
 */
final class AppIdentity {
	/**
	 * Application ID
	 */
	public const APP_ID = 'larpinq';

	/**
	 * The group whose members are the game masters.
	 */
	public const GM_GROUP = 'gamemasters';

	/**
	 * Story writers: plots, plot parts, lore pages and the story fields of characters (REQ-ASR-001).
	 */
	public const ROLE_WRITERS = 'larpinq-writers';

	/**
	 * Rules marshals: abilities, skills, items, conditions and effects (REQ-ASR-001).
	 */
	public const ROLE_RULES = 'larpinq-rules';

	/**
	 * Treasurers: registrations with their payment state (REQ-ASR-001).
	 */
	public const ROLE_TREASURERS = 'larpinq-treasurers';

	/**
	 * Stewards: the roster and check-in at the gate (REQ-ASR-001, REQ-ASR-003).
	 */
	public const ROLE_STEWARDS = 'larpinq-stewards';

	/**
	 * The four staff role groups, next to the single game master group.
	 */
	public const STAFF_ROLES = [self::ROLE_WRITERS, self::ROLE_RULES, self::ROLE_TREASURERS, self::ROLE_STEWARDS];
}//end class
