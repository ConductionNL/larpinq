/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Character PDF download: the template list and the download URL.
 *
 * The template list comes from larpinq's own endpoint, which asks the document
 * app (filinq, formerly docudesk) server-side, so this file never names that
 * app's id. The download itself is the existing
 * `GET /apps/larpinq/characters/{id}/download/{template}` route.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch the character-sheet templates and whether PDF export is available.
 *
 * A 403 means the endpoint exists but this user may not download sheets
 * (administrators only until player-character-sheet-access lands).
 *
 * @return {Promise<{available: boolean, forbidden: boolean, templates: Array<{id: string, name: string}>}>} The state.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */
export async function fetchCharacterPdfTemplates() {
	const response = await fetch(generateUrl('/apps/larpinq/api/pdf/templates'), {
		headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
	})
	if (response.status === 403) {
		return { available: true, forbidden: true, templates: [] }
	}
	if (!response.ok) {
		return { available: false, forbidden: false, templates: [] }
	}

	const body = await response.json()
	const templates = Array.isArray(body?.templates)
		? body.templates.filter(
				(row) => typeof row?.id === 'string' && row.id !== '',
			)
		: []

	return { available: body?.available === true, forbidden: false, templates }
}

/**
 * The download URL for one character and one template.
 *
 * @param {string} characterId The character UUID.
 * @param {string} templateId The template id.
 * @return {string} The URL.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */
export function characterPdfUrl(characterId, templateId) {
	return generateUrl(
		`/apps/larpinq/characters/${encodeURIComponent(characterId)}/download/${encodeURIComponent(templateId)}`,
	)
}

/**
 * Whether the download button may be pressed (PDF-044).
 *
 * @param {{loading: boolean, templateId: string|null}} state The modal state.
 * @return {boolean} True when a template is chosen and the list has loaded.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */
export function canDownload(state) {
	return (
		state.loading === false
		&& typeof state.templateId === 'string'
		&& state.templateId !== ''
	)
}
