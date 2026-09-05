/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the skill-tree graph derivation (src/views/skillTreeGraph.js):
 * node/edge building, cycle-tolerant tiering, and the owned/available/locked
 * mapping from a requirementReport fixture (no client re-derivation) plus the
 * degraded (report-absent) path.
 *
 * @spec openspec/specs/skill-tree-visualization/spec.md
 */

import { describe, expect, it } from 'vitest'
import {
	buildNodes,
	computeStateBySkill,
	computeTiers,
	idList,
	indexNames,
} from '../../src/views/skillTreeGraph.js'

const HEAL1 = { id: 'h1', name: 'Healing Lvl 1', requiredSkills: [] }
const HEAL2 = { id: 'h2', name: 'Healing Lvl 2', requiredSkills: ['h1'] }
const MASTER = {
	id: 'h3',
	name: 'Master Healing',
	requiredSkills: ['h2'],
	requiredStats: ['wis'],
	requiredScore: 5,
}
const SWORD = { id: 's1', name: 'Swordsmanship', requiredSkills: [] }

describe('skillTreeGraph — idList / indexNames', () => {
	it('normalises id arrays and object arrays to string ids', () => {
		expect(idList(['a', 'b'])).toEqual(['a', 'b'])
		expect(idList([{ id: 'x' }, { '@self': { id: 'y' } }])).toEqual(['x', 'y'])
		expect(idList(null)).toEqual([])
	})

	it('indexes a collection by id → name', () => {
		expect(indexNames([{ id: 'wis', name: 'Wisdom' }])).toEqual({
			wis: 'Wisdom',
		})
		expect(indexNames(null)).toEqual({})
	})
})

describe('skillTreeGraph — node + edge derivation', () => {
	it('renders a node per skill with directed requiredSkills edges', () => {
		const nodes = buildNodes({
			skills: [HEAL1, HEAL2, SWORD],
			activeSetting: null,
			names: {},
			stateMap: {},
		})
		expect(nodes).toHaveLength(3)
		const h2 = nodes.find((n) => n.id === 'h2')
		// Directed edge Healing Lvl 1 → Healing Lvl 2, resolved to a name.
		expect(h2.requiredSkills).toEqual([{ id: 'h1', name: 'Healing Lvl 1' }])
		// Swordsmanship is an unconnected root.
		expect(nodes.find((n) => n.id === 's1').requiredSkills).toEqual([])
	})

	it('resolves ability names and keeps requiredScore for detail', () => {
		const nodes = buildNodes({
			skills: [MASTER],
			activeSetting: null,
			names: { ability: { wis: 'Wisdom' } },
			stateMap: {},
		})
		expect(nodes[0].requiredStats).toEqual([{ id: 'wis', name: 'Wisdom' }])
		expect(nodes[0].requiredScore).toBe(5)
	})

	it('scopes nodes to the active setting', () => {
		const skills = [
			{
				id: 'g1',
				name: 'Grim skill',
				setting: 'Grimdark',
				requiredSkills: [],
			},
			{
				id: 'f1',
				name: 'Fantasy skill',
				setting: 'Highfantasy',
				requiredSkills: [],
			},
		]
		const nodes = buildNodes({
			skills,
			activeSetting: 'Grimdark',
			names: {},
			stateMap: {},
		})
		expect(nodes.map((n) => n.id)).toEqual(['g1'])
	})
})

describe('skillTreeGraph — tiering', () => {
	it('places prerequisites before dependents', () => {
		const nodes = buildNodes({
			skills: [MASTER, HEAL1, HEAL2, SWORD],
			activeSetting: null,
			names: {},
			stateMap: {},
		})
		const tiers = computeTiers(nodes)
		const tierOf = (id) => tiers.findIndex((t) => t.some((n) => n.id === id))
		expect(tierOf('h1')).toBe(0)
		expect(tierOf('s1')).toBe(0)
		expect(tierOf('h2')).toBeGreaterThan(tierOf('h1'))
		expect(tierOf('h3')).toBeGreaterThan(tierOf('h2'))
	})

	it('tolerates a prerequisite cycle without hanging', () => {
		const a = { id: 'a', name: 'A', requiredSkills: ['b'] }
		const b = { id: 'b', name: 'B', requiredSkills: ['a'] }
		const nodes = buildNodes({
			skills: [a, b],
			activeSetting: null,
			names: {},
			stateMap: {},
		})
		const tiers = computeTiers(nodes)
		// Both mutually-dependent nodes still render (in a final leftover tier).
		const all = tiers
			.flat()
			.map((n) => n.id)
			.sort()
		expect(all).toEqual(['a', 'b'])
	})

	it('tolerates a self-reference', () => {
		const self = { id: 'x', name: 'X', requiredSkills: ['x'] }
		const nodes = buildNodes({
			skills: [self],
			activeSetting: null,
			names: {},
			stateMap: {},
		})
		const tiers = computeTiers(nodes)
		expect(tiers.flat().map((n) => n.id)).toEqual(['x'])
	})
})

describe('skillTreeGraph — availability mapping from the server report', () => {
	const skills = [HEAL1, HEAL2, MASTER]

	it('maps owned / available / locked from the report', () => {
		const state = computeStateBySkill({
			skills,
			ownedIds: new Set(['h1']),
			report: {
				budget: { ok: true },
				requirements: [
					// Healing Lvl 2 fully satisfied → available.
					{ skill: 'h2', status: 'passed' },
					// Master Healing has an unmet prerequisite → locked.
					{ skill: 'h3', status: 'unmet' },
				],
			},
		})
		expect(state.h1).toBe('owned')
		expect(state.h2).toBe('available')
		expect(state.h3).toBe('locked')
	})

	it('locks everything unowned when the XP budget is not ok', () => {
		const state = computeStateBySkill({
			skills,
			ownedIds: new Set(['h1']),
			report: {
				budget: { ok: false },
				requirements: [{ skill: 'h2', status: 'passed' }],
			},
		})
		expect(state.h2).toBe('locked')
	})

	it('leaves skills the report does not classify uncoloured (unknown)', () => {
		const state = computeStateBySkill({
			skills,
			ownedIds: new Set(['h1']),
			report: { budget: { ok: true }, requirements: [] },
		})
		expect(state.h1).toBe('owned')
		expect(state.h2).toBe('unknown')
		expect(state.h3).toBe('unknown')
	})

	it('degrades to no colouring when no character is selected', () => {
		expect(
			computeStateBySkill({ skills, ownedIds: null, report: null }),
		).toEqual({})
	})

	it('degrades to no colouring when the report is unavailable', () => {
		// A character is selected (ownedIds present) but the report failed to load.
		const state = computeStateBySkill({
			skills,
			ownedIds: new Set(['h1']),
			report: null,
		})
		expect(state.h1).toBe('owned')
		expect(state.h2).toBe('unknown')
		expect(state.h3).toBe('unknown')
	})
})
