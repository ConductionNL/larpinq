/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Rulebook (rules-rulebook-and-spell-lists 2.2): the rules chapters of a
 * world in order, then the reference with Firebolt under Arcane spells at
 * 3 mana, with hidden objects left out; the reads go to OpenRegister per
 * schema, narrowed to the world; and the page reads the world in its route.
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ cnRenderMarkdown: (md) => `<p>${md}</p>` }))
vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text, vars) => String(text).replace(/\{(\w+)\}/g, (m, k) => (vars && k in vars ? String(vars[k]) : m)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))

const { buildRulebook, fetchRulebookData, skillCosts, RULEBOOK_SCHEMAS } = await import('../../src/services/rulebook.js')
const { default: Rulebook } = await import('../../src/views/Rulebook.vue')

function aldmoor () {
  return {
	chapters: [
		{ id: 'c2', title: 'Casting a spell', body: 'Pay the mana.', category: 'rules', order: 2 },
		{ id: 'c1', title: 'How combat works', body: 'One hit, one point.', category: 'rules', order: 1 },
		{ id: 'c3', title: 'The city', body: 'Not a rule.', category: 'place', order: 0 },
	],
	abilities: [{ id: 'a-mana', name: 'Mana', description: 'Arcane power.' }, { id: 'a-xp', name: 'XP' }],
	lists: [
		{ id: 'l-arcane', name: 'Arcane spells', kind: 'spells', costAbility: 'a-mana', order: 2, description: 'Cast from mana.' },
		{ id: 'l-martial', name: 'Martial skills', kind: 'skills', order: 1 },
		{ id: 'l-secret', name: 'Forbidden lists', kind: 'powers', order: 3, hiddenFromRulebook: true },
	],
	skills: [
		{ id: 's-lore', name: 'Arcane lore', list: 'l-martial', effect: 'You may learn spells.' },
		{ id: 's-fire', name: 'Firebolt', list: 'l-arcane', effects: ['e-fire'], requiredSkills: ['s-lore'], effect: 'Fire 2.' },
		{ id: 's-ward', name: 'Ward', list: 'l-arcane', effects: ['e-ward', 'e-boost'], requiredSkills: ['s-lore', 's-gone'] },
		{ id: 's-blood', name: 'Blood rite', list: 'l-arcane', hiddenFromRulebook: true },
		{ id: 's-run', name: 'Running' },
	],
	effects: [
		{ id: 'e-fire', modifier: 3, modification: 'negative', abilities: ['a-mana'] },
		{ id: 'e-ward', modifier: -2, modification: 'negative', abilities: ['a-mana'] },
		{ id: 'e-boost', modifier: 1, modification: 'positive', abilities: ['a-xp'] },
	],
	items: [{ id: 'i-1', name: 'Torch', effect: 'Light.' }, { id: 'i-2', name: 'Cursed ring', hiddenFromRulebook: true }],
	conditions: [{ id: 'k-1', name: 'Bleeding', description: 'Lose a point a minute.' }],
}
}

afterEach(() => {
	delete globalThis.fetch
})

describe('buildRulebook', () => {
	it('puts the rules chapters first, in order, and nothing else as a chapter', () => {
		expect(buildRulebook(aldmoor()).chapters.map((c) => c.title)).toEqual(['How combat works', 'Casting a spell'])
	})

	it('lists Firebolt under Arcane spells with its cost of 3 mana, paid from Mana', () => {
		const book = buildRulebook(aldmoor())
		const arcane = book.lists.find((list) => list.name === 'Arcane spells')
		expect(arcane.pool).toBe('Mana')
		expect(arcane.skills.map((skill) => [skill.name, skill.costs, skill.requires])).toEqual([
			['Firebolt', [{ amount: 3, ability: 'Mana' }], ['Arcane lore']],
			['Ward', [{ amount: 2, ability: 'Mana' }], ['Arcane lore']],
		])
	})

	it('orders the lists, pays a list without an ability from XP and puts listless skills last', () => {
		const book = buildRulebook(aldmoor(), 'XP', 'General skills')
		expect(book.lists.map((list) => [list.name, list.pool])).toEqual([
			['Martial skills', 'XP'],
			['Arcane spells', 'Mana'],
			['General skills', 'XP'],
		])
		expect(book.lists[2].skills.map((skill) => skill.name)).toEqual(['Running'])
	})

	it('leaves out every object hidden from the rulebook', () => {
		const book = buildRulebook(aldmoor())
		const names = JSON.stringify(book)
		expect(names).not.toContain('Blood rite')
		expect(names).not.toContain('Forbidden lists')
		expect(names).not.toContain('Cursed ring')
		expect(book.items.map((item) => item.name)).toEqual(['Torch'])
		expect(book.conditions.map((condition) => condition.name)).toEqual(['Bleeding'])
	})

	it('reads an empty answer as an empty book', () => {
		expect(buildRulebook({})).toEqual({ chapters: [], abilities: [], lists: [], items: [], conditions: [] })
	})
})

