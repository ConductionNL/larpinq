/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Copy a world's rules into a new world: the preview the dialog shows, the
 * copy request, and the small helpers the dialog renders from. The copy runs
 * server-side in larpinq's WorldCopyService (`/api/worlds/{id}/copy`).
 *
 * @spec openspec/specs/setting-management/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { bodyOf } from './responseBody.js'

/** The preview status of a refused preview, by HTTP status; any other is `failed`. */
const PREVIEW_FAILURES = new Map([
	[403, 'forbidden'],
	[422, 'too-large'],
])

/**
 * The copy endpoint of one world.
 *
 * @param {string} worldId The world UUID.
 * @return {string} The URL.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
function copyUrl(worldId) {
	return generateUrl(
		`/apps/larpinq/api/worlds/${encodeURIComponent(worldId)}/copy`,
	)
}

/**
 * What a copy of the world would create (REQ-WCR-001, REQ-WCR-003).
 *
 * @param {string} worldId The world UUID.
 * @return {Promise<{status: string, worldName: string, counts: object, error: string}>}
 *   status is ready, forbidden, too-large or failed.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export async function fetchCopyPreview(worldId) {
	const response = await fetch(copyUrl(worldId), {
		headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
	})
	const body = await bodyOf(response)
	if (!response.ok) {
		return {
			status: PREVIEW_FAILURES.get(response.status) ?? 'failed',
			worldName: '',
			counts: {},
			error: typeof body.error === 'string' ? body.error : '',
		}
	}
	return previewOf(body)
}

/**
 * The loaded copy preview.
 *
 * @param {object} body The response body.
 * @return {{status: string, worldName: string, counts: object, error: string}} The preview.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
function previewOf(body) {
	return {
		status: 'ready',
		worldName: String(body.world?.name ?? ''),
		counts: body.counts && typeof body.counts === 'object' ? body.counts : {},
		error: '',
	}
}

/**
 * Copy the world under a new name (REQ-WCR-001).
 *
 * @param {string} worldId The world UUID.
 * @param {string} name The new world's name.
 * @return {Promise<{ok: boolean, worldId: string, error: string, leftovers: Array<string>}>} The result.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export async function copyWorld(worldId, name) {
	const response = await fetch(copyUrl(worldId), {
		method: 'POST',
		headers: {
			requesttoken: getRequestToken(),
			Accept: 'application/json',
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({ name: String(name).trim() }),
	})
	const body = await bodyOf(response)
	if (!response.ok) {
		return {
			ok: false,
			worldId: '',
			error: typeof body.error === 'string' ? body.error : '',
			leftovers: Array.isArray(body.leftovers) ? body.leftovers : [],
		}
	}

	return {
		ok: true,
		worldId: String(body.world?.id ?? ''),
		error: '',
		leftovers: [],
	}
}

/**
 * Whether the Copy button may be pressed.
 *
 * @param {{status: string, name: string, busy: boolean}} state The dialog state.
 * @return {boolean} True when the preview loaded, a name is filled in and no copy runs.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export function canCopy(state) {
	return (
		state.status === 'ready'
		&& String(state.name ?? '').trim() !== ''
		&& state.busy === false
	)
}

/**
 * The number of objects a copy creates.
 *
 * @param {object|null} counts The counts per type.
 * @return {number} The total.
 *
 * @spec openspec/specs/setting-management/spec.md
 */
export function totalOf(counts) {
	if (!counts || typeof counts !== 'object') {
		return 0
	}
	return Object.values(counts).reduce(
		(sum, value) => sum + (Number.isFinite(value) ? value : 0),
		0,
	)
}
