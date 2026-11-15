/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Worlds page header actions "Export campaign" and "Import campaign",
 * registered as `kind: 'handler'` entries in src/registry.js and named from
 * the manifest. The logic lives in campaignPortability.js; this file only
 * reads the register id, picks the file and reports the result.
 *
 * @spec openspec/specs/data-portability/spec.md
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { useSettingsStore } from '../store/modules/settings.js'
import {
	campaignExportUrl,
	importCampaign,
	resolveRegisterId,
} from './campaignPortability.js'

/**
 * The larpinq register id, fetching the settings when they are not loaded.
 *
 * @return {Promise<string|null>} The id, or null when none is configured.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
async function registerId() {
	const store = useSettingsStore()
	const config = store.getConfig || (await store.fetchSettings())
	return resolveRegisterId(config)
}

/**
 * The larpinq register id, or null after showing the given message when
 * none is configured.
 *
 * @param {string} noRegisterMessage The translated message for a missing register.
 * @return {Promise<string|null>} The id, or null.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
async function configuredRegisterId(noRegisterMessage) {
	const id = await registerId()
	if (id === null) {
		showError(noRegisterMessage)
	}
	return id
}

/**
 * Let the user pick one Excel workbook.
 *
 * @return {Promise<File|null>} The file, or null when none was picked.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
function pickWorkbook() {
	return new Promise((resolve) => {
		const input = document.createElement('input')
		input.type = 'file'
		input.accept =
			'.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		input.addEventListener('change', () => resolve(input.files?.[0] || null))
		input.click()
	})
}

/**
 * Download the whole campaign as one workbook.
 *
 * @return {Promise<void>}
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export async function exportCampaignAction() {
	const id = await configuredRegisterId(
		t(
			'larpinq',
			'The campaign could not be exported, because no register is configured.',
		),
	)
	if (id === null) {
		return
	}
	window.location.assign(campaignExportUrl(id))
}

/**
 * Pick a campaign workbook and import it, reporting what happened.
 *
 * @return {Promise<void>}
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export async function importCampaignAction() {
	const id = await configuredRegisterId(
		t(
			'larpinq',
			'The campaign could not be imported, because no register is configured.',
		),
	)
	if (id === null) {
		return
	}
	const file = await pickWorkbook()
	if (file !== null) {
		await importAndReport(file, id)
	}
}

/**
 * Import one campaign workbook into the register and report the totals, or
 * the reason it failed.
 *
 * @param {File} file The picked workbook.
 * @param {string} id The register id.
 * @return {Promise<void>}
 *
 * @spec openspec/specs/data-portability/spec.md
 */
async function importAndReport(file, id) {
	let totals
	try {
		totals = await importCampaign(file, id)
	} catch (error) {
		reportImportFailed(error)
		return
	}
	reportImported(totals)
}

/**
 * Tell the user what the import created, updated, left and failed.
 *
 * @param {object} totals The import totals.
 * @return {void}
 *
 * @spec openspec/specs/data-portability/spec.md
 */
function reportImported(totals) {
	showSuccess(
		t(
			'larpinq',
			'Campaign imported: {created} created, {updated} updated, {unchanged} unchanged, {failed} failed',
			totals,
		),
	)
}

/**
 * Tell the user why the import failed.
 *
 * @param {Error} error What the import threw.
 * @return {void}
 *
 * @spec openspec/specs/data-portability/spec.md
 */
function reportImportFailed(error) {
	showError(
		t('larpinq', 'The campaign could not be imported: {reason}', {
			reason: error.message,
		}),
	)
}
