/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The whole rulebook of one world, pinned in full (rules-rulebook-and-spell-lists
 * REQ-RRS-003 and REQ-RRS-004): Corvath has two rules chapters, three
 * abilities, a spell list paid from Mana, a prayer list paid from Faith, a
 * martial list paid from XP, a list hidden from the rulebook and skills in no
 * list. What the page reads end to end, from the seven reads to the book.
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text) => String(text),
}))

const { buildRulebook, fetchRulebookData } = await import('../../src/services/rulebook.js')

function corvath() {
	return {
		chapters: [
			{ id: 'ch-death', title: 'Death and dying', body: 'Ten minutes to bleed out.', category: 'rules', order: 2 },
			{ id: 'ch-calls', title: 'Calls', body: 'Shout the call.', category: 'rules', order: 1 },
			{ id: 'ch-gm', title: 'Plot notes', body: 'Secret.', category: 'gm', order: 0 },
		],
		abilities: [
			{ id: 'ab-xp', name: 'XP', order: 1, description: 'Experience.' },
			{ id: 'ab-mana', name: 'Mana', order: 2, description: 'Arcane power.' },
			{ id: 'ab-faith', name: 'Faith', order: 3, description: 'Divine favour.' },
			{ id: 'ab-dread', name: 'Dread', order: 4, hiddenFromRulebook: true },
		],
		lists: [
			{ id: 'li-arcane', name: 'Arcane spells', kind: 'spells', costAbility: 'ab-mana', order: 2, description: 'Learned at the academy.' },
			{ id: 'li-divine', name: 'Prayers', kind: 'powers', costAbility: 'ab-faith', order: 3 },
			{ id: 'li-martial', name: 'Martial skills', kind: 'skills', costAbility: 'ab-xp', order: 1 },
			{ id: 'li-dark', name: 'Dark rites', kind: 'powers', costAbility: 'ab-dread', order: 0, hiddenFromRulebook: true },
		],
		skills: [
			{ id: 'sk-sword', name: 'Sword', list: 'li-martial', effects: ['ef-xp-5'], effect: 'Use a sword.' },
			{ id: 'sk-shield', name: 'Shield', list: 'li-martial', effects: ['ef-xp-5'], requiredSkills: ['sk-sword'] },
			{ id: 'sk-spark', name: 'Spark', list: 'li-arcane', effects: ['ef-mana-1'], order: 1, effect: 'Light a candle.' },
			{ id: 'sk-bolt', name: 'Firebolt', list: 'li-arcane', effects: ['ef-mana-3'], order: 2, requiredSkills: ['sk-spark'], effect: 'Call Fire 2.' },
			{ id: 'sk-heal', name: 'Heal', list: 'li-divine', effects: ['ef-faith-2'], effect: 'Restore 1.' },
			{ id: 'sk-curse', name: 'Curse', list: 'li-dark', effects: ['ef-dread'] },
			{ id: 'sk-whisper', name: 'Whisper', list: 'li-arcane', hiddenFromRulebook: true },
			{ id: 'sk-swim', name: 'Swimming' },
			{ id: 'sk-climb', name: 'Climbing', effects: ['ef-xp-5'] },
		],
		effects: [
			{ id: 'ef-xp-5', modifier: 5, modification: 'negative', abilities: ['ab-xp'] },
			{ id: 'ef-mana-1', modifier: 1, modification: 'negative', abilities: ['ab-mana'] },
			{ id: 'ef-mana-3', modifier: 3, modification: 'negative', abilities: ['ab-mana'] },
			{ id: 'ef-faith-2', modifier: 2, modification: 'negative', abilities: ['ab-faith'] },
			{ id: 'ef-dread', modifier: 1, modification: 'negative', abilities: ['ab-dread'] },
		],
		items: [
			{ id: 'it-potion', name: 'Healing potion', effect: 'Restore 2.', order: 1 },
			{ id: 'it-map', name: 'Map', description: 'Of the old city.', order: 2 },
		],
		conditions: [
			{ id: 'co-poison', name: 'Poisoned', effect: 'Lose 1 a minute.' },
			{ id: 'co-doom', name: 'Doomed', hiddenFromRulebook: true },
		],
	}
}

