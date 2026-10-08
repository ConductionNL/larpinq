/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Data portability (admin-import-export): who is offered an import, and the
 * campaign export and import over OpenRegister's register endpoints.
 *
 * Every import, the list import on Characters and Players and the campaign
 * import alike, goes to OpenRegister's `POST /api/registers/{id}/import`,
 * which requires permission to manage the register. The larpinq register
 * grants `manage` to game masters (register.d/import-for-game-masters.json),
 * and administrators always have it, so the manifest's import actions are
 * removed for everyone else rather than offered and refused.
 *
 * @spec openspec/specs/data-portability/spec.md
 */

import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

/** The Worlds page header action that imports a campaign workbook. */
export const CAMPAIGN_IMPORT_ACTION = 'import-campaign'

/**
 * Whether the page says this user is a game master (DashboardController
 * provides `isGameMaster`). False when the page provides nothing.
 *
 * @return {boolean} True for a game master or administrator.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export function isGameMaster() {
	try {
		return loadState('larpinq', 'isGameMaster', false) === true
	} catch {
		return false
	}
}

/**
 * Whether this user may import: OpenRegister's register import lets through
 * administrators and the groups in the register's manage rule, which names
 * the game masters.
 *
 * @param {object|null} [user] The current user, as @nextcloud/auth returns it.
 * @param {boolean} [gameMaster] Whether the user is a game master.
 * @return {boolean} True for an administrator or a game master.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export function canImport(user = getCurrentUser(), gameMaster = isGameMaster()) {
	return user?.isAdmin === true || gameMaster === true
}

/**
 * Remove every import from the manifest for a user who may not import: the
 * list import on every index page and the campaign import on the Worlds page.
 * The exports stay. Returns a new manifest; the input is not changed.
 *
 * @param {object} manifest The merged app manifest.
 * @param {boolean} allowed Whether the user may import.
 * @return {object} The manifest to render.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export function applyImportGate(manifest, allowed) {
	if (allowed) {
		return manifest
	}
	const pages = (manifest.pages || []).map((page) => {
		if (page?.type !== 'index' || !page.config) {
			return page
		}
		const config = { ...page.config, showMassImport: false }
		if (Array.isArray(config.headerActions)) {
			config.headerActions = config.headerActions.filter(
				(action) => action?.id !== CAMPAIGN_IMPORT_ACTION,
			)
		}
		return { ...page, config }
	})
	return { ...manifest, pages }
}

/**
 * The numeric id of the larpinq register from the larpinq settings. The
 * register endpoints take the id, not the slug.
 *
 * @param {object|null} config The larpinq settings configuration.
 * @return {string|null} The register id, or null when none is configured.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export function resolveRegisterId(config) {
	const id =
		config?.setting_register || config?.character_register || config?.register
	return id === undefined || id === null || id === '' ? null : String(id)
}

/**
 * The download URL of the whole register as one Excel workbook, one sheet
 * per schema, holding the objects this user may read.
 *
 * @param {string} registerId The register id.
 * @return {string} The URL.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export function campaignExportUrl(registerId) {
	return (
		generateUrl(
			`/apps/openregister/api/registers/${encodeURIComponent(registerId)}/export`,
		) + '?format=excel'
	)
}

/**
 * Count OpenRegister's per-sheet import summary. A count arrives as a list of
 * rows or as a number, depending on the import path.
 *
 * @param {object|undefined} summary `{ <sheet>: { created, updated, unchanged, errors } }`.
 * @return {{created: number, updated: number, unchanged: number, failed: number}} The totals.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export function summariseImport(summary) {
	const count = (value) =>
		Array.isArray(value) ? value.length : Number(value) || 0
	const totals = { created: 0, updated: 0, unchanged: 0, failed: 0 }
	for (const sheet of Object.values(summary || {})) {
		totals.created += count(sheet?.created)
		totals.updated += count(sheet?.updated)
		totals.unchanged += count(sheet?.unchanged)
		totals.failed += count(sheet?.errors)
	}
	return totals
}

/**
 * Import a campaign workbook into the register. Sheets are matched to schemas
 * by OpenRegister and objects are upserted by id.
 *
 * @param {Blob} file The Excel workbook.
 * @param {string} registerId The register id.
 * @return {Promise<{created: number, updated: number, unchanged: number, failed: number}>} The totals.
 * @throws {Error} With OpenRegister's reason when the import is refused or fails.
 *
 * @spec openspec/specs/data-portability/spec.md
 */
export async function importCampaign(file, registerId) {
	const body = new FormData()
	body.append('file', file)
	const response = await fetch(
		generateUrl(
			`/apps/openregister/api/registers/${encodeURIComponent(registerId)}/import`,
		) + '?type=excel',
		{
			method: 'POST',
			headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
			body,
		},
	)
	const payload = await response.json().catch(() => ({}))
	if (!response.ok) {
		throw new Error(payload?.error || `Import failed (${response.status})`)
	}
	return summariseImport(payload?.summary)
}
