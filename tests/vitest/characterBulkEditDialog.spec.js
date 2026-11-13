/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/dialogs/CharacterBulkEditDialog.vue: the worlds it
 * offers, which choices count, applying them to every selected character,
 * and what closing reports. Only fetch is faked; the real characterBulkEdit
 * service runs.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import CharacterBulkEditDialog from '../../src/dialogs/CharacterBulkEditDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))

const OBJECTS = '/index.php/apps/openregister/api/objects/larpinq'

afterEach(() => {
	delete globalThis.fetch
})

describe('CharacterBulkEditDialog', () => {
	it('offers the worlds by name', async () => {
		const urls = []
		globalThis.fetch = async (url) => {
			urls.push(url)
			return { ok: true, json: async () => ({ results: [{ id: 'w-1', name: 'Aldmoor' }, { id: 7 }] }) }
		}
		const self = CharacterBulkEditDialog.data()

		await CharacterBulkEditDialog.mounted.call(self)

		expect(urls).toEqual([`${OBJECTS}/setting?_limit=500`])
		expect(self.worldOptions).toEqual([{ id: 'w-1', label: 'Aldmoor' }, { id: '7', label: '7' }])
	})

	it('reads the ids as strings and offers the fixed statuses and types', () => {
		const { computed } = CharacterBulkEditDialog
		expect(computed.ids.call({ selectedIds: [1, 'c-2'] })).toEqual(['1', 'c-2'])
		expect(computed.ids.call({ selectedIds: null })).toEqual([])
		expect(computed.statusOptions.call({}).map((option) => option.id)).toEqual(['active', 'retired', 'dead'])
		expect(computed.typeOptions.call({}).map((option) => option.id)).toEqual(['player', 'npc', 'other'])
	})

	it('counts a choice only once something is picked', () => {
		const { choices, chosen } = CharacterBulkEditDialog.computed
		const none = choices.call({ status: null, type: null, world: null })
		const some = choices.call({ status: { id: 'dead' }, type: null, world: { id: 'w-1' } })

		expect(none).toEqual({ status: undefined, type: undefined, setting: undefined })
		expect(chosen.call({ choices: none })).toBe(false)
		expect(some).toEqual({ status: 'dead', type: undefined, setting: 'w-1' })
		expect(chosen.call({ choices: some })).toBe(true)
	})

	it('patches every character, reports progress and the refused ones by name', async () => {
		const calls = []
		globalThis.fetch = async (url, options = {}) => {
			const method = options.method ?? 'GET'
			calls.push({ url, method, body: options.body })
			if (method === 'GET') {
				return { ok: true, json: async () => ({ name: url.endsWith('c-1') ? 'Aldric' : 'Brenna' }) }
			}
			if (url.endsWith('c-2')) {
				return { ok: false, status: 403, json: async () => ({ message: 'Not yours.' }) }
			}
			return { ok: true, json: async () => ({}) }
		}
		const progress = []
		const self = {
			...CharacterBulkEditDialog.data(),
			ids: ['c-1', 'c-2'],
			choices: { status: 'retired', type: undefined, setting: undefined },
		}
		Object.defineProperty(self, 'progress', {
			get: () => progress.at(-1) ?? 0,
			set: (value) => {
				progress.push(value)
			},
		})

		await CharacterBulkEditDialog.methods.apply.call(self)

		expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual([
			`GET ${OBJECTS}/character/c-1`,
			`GET ${OBJECTS}/character/c-2`,
			`PATCH ${OBJECTS}/character/c-1`,
			`PATCH ${OBJECTS}/character/c-2`,
		])
		expect(calls[2].body).toBe('{"status":"retired"}')
		expect(progress).toEqual([0, 1, 2])
		expect(self.report).toEqual({ changed: ['c-1'], refused: [{ id: 'c-2', name: 'Brenna', reason: 'Not yours.' }] })
		expect(self).toMatchObject({ busy: false, done: true })
	})

	it('reports the number changed when it closes', () => {
		const emitted = []
		const self = { report: { changed: ['c-1', 'c-3'], refused: [] }, $emit: (...args) => emitted.push(args) }

		CharacterBulkEditDialog.methods.close.call(self)

		expect(emitted).toEqual([['close', 2]])
	})
})
