<?php

/**
 * Contract of SettingsService
 *
 * The public surface of SettingsService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

/**
 * Contract of SettingsService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface SettingsServiceInterface {

	/**
	 * Get all Larpinq settings.
	 *
	 * @return array The settings as key-value pairs.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-23
	 */
	public function getSettings(): array;

	/**
	 * Load settings by importing the register JSON via ConfigurationService.
	 * Delegates to SettingsLoadService.
	 *
	 * Version-gated: the import is skipped when the recorded version already
	 * matches. Use reloadSettings() to re-import unconditionally.
	 *
	 * @return array The import result.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
	 */
	public function loadSettings(): array;

	/**
	 * Re-import the register JSON unconditionally, bypassing the version gate.
	 * Delegates to SettingsLoadService.
	 *
	 * @return array The import result.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
	 */
	public function reloadSettings(): array;

	/**
	 * Update Larpinq settings with the given data.
	 *
	 * @param array $data The settings data to update.
	 *
	 * @return array The updated settings.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-24
	 */
	public function updateSettings(array $data): array;
}//end interface
