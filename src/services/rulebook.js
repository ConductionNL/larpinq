/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Rulebook of one world (rules-rulebook-and-spell-lists D3): the rules
 * chapters in order, then a reference of abilities, skill lists with their
 * skills (cost, pool, prerequisites by name, effect), items and conditions.
 *
 * Every read goes to OpenRegister's objects API, so the read rules of each
 * schema apply in the query: a player gets a rules chapter only when the lore
 * read rule lets them. Hidden-from-the-rulebook objects are left out here, by
 * their flag; nothing else is filtered on the client.
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

/** The schema slugs the rulebook reads, by role. */
export const RULEBOOK_SCHEMAS = Object.freeze({
	chapters: 'larping_lore_page',
	abilities: 'ability',
	lists: 'larping_skill_list',
	skills: 'larping_skill',
	effects: 'effect',
	items: 'larping_item',
	conditions: 'condition',
})

/** The most objects one read asks for. */
const LIMIT = 500

/**
 * The id of a row, whichever shape OpenRegister sent.
 *
 * @param {object} row The row.
 * @return {string}
 * @spec exclude Data-shape helper: reads an id from either OpenRegister row shape, no domain behaviour of its own.
 */
export function rowId(row) {
	return String(row?.id || row?.['@self']?.id || '')
}

/**
 * The rows out of an OpenRegister list answer.
 *
 * @param {object|Array} body The answer.
 * @return {Array<object>}
 */
function rowsOf(body) {
	if (Array.isArray(body)) return body
	if (body && Array.isArray(body.results)) return body.results
	return []
}

/**
 * Whether an object is kept out of the rulebook.
 *
 * @param {object} object The object.
 * @return {boolean}
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-secret-rules-stay-out-of-the-rulebook-req-rrs-004
 */
export function isHidden(object) {
	return object?.hiddenFromRulebook === true
}

/**
 * Sort by `order`, then by name or title.
 *
 * @param {object} a One object.
 * @param {object} b The other.
 * @return {number}
 */
function byOrderThenName(a, b) {
	const order = orderOf(a) - orderOf(b)
	if (order !== 0) return order
	return sortName(a).localeCompare(sortName(b))
}

/**
 * The `order` of a row as a number, 0 when it has none.
 *
 * @param {object} row The row.
 * @return {number}
 */
function orderOf(row) {
	return Number(row.order) || 0
}

/**
 * The name, else the title, of a row: what it sorts by after its order.
 *
 * @param {object} row The row.
 * @return {string}
 */
function sortName(row) {
	return String(row.name ?? row.title ?? '')
}

/**
 * The cost of a skill: each effect it grants that lowers an ability, as "3 mana".
 *
 * @param {object} skill The skill.
 * @param {Map<string, object>} effects The effects by id.
 * @param {Map<string, object>} abilities The abilities by id.
 * @return {Array<{amount: number, ability: string}>}
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
 */
export function skillCosts(skill, effects, abilities) {
	const costs = []
	for (const effectId of asList(skill?.effects)) {
		const effect = effects.get(String(effectId))
		const amount = effectCost(effect)
		if (amount === 0) continue
		costs.push(...abilityCosts(effect, amount, abilities))
	}
	return costs
}

/**
 * One cost per known ability an effect lowers.
 *
 * @param {object} effect The effect.
 * @param {number} amount How much it lowers each ability.
 * @param {Map<string, object>} abilities The abilities by id.
 * @return {Array<{amount: number, ability: string}>}
 */
function abilityCosts(effect, amount, abilities) {
	return asList(effect.abilities)
		.map((abilityId) => abilities.get(String(abilityId)))
		.filter((ability) => ability)
		.map((ability) => ({ amount, ability: ability.name || '' }))
}

/**
 * The value when it is an array, else an empty list.
 *
 * @param {Array|undefined} value The value.
 * @return {Array}
 */
function asList(value) {
	return Array.isArray(value) ? value : []
}

/**
 * How much an effect lowers its abilities: 0 for a missing or non-lowering effect.
 *
 * @param {object|undefined} effect The effect.
 * @return {number}
 */
function effectCost(effect) {
	if (!effect || effect.modification !== 'negative') return 0
	return Math.abs(Number(effect.modifier) || 0)
}

/**
 * The name, kind and description of a skill list, with their defaults.
 *
 * @param {object} list The list.
 * @return {{name: string, kind: string, description: string}}
 */
