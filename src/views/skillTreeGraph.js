/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Pure graph-derivation helpers for the skill-tree visualization
 * (skill-tree-visualization). Kept framework-free so the derivation — node /
 * edge building, cycle-tolerant tiering, and the owned/available/locked
 * mapping — is unit-testable in a node environment without mounting the SFC.
 *
 * The availability mapping consumes the server requirementReport ONLY; it never
 * re-implements prerequisite or XP-budget evaluation (ADR-022).
 *
 * @spec openspec/specs/skill-tree-visualization/spec.md
 */

/**
 * Normalise a reference value (array of ids or objects) to a string id list.
 *
 * @param {unknown} value The raw reference value. Anything may arrive here —
 * OpenRegister returns a reference field as an array of id strings OR of full
 * objects, and as a bare `null` when unset — so the guard below is load-bearing
 * and only arrays produce output.
 * @return {Array<string>} The id list.
 */
export function idList(value) {
	if (!Array.isArray(value)) {
		return []
	}
	return value
		.map((v) =>
			v && typeof v === 'object' ? (v.id ?? v['@self']?.id ?? '') : v,
		)
		.map(String)
		.filter((v) => v !== '')
}

/**
 * Index a collection of objects by id → name.
 *
 * @param {Array<object>} collection The objects.
 * @return {Record<string, string>} The id → name map.
 */
export function indexNames(collection) {
	const map = {}
	for (const item of Array.isArray(collection) ? collection : []) {
		if (item && item.id !== undefined) {
			map[String(item.id)] = item.name || String(item.id)
		}
	}
	return map
}

/**
 * Map each skill to an availability state from the SERVER report only.
 *
 * `owned` — the skill id is in the owned set. `available` — not owned, the
 * report classifies all of its requirement entries as passed/overridden and the
 * XP budget is ok. `locked` — not owned and the report has an unmet/unresolvable
 * entry (or the budget is not ok). `unknown` — no character selected, or the
 * report does not classify the skill (uncoloured). No client re-derivation.
 *
 * @param {object}      options         The inputs.
 * @param {Array<object>} options.skills The skill objects.
 * @param {Set<string>|null} options.ownedIds The owned skill id set, or null when no character is selected.
 * @param {object|null} options.report  The requirementReport response, or null.
 * @return {Record<string, string>} skillId → state.
 */
export function computeStateBySkill({ skills, ownedIds, report }) {
	const state = {}
	if (!ownedIds) {
		return state
	}
	const budgetOk = report?.budget?.ok !== false
	const bySkill = {}
	for (const entry of report?.requirements ?? []) {
		;(bySkill[entry.skill] = bySkill[entry.skill] || []).push(entry.status)
	}
	for (const skill of Array.isArray(skills) ? skills : []) {
		const id = String(skill.id)
		if (ownedIds.has(id)) {
			state[id] = 'owned'
			continue
		}
		const statuses = bySkill[id]
		if (!statuses) {
			state[id] = 'unknown'
			continue
		}
		const blocked =
			statuses.some((s) => s === 'unmet' || s === 'unresolvable') || !budgetOk
		state[id] = blocked ? 'locked' : 'available'
	}
	return state
}

/**
 * Build the setting-scoped node set (nodes = skills, edges = requiredSkills).
 * When `activeSetting` is falsy every skill is included.
 *
 * @param {object} options              The inputs.
 * @param {Array<object>} options.skills The skill objects.
 * @param {string|null} options.activeSetting The active setting id, or null for all.
 * @param {object} options.names        `{ ability, condition, effect }` id → name maps.
 * @param {Record<string,string>} options.stateMap skillId → availability state.
 * @return {Array<object>} The nodes.
 */
export function buildNodes({ skills, activeSetting, names, stateMap }) {
	const list = Array.isArray(skills) ? skills : []
	const skillName = {}
	for (const s of list) {
		skillName[String(s.id)] = s.name || String(s.id)
	}
	const ability = names?.ability ?? {}
	const condition = names?.condition ?? {}
	const effect = names?.effect ?? {}
	return list
		.filter((s) => !activeSetting || String(s.setting) === activeSetting)
		.map((s) => ({
			id: String(s.id),
			name: s.name || String(s.id),
			setting: s.setting,
			requiredSkills: idList(s.requiredSkills).map((id) => ({
				id,
				name: skillName[id] || id,
			})),
			requiredStats: idList(s.requiredStats).map((id) => ({
				id,
				name: ability[id] || id,
			})),
			requiredScore: Number(s.requiredScore || 0),
			requiredConditions: idList(s.requiredConditions).map((id) => ({
				id,
				name: condition[id] || id,
			})),
			requiredEffects: idList(s.requiredEffects).map((id) => ({
				id,
				name: effect[id] || id,
			})),
			state: (stateMap && stateMap[String(s.id)]) || 'unknown',
		}))
}

/**
 * Arrange nodes into dependency tiers (roots first). Cycle-tolerant: the passes
 * are bounded by the node count so a self-reference / cycle can never spin
 * unbounded; any nodes still unplaced (part of a cycle) drop into a final tier.
 *
 * @param {Array<object>} nodes The nodes (each with `requiredSkills[]`).
 * @return {Array<Array<object>>} The tiers.
 */
export function computeTiers(nodes) {
	const list = Array.isArray(nodes) ? nodes : []
	const inScope = new Set(list.map((n) => n.id))
	const placed = new Set()
	const tiers = []
	for (let pass = 0; pass < list.length && placed.size < list.length; pass++) {
		const tier = list.filter((n) => {
			if (placed.has(n.id)) return false
			return n.requiredSkills.every(
				(r) => !inScope.has(r.id) || placed.has(r.id),
			)
		})
		if (tier.length === 0) break
		tier.forEach((n) => placed.add(n.id))
		tiers.push(tier)
	}
	const leftover = list.filter((n) => !placed.has(n.id))
	if (leftover.length > 0) {
		tiers.push(leftover)
	}
	return tiers
}
