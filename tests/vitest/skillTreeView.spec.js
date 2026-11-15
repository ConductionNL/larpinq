/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The skill tree view's derived state and its requirement-report fetch:
 * the selector options, the owned set, the setting-scoped node set and the
 * selected node (run through the real skillTreeGraph helpers), and how a
 * character change loads, refuses or fails the report. Only fetch, the
 * object store and the Nextcloud components and globals are doubled.
 *
 * @spec openspec/specs/skill-tree-visualization/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SkillTree from '../../src/views/SkillTree.vue'

let store = {}

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
	translatePlural: (app, one) => one,
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({ default: {} }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnIcon: {},
	CnStatusBadge: {},
	useObjectStore: () => store,
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (url, params = {}) => url.replace('{id}', params.id),
}))
vi.mock('../../src/store/store.js', () => ({ ensureObjectTypes: async () => {} }))

const SKILLS = [
	{ id: 's1', name: 'Sword', setting: 'fantasy' },
	{ id: 's2', name: 'Shield', setting: 'fantasy', requiredSkills: ['s1'] },
	{ id: 's3', name: 'Hacking', setting: 'cyber' },
	{ id: 's4', name: 'Untitled' },
]

/**
 * A component instance: data, then the computeds as live getters, then the
 * methods bound to it.
 *
 * @param {object} state State to set over the data.
 * @return {object} The instance.
 */
function view(state = {}) {
	const vm = { ...SkillTree.data(), ...state }
	for (const [name, getter] of Object.entries(SkillTree.computed)) {
		Object.defineProperty(vm, name, { get: () => getter.call(vm), enumerable: true })
	}
	for (const [name, method] of Object.entries(SkillTree.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

beforeEach(() => {
	globalThis.OC = { requestToken: 'tok' }
	vi.spyOn(console, 'warn').mockImplementation(() => {})
	store = {}
})

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('SkillTree selectors', () => {
	it('lists the characters by name, falling back to the id', () => {
		const vm = view({ characters: [{ id: 7, name: 'Aria' }, { id: 8 }] })

		expect(vm.characterOptions).toEqual([
			{ value: '7', label: 'Aria' },
			{ value: '8', label: '8' },
		])
	})

	it('lists each setting once, in first-seen order, and skips skills without one', () => {
		expect(view({ skills: SKILLS }).settingOptions).toEqual([
			{ value: 'fantasy', label: 'fantasy' },
			{ value: 'cyber', label: 'cyber' },
		])
	})
})

describe('SkillTree nodes', () => {
	it('shows every skill without a setting filter, and only that setting with one', () => {
		const vm = view({ skills: SKILLS })
		expect(vm.nodes.map((n) => n.id)).toEqual(['s1', 's2', 's3', 's4'])

		vm.selectedSetting = { value: 'fantasy' }
		expect(vm.nodes.map((n) => n.id)).toEqual(['s1', 's2'])
		expect(vm.nodes[1].requiredSkills).toEqual([{ id: 's1', name: 'Sword' }])
	})

	it('derives the owned set from the selected character, empty without one', () => {
		const vm = view({ characters: [{ id: 7, skills: [{ id: 's1' }, 's3'] }] })
		expect([...vm.ownedSkillIds]).toEqual([])

		vm.selectedCharacter = { value: '7' }
		expect([...vm.ownedSkillIds]).toEqual(['s1', 's3'])
	})

	it('finds the selected node by id, and null for none or an unknown id', () => {
		const vm = view({ skills: SKILLS })
		expect(vm.selectedNode).toBeNull()

		vm.selectedNodeId = 's2'
		expect(vm.selectedNode.name).toBe('Shield')

		vm.selectedNodeId = 'gone'
		expect(vm.selectedNode).toBeNull()
	})

	it('arranges the nodes in tiers with the prerequisite first', () => {
		const vm = view({ skills: SKILLS, selectedSetting: { value: 'fantasy' } })

		expect(vm.tiers.map((tier) => tier.map((n) => n.id))).toEqual([['s1'], ['s2']])
	})
})

describe('SkillTree requirement report', () => {
	it('clears the report and fetches nothing without a character', async () => {
		globalThis.fetch = vi.fn()
		const vm = view({ report: { stale: true } })

		await vm.onCharacterChange()

		expect(vm.report).toBeNull()
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})

	it('loads the selected character\'s report with the OCS headers', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ entries: [] }) })
		const vm = view({ selectedCharacter: { value: '7' } })

		await vm.onCharacterChange()

		expect(vm.report).toEqual({ entries: [] })
		expect(globalThis.fetch).toHaveBeenCalledWith(
			'/apps/larpinq/api/characters/7/requirement-report',
			{ headers: { requesttoken: 'tok', 'OCS-APIREQUEST': 'true' } },
		)
	})

	it('degrades a refused or failed report to an uncoloured tree and warns', async () => {
		globalThis.fetch = vi.fn()
			.mockResolvedValueOnce({ ok: false, status: 403 })
			.mockRejectedValueOnce(new Error('offline'))
		const vm = view({ selectedCharacter: { value: '7' } })

		await vm.onCharacterChange()
		expect(vm.report).toBeNull()
		await vm.onCharacterChange()
		expect(vm.report).toBeNull()

		expect(console.warn).toHaveBeenCalledTimes(2)
		expect(console.warn.mock.calls[0][1].message).toBe('report failed: 403')
	})
})

describe('SkillTree lifecycle', () => {
	it('loads, then subscribes, on mount; releases on unmount', async () => {
		const order = []
		const vm = view()
		vm.load = async () => order.push('load')
		vm.syncLiveSubscriptions = () => order.push('subscribe')
		vm.releaseLiveSubscriptions = () => order.push('release')

		await SkillTree.mounted.call(vm)
		SkillTree.beforeUnmount.call(vm)

		expect(order).toEqual(['load', 'subscribe', 'release'])
	})

	it('releasing unsubscribes every handle, stops the watcher and bumps the epoch', () => {
		const unsubscribed = []
		store = { unsubscribe: (handle) => unsubscribed.push(handle) }
		const unwatch = vi.fn()
		const vm = view({ liveHandles: ['h1', 'h2'], liveActive: true, liveUnwatch: unwatch, liveEpoch: 3 })

		vm.releaseLiveSubscriptions()

		expect(unsubscribed).toEqual(['h1', 'h2'])
		expect(unwatch).toHaveBeenCalledTimes(1)
		expect([vm.liveHandles, vm.liveActive, vm.liveUnwatch, vm.liveEpoch]).toEqual([[], false, null, 4])
	})
})
