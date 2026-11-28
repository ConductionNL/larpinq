<?php

/**
 * Larpinq PreferenceSlot.
 *
 * The user value a preference request reads or writes.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://github.com/ConductionNL/larpinq
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

/**
 * The signed-in user and the namespaced config key of one preference.
 *
 * @spec openspec/changes/retrofit-2026-05-26-preferences-api/tasks.md#task-1
 */
final class PreferenceSlot {
	/**
	 * Constructor.
	 *
	 * @param string $userId The signed-in user's id.
	 * @param string $configKey The user value key, `pref_` plus the sanitised key.
	 */
	public function __construct(
		public readonly string $userId,
		public readonly string $configKey,
	) {
	}//end __construct()
}//end class