const EXPECTED_BOOK = {
	chapters: [
		{ id: 'ch-calls', title: 'Calls', body: 'Shout the call.' },
		{ id: 'ch-death', title: 'Death and dying', body: 'Ten minutes to bleed out.' },
	],
	abilities: [
		{ id: 'ab-xp', name: 'XP', description: 'Experience.', effect: '' },
		{ id: 'ab-mana', name: 'Mana', description: 'Arcane power.', effect: '' },
		{ id: 'ab-faith', name: 'Faith', description: 'Divine favour.', effect: '' },
	],
	lists: [
		{
			id: 'li-martial',
			name: 'Martial skills',
			kind: 'skills',
			description: '',
			pool: 'XP',
			skills: [
				{ id: 'sk-shield', name: 'Shield', effect: '', costs: [{ amount: 5, ability: 'XP' }], requires: ['Sword'] },
				{ id: 'sk-sword', name: 'Sword', effect: 'Use a sword.', costs: [{ amount: 5, ability: 'XP' }], requires: [] },
			],
		},
		{
			id: 'li-arcane',
			name: 'Arcane spells',
			kind: 'spells',
			description: 'Learned at the academy.',
			pool: 'Mana',
			skills: [
				{ id: 'sk-spark', name: 'Spark', effect: 'Light a candle.', costs: [{ amount: 1, ability: 'Mana' }], requires: [] },
				{ id: 'sk-bolt', name: 'Firebolt', effect: 'Call Fire 2.', costs: [{ amount: 3, ability: 'Mana' }], requires: ['Spark'] },
			],
		},
		{
			id: 'li-divine',
			name: 'Prayers',
			kind: 'powers',
			description: '',
			pool: 'Faith',
			skills: [
				{ id: 'sk-heal', name: 'Heal', effect: 'Restore 1.', costs: [{ amount: 2, ability: 'Faith' }], requires: [] },
			],
		},
		{
			id: '',
			name: 'Other skills',
			kind: 'skills',
			description: '',
			pool: 'Experience',
			skills: [
				{ id: 'sk-climb', name: 'Climbing', effect: '', costs: [{ amount: 5, ability: 'XP' }], requires: [] },
				{ id: 'sk-curse', name: 'Curse', effect: '', costs: [{ amount: 1, ability: 'Dread' }], requires: [] },
				{ id: 'sk-swim', name: 'Swimming', effect: '', costs: [], requires: [] },
			],
		},
	],
	items: [
		{ id: 'it-potion', name: 'Healing potion', description: '', effect: 'Restore 2.' },
		{ id: 'it-map', name: 'Map', description: 'Of the old city.', effect: '' },
	],
	conditions: [
		{ id: 'co-poison', name: 'Poisoned', description: '', effect: 'Lose 1 a minute.' },
	],
}

describe('the whole rulebook of Corvath', () => {
	it('builds every chapter, ability, list, skill, item and condition', () => {
		expect(buildRulebook(corvath(), { xp: 'Experience', general: 'Other skills' })).toEqual(EXPECTED_BOOK)
	})

	it('builds the same book from the seven reads, with the rows in @self shape', async () => {
		const data = corvath()
		const schemaRole = {
			larping_lore_page: 'chapters',
			ability: 'abilities',
			larping_skill_list: 'lists',
			larping_skill: 'skills',
			effect: 'effects',
			larping_item: 'items',
			condition: 'conditions',
		}
		const asSelf = ({ id, ...rest }) => ({ ...rest, '@self': { id } })
		const fetchImpl = async (url) => {
			const schema = url.split('/larpinq/')[1].split('?')[0]
			return { ok: true, json: async () => ({ results: data[schemaRole[schema]].map(asSelf) }) }
		}

		const read = await fetchRulebookData('w-corvath', fetchImpl)

		expect(buildRulebook(read, { xp: 'Experience', general: 'Other skills' })).toEqual(EXPECTED_BOOK)
	})

	it('names the default pool and the general heading in English without labels', () => {
		const book = buildRulebook(corvath())
		const general = book.lists[book.lists.length - 1]

		expect([general.name, general.pool]).toEqual(['General skills', 'XP'])
	})

	it('leaves nothing hidden from the rulebook anywhere in the book', () => {
		const text = JSON.stringify(buildRulebook(corvath()))

		for (const hidden of ['Dark rites', 'Whisper', 'Doomed', 'Plot notes', 'ab-dread"', 'li-dark']) {
			expect(text).not.toContain(hidden)
		}
	})

	it('shows a list again once its hidden flag is cleared, paid from its own ability', () => {
		const data = corvath()
		data.lists = data.lists.map((list) => (list.id === 'li-dark' ? { ...list, hiddenFromRulebook: false } : list))

		const book = buildRulebook(data)

		expect(book.lists[0]).toEqual({
			id: 'li-dark',
			name: 'Dark rites',
			kind: 'powers',
			description: '',
			pool: 'Dread',
			skills: [{ id: 'sk-curse', name: 'Curse', effect: '', costs: [{ amount: 1, ability: 'Dread' }], requires: [] }],
		})
		expect(book.lists.at(-1).skills.map((skill) => skill.id)).toEqual(['sk-climb', 'sk-swim'])
	})
})
