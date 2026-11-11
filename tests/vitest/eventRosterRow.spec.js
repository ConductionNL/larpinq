/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the row update of src/views/EventRoster.vue's setStatus():
 * what a recorded check-in writes onto the roster row, and that a failed
 * write leaves the row alone. The requests themselves are pinned in
 * ocsRequests.golden.spec.js.
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import EventRoster from '../../src/views/EventRoster.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
	translatePlural: (app, one) => one,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({ default: {} }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnDataTable: {},
	CnIcon: {},
	CnStatusBadge: {},
	useObjectStore: () => ({}),
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => url }))

/**
 * Run setStatus() against a fetch that answers with the given body.
 *
 * @param {object} answer The response: ok and the JSON body.
 * @param {string} status The status to set.
 * @return {Promise<object>} The row and the saving flag left behind.
 */
async function setStatus(answer, status) {
	globalThis.OC = { requestToken: 'token-1' }
	globalThis.fetch = async () => ({ ok: answer.ok, status: 200, json: async () => answer.body })
	vi.spyOn(console, 'error').mockImplementation(() => {})
	const row = { character: 'c1', status: 'registered', checkedInAt: 'before', checkedInBy: 'before', name: 'Mira' }
	const self = { eventId: 'e1', saving: 'x' }
	await EventRoster.methods.setStatus.call(self, row, status)
	return { row, saving: self.saving }
}

afterEach(() => {
	vi.restoreAllMocks()
})

describe('EventRoster setStatus row update', () => {
	it.each([
		[
			'saved with stamps',
			{ ok: true, body: { status: 'checked-in', checkedInAt: '2026-12-01T10:00:00+00:00', checkedInBy: 'gm' } },
			'checked-in',
			{ character: 'c1', status: 'checked-in', checkedInAt: '2026-12-01T10:00:00+00:00', checkedInBy: 'gm', name: 'Mira' },
		],
		[
			'saved without fields',
			{ ok: true, body: {} },
			'no-show',
			{ character: 'c1', status: 'no-show', checkedInAt: '', checkedInBy: '', name: 'Mira' },
		],
		[
			'refused',
			{ ok: false, body: { status: 'checked-in' } },
			'checked-in',
			{ character: 'c1', status: 'registered', checkedInAt: 'before', checkedInBy: 'before', name: 'Mira' },
		],
	])('%s', async (name, answer, status, row) => {
		expect(await setStatus(answer, status)).toEqual({ row, saving: null })
	})
})