function listText(list) {
	return { name: list.name || '', kind: list.kind || 'skills', description: list.description || '' }
}

/**
 * The name of the ability a list's skills are paid from, else the default pool.
 *
 * @param {object} list The list.
 * @param {Map<string, object>} abilities The abilities by id.
 * @param {string} xpLabel The name of the default pool.
 * @return {string}
 */
function poolName(list, abilities, xpLabel) {
	return abilities.get(String(list.costAbility || ''))?.name || xpLabel
}

/**
 * The rulebook, built from what the reads returned.
 *
 * @param {object} data The rows by role, as RULEBOOK_SCHEMAS names them.
 * @param {string} xpLabel The name of the default pool ("XP").
 * @param {string} generalLabel The heading of the skills in no list.
 * @return {{chapters: Array<object>, abilities: Array<object>, lists: Array<object>, items: Array<object>, conditions: Array<object>}}
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
 */
export function buildRulebook(data, xpLabel = 'XP', generalLabel = 'General skills') {
	const index = (rows) => new Map((rows || []).map((row) => [rowId(row), row]))
	const abilities = index(data.abilities)
	const effects = index(data.effects)
	const allSkills = index(data.skills)

	const chapters = (data.chapters || [])
		.filter((page) => page.category === 'rules')
		.sort(byOrderThenName)
		.map((page) => ({ id: rowId(page), title: page.title || '', body: page.body || '' }))

	const skillEntry = (skill) => ({
		id: rowId(skill),
		name: skill.name || '',
		effect: skill.effect || '',
		costs: skillCosts(skill, effects, abilities),
		requires: (Array.isArray(skill.requiredSkills) ? skill.requiredSkills : [])
			.map((id) => allSkills.get(String(id))?.name)
			.filter((name) => typeof name === 'string' && name !== ''),
	})

	const visibleSkills = (data.skills || []).filter((skill) => !isHidden(skill))
	const listIds = new Set()
	const lists = (data.lists || [])
		.filter((list) => !isHidden(list))
		.sort(byOrderThenName)
		.map((list) => {
			const id = rowId(list)
			listIds.add(id)
			return {
				id,
				...listText(list),
				pool: poolName(list, abilities, xpLabel),
				skills: visibleSkills.filter((skill) => String(skill.list || '') === id).sort(byOrderThenName).map(skillEntry),
			}
		})

	const general = visibleSkills.filter((skill) => !skill.list || !listIds.has(String(skill.list)))
	if (general.length > 0) {
		lists.push({ id: '', name: generalLabel, kind: 'skills', description: '', pool: xpLabel, skills: general.sort(byOrderThenName).map(skillEntry) })
	}

	const named = (rows) => (rows || [])
		.filter((row) => !isHidden(row))
		.sort(byOrderThenName)
		.map((row) => ({ id: rowId(row), name: row.name || '', description: row.description || '', effect: row.effect || '' }))

	return {
		chapters,
		abilities: named(data.abilities),
		lists: lists.filter((list) => list.skills.length > 0 || list.id !== ''),
		items: named(data.items),
		conditions: named(data.conditions),
	}
}

/**
 * Read every schema of the rulebook for one world ('' reads every world).
 *
 * @param {string} worldId The world uuid, or ''.
 * @param {function(string, object): Promise<Response>} [fetchImpl] The fetch to use.
 * @return {Promise<object>} The rows by role.
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
 */
export async function fetchRulebookData(worldId, fetchImpl = (url, options) => fetch(url, options)) {
	const headers = { requesttoken: getRequestToken(), Accept: 'application/json' }
	const read = async (schema, extra = {}) => {
		const params = new URLSearchParams({ _limit: String(LIMIT), ...extra })
		if (worldId) params.set('setting', worldId)
		const response = await fetchImpl(generateUrl(`/apps/openregister/api/objects/larpinq/${schema}`) + '?' + params.toString(), { headers })
		if (!response.ok) {
			throw new Error(`The ${schema} objects could not be read (${response.status})`)
		}
		return rowsOf(await response.json())
	}

	const entries = await Promise.all(Object.entries(RULEBOOK_SCHEMAS).map(async ([role, schema]) => [
		role,
		await read(schema, role === 'chapters' ? { category: 'rules' } : {}),
	]))
	return Object.fromEntries(entries)
}