describe('skillCosts', () => {
	it('counts only effects that lower an ability', () => {
		const data = aldmoor()
		const byId = (rows) => new Map(rows.map((row) => [row.id, row]))
		expect(skillCosts({ effects: ['e-boost', 'e-missing', 'e-fire'] }, byId(data.effects), byId(data.abilities))).toEqual([{ amount: 3, ability: 'Mana' }])
		expect(skillCosts({}, new Map(), new Map())).toEqual([])
	})
})

describe('fetchRulebookData', () => {
	it('reads every schema of the world, the chapters narrowed to the rules category', async () => {
		const urls = []
		const data = await fetchRulebookData('w-1', async (url) => {
			urls.push(url)
			return { ok: true, json: async () => ({ results: [{ id: url }] }) }
		})

		const base = '/index.php/apps/openregister/api/objects/larpinq/'
		expect(urls).toEqual([
			`${base}larping_lore_page?_limit=500&category=rules&setting=w-1`,
			`${base}ability?_limit=500&setting=w-1`,
			`${base}larping_skill_list?_limit=500&setting=w-1`,
			`${base}larping_skill?_limit=500&setting=w-1`,
			`${base}effect?_limit=500&setting=w-1`,
			`${base}larping_item?_limit=500&setting=w-1`,
			`${base}condition?_limit=500&setting=w-1`,
		])
		expect(Object.keys(data)).toEqual(Object.keys(RULEBOOK_SCHEMAS))
	})

	it('reads every world without a world, and fails on a refused read', async () => {
		const urls = []
		await expect(fetchRulebookData('', async (url) => {
			urls.push(url)
			return { ok: !url.includes('larping_item'), status: 403, json: async () => [] }
		})).rejects.toThrow('The larping_item objects could not be read (403)')
		expect(urls[0]).toBe('/index.php/apps/openregister/api/objects/larpinq/larping_lore_page?_limit=500&category=rules')
	})
})

describe('Rulebook page', () => {
	const page = (overrides = {}) => ({ ...Rulebook.data(), ...Rulebook.methods, ...overrides })

	it('reads the world in its route and builds the book', async () => {
		const asked = []
		const self = page({ $route: { params: { world: 'w-aldmoor' } }, load: async (world) => { asked.push(world); return aldmoor() } })
		self.world = Rulebook.computed.world.call(self)

		await Rulebook.methods.read.call(self)

		expect(asked).toEqual(['w-aldmoor'])
		expect(self.loading).toBe(false)
		expect(self.error).toBe(false)
		expect(self.book.chapters.map((c) => c.title)).toEqual(['How combat works', 'Casting a spell'])
		expect(Rulebook.computed.empty.call(self)).toBe(false)
	})

	it('says so when the reads fail', async () => {
		const self = page({ load: async () => { throw new Error('down') } })
		await Rulebook.methods.read.call(self)
		expect([self.loading, self.error]).toEqual([false, true])
		expect(Rulebook.computed.empty.call(self)).toBe(true)
	})

	it('writes a skill with its costs as "Firebolt, 3 mana"', () => {
		expect(Rulebook.methods.skillLine({ name: 'Firebolt', costs: [{ amount: 3, ability: 'Mana' }] })).toBe('Firebolt, 3 mana')
		expect(Rulebook.methods.skillLine({ name: 'Running', costs: [] })).toBe('Running')
		expect(Rulebook.methods.kindLabel('spells')).toBe('Spells')
		expect(Rulebook.methods.render('**x**')).toBe('<p>**x**</p>')
	})
})
