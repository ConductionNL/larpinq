/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Characters "Edit selected" bulk action and the reads its dialog makes:
 * the selected characters' names and the worlds a character can move to.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchCharacterNames, fetchWorlds } from '../../src/services/characterBulkEdit.js'
import { bulkEditCharactersAction } from '../../src/services/characterBulkEditAction.js'

const dialog = vi.hoisted(() => ({ calls: [], result: undefined }))

vi.mock('@nextcloud/vue/functions/dialog', () => ({
	spawnDialog: (component, props) => {
		dialog.calls.push([component, props])
		return Promise.resolve(dialog.result)
	},
}))
vi.mock('../../src/dialogs/CharacterBulkEditDialog.vue', () => ({
	default: { name: 'CharacterBulkEditDialog' },
}))

const realFetch = globalThis.fetch
let reload

beforeEach(() => {
	dialog.calls.length = 0
	dialog.result = undefined
	reload = vi.fn()
	globalThis.window = { location: { reload } }
})

afterEach(() => {
	delete globalThis.window
	globalThis.fetch = realFetch
})

describe('bulkEditCharactersAction', () => {
	it.each([
		['no scope', undefined],
		['no selection', {}],
		['a non-array selection', { selectedIds: 'c1' }],
		['an empty selection', { selectedIds: [] }],
	])('opens nothing for %s', async (_label, scope) => {
		await bulkEditCharactersAction(scope)

		expect(dialog.calls).toEqual([])
		expect(reload).not.toHaveBeenCalled()
	})

	it('opens the bulk edit dialog with the selection', async () => {
		await bulkEditCharactersAction({ selectedIds: ['c1', 'c2'] })

		expect(dialog.calls).toEqual([[{ name: 'CharacterBulkEditDialog' }, { selectedIds: ['c1', 'c2'] }]])
	})

	it('reloads the list when characters changed', async () => {
		dialog.result = 2

		await bulkEditCharactersAction({ selectedIds: ['c1'] })

		expect(reload).toHaveBeenCalledTimes(1)
	})

	it.each([
		['cancelled', undefined],
		['nothing changed', 0],
		['a non-number', 'none'],
	])('keeps the list when %s', async (_label, result) => {
		dialog.result = result

		await bulkEditCharactersAction({ selectedIds: ['c1'] })

		expect(reload).not.toHaveBeenCalled()
	})
})

describe('fetchCharacterNames', () => {
	it('names each readable character, falling back to its id, and skips unreadable ones', async () => {
		const fetch = vi.fn()
			.mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ name: 'Aria' }) })
			.mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({}) })
			.mockResolvedValueOnce({ ok: false })
		globalThis.fetch = fetch

		expect(await fetchCharacterNames(['c1', 'c/2', 'c3'])).toEqual({ c1: 'Aria', 'c/2': 'c/2' })
		expect(fetch.mock.calls.map((call) => call[0])).toEqual([
			'/index.php/apps/openregister/api/objects/larpinq/character/c1',
			'/index.php/apps/openregister/api/objects/larpinq/character/c%2F2',
			'/index.php/apps/openregister/api/objects/larpinq/character/c3',
		])
		expect(fetch.mock.calls[0][1].headers.requesttoken).toBe('test-token')
	})

	it('asks nothing without ids', async () => {
		const fetch = vi.fn()
		globalThis.fetch = fetch

		expect(await fetchCharacterNames(undefined)).toEqual({})
		expect(await fetchCharacterNames([])).toEqual({})
		expect(fetch).not.toHaveBeenCalled()
	})
})

describe('fetchWorlds', () => {
	it('labels each world by name, else by id', async () => {
		const fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve({ results: [{ id: 1, name: 'Aldmoor' }, { id: 'w2' }] }),
		})
		globalThis.fetch = fetch

		expect(await fetchWorlds()).toEqual([
			{ id: '1', label: 'Aldmoor' },
			{ id: 'w2', label: 'w2' },
		])
		expect(fetch.mock.calls[0][0]).toBe('/index.php/apps/openregister/api/objects/larpinq/setting?_limit=500')
	})

	it('offers no worlds when they cannot be read or the answer has no results', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: false })
		expect(await fetchWorlds()).toEqual([])

		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({}) })
		expect(await fetchWorlds()).toEqual([])
	})
})
