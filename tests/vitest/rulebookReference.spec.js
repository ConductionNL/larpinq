/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The rulebook reference in detail (rules-rulebook-and-spell-lists D3): row
 * ids in both OpenRegister shapes, the hidden flag, ordering by `order` then
 * name or title, costs of several effects and abilities, lists whose ability
 * is unknown, skills of an unknown or hidden list, the answer shapes the
 * reads accept, and the page's world, labels, rendering and print.
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

const { buildRulebook, fetchRulebookData, isHidden, rowId, skillCosts } = await import('../../src/services/rulebook.js')
const { activeWorld } = await import('../../src/services/activeWorld.js')
const { default: Rulebook } = await import('../../src/views/Rulebook.vue')

const byId = (rows) => new Map(rows.map((row) => [rowId(row), row]))

afterEach(() => {
	vi.restoreAllMocks()
})

describe('rowId', () => {
	it('reads the id of a flat row and of a row with @self', () => {
		expect(rowId({ id: 'a-1' })).toBe('a-1')
		expect(rowId({ '@self': { id: 'b-2' } })).toBe('b-2')
		expect(rowId({ id: 'flat', '@self': { id: 'nested' } })).toBe('flat')
	})

	it('reads a number as a string and nothing as an empty id', () => {
		expect(rowId({ id: 42 })).toBe('42')
		expect(rowId({})).toBe('')
		expect(rowId(null)).toBe('')
		expect(rowId(undefined)).toBe('')
	})
})

describe('isHidden', () => {
	it('hides only an object whose flag is exactly true', () => {
		expect(isHidden({ hiddenFromRulebook: true })).toBe(true)
		expect(isHidden({ hiddenFromRulebook: false })).toBe(false)
		expect(isHidden({ hiddenFromRulebook: 'true' })).toBe(false)
		expect(isHidden({})).toBe(false)
		expect(isHidden(null)).toBe(false)
	})
})

describe('skillCosts', () => {
	const abilities = byId([{ id: 'mana', name: 'Mana' }, { id: 'faith', name: 'Faith' }, { id: 'nameless' }])

	it('charges each ability a lowering effect names, in effect order', () => {
		const effects = byId([
			{ id: 'twin', modifier: 2, modification: 'negative', abilities: ['mana', 'faith'] },
			{ id: 'one', modifier: '4', modification: 'negative', abilities: ['faith'] },
		])

		expect(skillCosts({ effects: ['twin', 'one'] }, { effects, abilities })).toEqual([
			{ amount: 2, ability: 'Mana' },
			{ amount: 2, ability: 'Faith' },
			{ amount: 4, ability: 'Faith' },
		])
	})

	it('reads a negative modifier as its size and skips unknown abilities', () => {
		const effects = byId([{ id: 'drain', modifier: -5, modification: 'negative', abilities: ['mana', 'gone'] }])

		expect(skillCosts({ effects: ['drain'] }, { effects, abilities })).toEqual([{ amount: 5, ability: 'Mana' }])
	})

	it('names an ability without a name as an empty string', () => {
		const effects = byId([{ id: 'odd', modifier: 1, modification: 'negative', abilities: ['nameless'] }])

		expect(skillCosts({ effects: ['odd'] }, { effects, abilities })).toEqual([{ amount: 1, ability: '' }])
	})

	it('charges nothing for a zero, unreadable or abilityless effect', () => {
		const effects = byId([
			{ id: 'zero', modifier: 0, modification: 'negative', abilities: ['mana'] },
			{ id: 'text', modifier: 'lots', modification: 'negative', abilities: ['mana'] },
			{ id: 'bare', modifier: 3, modification: 'negative' },
			{ id: 'raise', modifier: 3, modification: 'positive', abilities: ['mana'] },
		])

		expect(skillCosts({ effects: ['zero', 'text', 'bare', 'raise'] }, { effects, abilities })).toEqual([])
	})

	it('charges nothing when the effects are not a list or there is no skill', () => {
		const effects = byId([{ id: 'x', modifier: 1, modification: 'negative', abilities: ['mana'] }])

		expect(skillCosts({ effects: 'x' }, { effects, abilities })).toEqual([])
		expect(skillCosts(undefined, { effects, abilities })).toEqual([])
	})
})

