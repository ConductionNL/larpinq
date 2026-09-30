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
import { campaignExportUrl, importCampaign, resolveRegisterId } from './campaignPortability.js'

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
		input.accept = '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
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
	const id = await registerId()
	if (id === null) {
		showError(t('larpinq', 'The campaign could not be exported, because no register is configured.'))
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
	const id = await registerId()
	if (id === null) {
		showError(t('larpinq', 'The campaign could not be imported, because no register is configured.'))
		return
	}
	const file = await pickWorkbook()
	if (file === null) {
		return
	}
	try {
		const totals = await importCampaign(file, id)
		showSuccess(t('larpinq', 'Campaign imported: {created} created, {updated} updated, {unchanged} unchanged, {failed} failed', totals))
	} catch (error) {
		showError(t('larpinq', 'The campaign could not be imported: {reason}', { reason: error.message }))
	}
}
