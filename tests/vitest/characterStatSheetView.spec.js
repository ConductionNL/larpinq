/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/CharacterStatSheet.vue: loading the sheet,
 * opening an ability, and how a modifier's source is described. Only fetch
 * is faked; the real characterStats service runs.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import CharacterStatSheet from '../../src/views/CharacterStatSheet.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => vars[name] ?? match),
}))

/**
 * Mount the sheet over a fetch that answers once.
 *
 * @param {{ok: boolean, body: object}} answer The answer.
 * @return {Promise<{self: object, urls: Array<string>}>} The state and the urls fetched.
 */
async function mounted(answer) {
	const urls = []
	globalThis.fetch = async (url) => {
		urls.push(url)
		return { ok: answer.ok, json: async () => answer.body }
	}
	const self = { ...CharacterStatSheet.data(), characterId: 'char-1' }
	await CharacterStatSheet.mounted.call(self)
	return { self, urls }
}

afterEach(() => {
	delete globalThis.fetch
})

describe('CharacterStatSheet mounted', () => {
	it('shows the sheet the server computes', async () => {
		const { self, urls } = await mounted({
			ok: true,
			body: { abilities: [{ id: 'str', name: 'Strength', value: 3 }], xp: { total: 10 } },
		})

		expect(urls).toEqual(['/index.php/apps/larpinq/api/characters/char-1/stats'])
		expect(self.sheet).toEqual({ abilities: [{ id: 'str', name: 'Strength', value: 3 }], xp: { total: 10 } })
		expect(self.failed).toBe(false)
		expect(self.loading).toBe(false)
	})

	it('marks a failed read and keeps the empty sheet', async () => {
		const { self } = await mounted({ ok: false, body: {} })

		expect(self.failed).toBe(true)
		expect(self.sheet).toEqual({ abilities: [], xp: null })
		expect(self.loading).toBe(false)
	})
})

describe('CharacterStatSheet toggle', () => {
	it('opens an ability and closes it again, one at a time', () => {
		const self = { open: '' }
		const { toggle } = CharacterStatSheet.methods

		toggle.call(self, 'str')
		expect(self.open).toBe('str')
		toggle.call(self, 'dex')
		expect(self.open).toBe('dex')
		toggle.call(self, 'dex')
		expect(self.open).toBe('')
	})
})

describe('CharacterStatSheet modifier text', () => {
	const self = { sourceLabel: CharacterStatSheet.methods.sourceLabel }

	it.each([
		['skill', 'skill'],
		['item', 'item'],
		['condition', 'condition'],
		['event', 'event'],
		['xpAward', 'XP award'],
		['ritual', 'ritual'],
	])('labels %s as %s', (source, label) => {
		expect(CharacterStatSheet.methods.sourceLabel(source)).toBe(label)
	})

	it.each([
		[{ sourceName: 'Iron Will', source: 'skill' }, 'from Iron Will (skill)'],
		[{ effectName: 'Blessing', source: 'xpAward' }, 'from Blessing (XP award)'],
		[{ sourceName: 'Fog', effectName: 'Blind' }, 'from Fog'],
		[{ effectName: 'Blind' }, 'from Blind'],
	])('%j reads %s', (modifier, text) => {
		expect(CharacterStatSheet.methods.modifierText.call(self, modifier)).toBe(text)
	})
})