describe('buildRulebook ordering and defaults', () => {
	it('sorts chapters of equal order by title and reads a missing order as 0', () => {
		const book = buildRulebook({
			chapters: [
				{ id: 'b', title: 'Bows', category: 'rules', order: 1 },
				{ id: 'a', title: 'Armour', category: 'rules', order: 1 },
				{ id: 'z', title: 'Zero', category: 'rules' },
				{ '@self': { id: 'n' }, category: 'rules', order: '3' },
			],
		})

		expect(book.chapters).toEqual([
			{ id: 'z', title: 'Zero', body: '' },
			{ id: 'a', title: 'Armour', body: '' },
			{ id: 'b', title: 'Bows', body: '' },
			{ id: 'n', title: '', body: '' },
		])
	})

	it('fills a list in with its defaults and pays an unknown ability from XP', () => {
		const book = buildRulebook({
			lists: [{ id: 'l-1', costAbility: 'gone' }],
			skills: [{ id: 's-1', name: 'Sneak', list: 'l-1' }],
		}, { xp: 'Experience' })

		expect(book.lists).toEqual([{
			id: 'l-1',
			name: '',
			kind: 'skills',
			description: '',
			pool: 'Experience',
			skills: [{ id: 's-1', name: 'Sneak', effect: '', costs: [], requires: [] }],
		}])
	})

	it('keeps an empty named list but drops an empty general list', () => {
		const book = buildRulebook({ lists: [{ id: 'l-empty', name: 'Rituals', kind: 'powers' }] })

		expect(book.lists.map((list) => [list.name, list.kind, list.skills.length])).toEqual([['Rituals', 'powers', 0]])
	})

	it('puts a skill of an unknown or hidden list under the general heading', () => {
		const book = buildRulebook({
			lists: [{ id: 'l-secret', name: 'Secret', hiddenFromRulebook: true }],
			skills: [
				{ id: 's-b', name: 'Bribe', list: 'l-secret' },
				{ id: 's-a', name: 'Appraise', list: 'l-gone' },
				{ id: 's-c', name: 'Climb', order: -1 },
			],
		}, { general: 'Algemeen' })

		expect(book.lists).toHaveLength(1)
		expect(book.lists[0].name).toBe('Algemeen')
		expect(book.lists[0].pool).toBe('XP')
		expect(book.lists[0].skills.map((skill) => skill.name)).toEqual(['Climb', 'Appraise', 'Bribe'])
	})

	it('names only known prerequisites, also those hidden from the rulebook', () => {
		const book = buildRulebook({
			skills: [
				{ id: 's-1', name: 'Base', hiddenFromRulebook: true },
				{ id: 's-2', name: '' },
				{ id: 's-3', name: 'Top', requiredSkills: ['s-1', 's-2', 's-9', 7] },
				{ id: 's-4', name: 'Loose', requiredSkills: 's-1' },
			],
		})

		const skills = book.lists[0].skills
		expect(skills.map((skill) => [skill.name, skill.requires])).toEqual([['', []], ['Loose', []], ['Top', ['Base']]])
	})

	it('describes items and conditions by name, description and effect, ordered', () => {
		const book = buildRulebook({
			abilities: [{ id: 'a-2', name: 'Strength', order: 2 }, { id: 'a-1', name: 'Agility', order: 1, description: 'Quick.' }],
			items: [{ id: 'i-2', name: 'Shield', effect: 'Block.' }, { id: 'i-1', name: 'Axe', description: 'Heavy.' }],
			conditions: [{ id: 'k-1', name: 'Poisoned', effect: 'Lose 1.', description: 'Green.' }],
		})

		expect(book.abilities).toEqual([
			{ id: 'a-1', name: 'Agility', description: 'Quick.', effect: '' },
			{ id: 'a-2', name: 'Strength', description: '', effect: '' },
		])
		expect(book.items).toEqual([
			{ id: 'i-1', name: 'Axe', description: 'Heavy.', effect: '' },
			{ id: 'i-2', name: 'Shield', description: '', effect: 'Block.' },
		])
		expect(book.conditions).toEqual([{ id: 'k-1', name: 'Poisoned', description: 'Green.', effect: 'Lose 1.' }])
	})

	it('does not reorder the rows it was given', () => {
		const data = { items: [{ id: 'i-2', name: 'B' }, { id: 'i-1', name: 'A' }] }
		const before = data.items.map((row) => row.id)

		buildRulebook(data)

		expect(data.items.map((row) => row.id)).toEqual(before)
	})
})

describe('fetchRulebookData answers', () => {
	const fakeFetch = (bodies, seen = []) => async (url, options) => {
		seen.push({ url, options })
		const schema = url.split('/larpinq/')[1].split('?')[0]
		return { ok: true, status: 200, json: async () => bodies[schema] }
	}

	it('reads a plain array, a results envelope and anything else as no rows', async () => {
		const data = await fetchRulebookData('w', fakeFetch({
			larping_lore_page: [{ id: 'c-1' }],
			ability: { results: [{ id: 'a-1' }] },
			larping_skill_list: { total: 0 },
			larping_skill: null,
			effect: 'oops',
			larping_item: { results: 'not a list' },
			condition: [],
		}))

		expect(data).toEqual({
			chapters: [{ id: 'c-1' }],
			abilities: [{ id: 'a-1' }],
			lists: [],
			skills: [],
			effects: [],
			items: [],
			conditions: [],
		})
	})

	it('asks for JSON with the request token on every read', async () => {
		const seen = []
		await fetchRulebookData('w', fakeFetch({}, seen))

		expect(seen).toHaveLength(7)
		for (const { options } of seen) {
			expect(options.headers.Accept).toBe('application/json')
			expect(Object.keys(options.headers)).toEqual(['requesttoken', 'Accept'])
		}
	})

	it('names the first schema whose read is refused', async () => {
		const refuse = async (url) => ({ ok: !url.includes('/ability?'), status: 500, json: async () => [] })

		await expect(fetchRulebookData('w', refuse)).rejects.toThrow('The ability objects could not be read (500)')
	})
})

