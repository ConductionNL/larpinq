/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/CharacterCustomFields.vue: the edits a loaded
 * sheet starts from, the labels of private fields, and what a save sends.
 * Only fetch is faked; the real characterCustomFields service runs.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import CharacterCustomFields from '../../src/views/CharacterCustomFields.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => vars[name] ?? match),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

const OBJECTS = '/index.php/apps/openregister/api/objects/larpinq'
const DEFINITIONS = [
	{ id: 'f1', key: 'bloodline', label: 'Bloodline', fieldType: 'text', visibility: 'owner', order: 1 },
	{ id: 'f2', key: 'sworn', label: 'Sworn', fieldType: 'yes-no', visibility: 'owner', order: 2 },
	{ id: 'f3', key: 'secret', label: 'Secret', fieldType: 'number', visibility: 'gamemasters', order: 3 },
]

/**
 * Fake fetch: GETs answer from the store, a PATCH answers with the given result.
 *
 * @param {object} character The stored character.
 * @param {{ok: boolean, body: object}} patch The answer to a PATCH.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(character, patch = { ok: true, body: {} }) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		const method = options.method ?? 'GET'
		calls.push({ url, method, body: options.body ? JSON.parse(options.body) : undefined })
		if (method === 'PATCH') {
			return { ok: patch.ok, json: async () => patch.body }
		}
		const body = url.startsWith(`${OBJECTS}/character/`) ? character : { results: DEFINITIONS }
		return { ok: true, json: async () => body }
	}
	return calls
}

/**
 * The tab's state, bound to its methods.
 *
 * @return {object} The instance.
 */
function instance() {
	const self = { ...CharacterCustomFields.data(), characterId: 'char-1' }
	for (const [name, method] of Object.entries(CharacterCustomFields.methods)) {
		self[name] = method.bind(self)
	}
	return self
}

afterEach(() => {
	delete globalThis.fetch
})

describe('CharacterCustomFields load', () => {
	it('starts the edits from the stored values, a yes-no as a boolean', async () => {
		const calls = fakeFetch({
			customFields: { bloodline: 'elven', sworn: 'yes' },
			customFieldsPrivate: { secret: 7 },
		})
		const self = instance()

		await self.load()

		expect(calls.map((call) => call.url)).toEqual([
			`${OBJECTS}/character/char-1`,
			`${OBJECTS}/larping_character_field?_limit=500`,
		])
		expect(self.sheet.rows.map((row) => row.key)).toEqual(['bloodline', 'sworn', 'secret'])
		expect(self.edits).toEqual({ bloodline: 'elven', sworn: false, secret: 7 })
		expect(self.loading).toBe(false)
	})

	it('has no sheet and no edits when the character cannot be read', async () => {
		globalThis.fetch = async () => ({ ok: false, json: async () => ({}) })
		const self = instance()

		await self.load()

		expect(self.sheet).toBeNull()
		expect(self.edits).toEqual({})
		expect(self.loading).toBe(false)
	})
})

describe('CharacterCustomFields labelOf', () => {
	it('marks a private field as for game masters only', () => {
		expect(CharacterCustomFields.methods.labelOf({ label: 'Secret', private: true })).toBe('Secret (game masters only)')
		expect(CharacterCustomFields.methods.labelOf({ label: 'Bloodline', private: false })).toBe('Bloodline')
	})
})

describe('CharacterCustomFields save', () => {
	it('sends each edit to its own bag, typed, and reloads', async () => {
		const calls = fakeFetch({ customFields: {}, customFieldsPrivate: {} })
		const self = instance()
		await self.load()
		calls.length = 0
		self.edits = { bloodline: ' dwarven ', sworn: true, secret: '12' }

		await self.save()

		expect(calls[0].method).toBe('PATCH')
		expect(calls[0].url).toBe(`${OBJECTS}/character/char-1`)
		expect(calls[0].body).toEqual({
			customFields: { bloodline: 'dwarven', sworn: true },
			customFieldsPrivate: { secret: 12 },
		})
		expect(calls.slice(1).map((call) => call.method)).toEqual(['GET', 'GET'])
		expect(self).toMatchObject({ saving: false, saved: true, errors: {} })
	})

	it('keeps the refused fields and does not reload', async () => {
		const calls = fakeFetch(
			{ customFields: {}, customFieldsPrivate: {} },
			{ ok: false, body: { fields: { secret: 'Must be a number.' } } },
		)
		const self = instance()
		await self.load()
		calls.length = 0

		await self.save()

		expect(calls.map((call) => call.method)).toEqual(['PATCH'])
		expect(self).toMatchObject({ saving: false, saved: false, errors: { secret: 'Must be a number.' } })
	})
})