describe('Rulebook page helpers', () => {
	const page = (overrides = {}) => ({ ...Rulebook.data(), ...Rulebook.methods, ...overrides })

	it('reads the world from the route, else the active world, else every world', () => {
		const before = activeWorld.id
		try {
			activeWorld.id = 'w-active'
			expect(Rulebook.computed.world.call({ $route: { params: { world: 'w-route' } } })).toBe('w-route')
			expect(Rulebook.computed.world.call({ $route: { params: {} } })).toBe('w-active')
			expect(Rulebook.computed.world.call({})).toBe('w-active')
			activeWorld.id = ''
			expect(Rulebook.computed.world.call({})).toBe('')
		} finally {
			activeWorld.id = before
		}
	})

	it('is not empty with only abilities or only lists, and empty with only items', () => {
		const book = (parts) => ({ book: { chapters: [], abilities: [], lists: [], items: [], conditions: [], ...parts } })

		expect(Rulebook.computed.empty.call(book({ abilities: [{}] }))).toBe(false)
		expect(Rulebook.computed.empty.call(book({ lists: [{}] }))).toBe(false)
		expect(Rulebook.computed.empty.call(book({ items: [{}], conditions: [{}] }))).toBe(true)
	})

	it('starts loading, without an error, with an empty book', () => {
		expect(Rulebook.data()).toEqual({
			loading: true,
			error: false,
			book: { chapters: [], abilities: [], lists: [], items: [], conditions: [] },
		})
		expect(Rulebook.props.load.type).toBe(Function)
	})

	it('clears an earlier error when a read succeeds and labels the default pool', async () => {
		const self = page({
			world: 'w-1',
			error: true,
			load: async () => ({ skills: [{ id: 's', name: 'Swim' }] }),
		})

		await Rulebook.methods.read.call(self)

		expect(self.error).toBe(false)
		expect(self.loading).toBe(false)
		expect(self.book.lists).toEqual([{
			id: '',
			name: 'General skills',
			kind: 'skills',
			description: '',
			pool: 'XP',
			skills: [{ id: 's', name: 'Swim', effect: '', costs: [], requires: [] }],
		}])
	})

	it('keeps the earlier book when a read fails', async () => {
		const earlier = { chapters: [{ id: 'c' }], abilities: [], lists: [], items: [], conditions: [] }
		const self = page({ world: '', book: earlier, load: async () => { throw new Error('offline') } })

		await Rulebook.methods.read.call(self)

		expect(self.book).toBe(earlier)
		expect(self.error).toBe(true)
		expect(self.loading).toBe(false)
	})

	it('reads again whenever the world changes, from the start', () => {
		const read = vi.fn()

		Rulebook.watch.world.handler.call({ read })

		expect(Rulebook.watch.world.immediate).toBe(true)
		expect(read).toHaveBeenCalledTimes(1)
	})

	it('writes every cost of a skill, lower case, after its name', () => {
		expect(Rulebook.methods.skillLine({
			name: 'Storm',
			costs: [{ amount: 2, ability: 'Mana' }, { amount: 1, ability: 'FAITH' }],
		})).toBe('Storm, 2 mana, 1 faith')
	})

	it('labels each list kind and passes an unknown kind through', () => {
		expect(Rulebook.methods.kindLabel('skills')).toBe('Skills')
		expect(Rulebook.methods.kindLabel('powers')).toBe('Powers')
		expect(Rulebook.methods.kindLabel('rituals')).toBe('rituals')
	})

	it('renders a missing chapter body as empty markdown', () => {
		expect(Rulebook.methods.render(undefined)).toBe('<p></p>')
		expect(Rulebook.methods.render('# Rules')).toBe('<p># Rules</p>')
	})

	it('prints through the browser', () => {
		const print = vi.fn()
		vi.stubGlobal('window', { print })
		try {
			Rulebook.methods.print()
		} finally {
			vi.unstubAllGlobals()
		}

		expect(print).toHaveBeenCalledTimes(1)
	})
})
