/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the live-update subscription of src/views/SkillTree.vue
 * (adopt-live-updates-ui): which types it subscribes, what it keeps, what it
 * drops when released while waiting, and how it recovers from a failed
 * subscription. Every store call, watcher and warning is recorded in order.
 *
 * @spec openspec/specs/realtime-updates/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import SkillTree from '../../src/views/SkillTree.vue'

const calls = []
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
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => url }))
vi.mock('../../src/store/store.js', () => ({ ensureObjectTypes: async () => {} }))

/**
 * A store that records subscribe/unsubscribe/getCollection.
 *
 * @param {object} options The store shape.
 * @return {object} The store.
 */
function recordingStore({ objectTypes, subscribe = true, fail = false, onSubscribe = null }) {
	const recorded = {
		objectTypes,
		unsubscribe: (handle) => calls.push(['unsubscribe', handle]),
		getCollection: (type) => {
			calls.push(['getCollection', type])
			return [type]
		},
	}
	if (subscribe) {
		recorded.subscribe = async (type) => {
			calls.push(['subscribe', type])
			if (onSubscribe) {
				onSubscribe()
			}
			if (fail) {
				throw new Error('no push')
			}
			return `handle-${type}`
		}
	}
	return recorded
}

/**
 * A fake component with the view's methods.
 *
 * @param {object} state The starting state.
 * @return {object} The fake component.
 */
function view(state = {}) {
	const self = {
		...SkillTree.methods,
		liveActive: false,
		liveEpoch: 0,
		liveHandles: [],
		liveUnwatch: null,
		...state,
		$watch: (source, callback) => {
			calls.push(['watch', source()])
			callback()
			return 'unwatch'
		},
		syncFromStore: (given) => calls.push(['syncFromStore', given === store]),
	}
	return self
}

/**
 * Run syncLiveSubscriptions and return the state it leaves.
 *
 * @param {object} self The fake component.
 * @return {Promise<object>} The live state.
 */
async function run(self) {
	await SkillTree.methods.syncLiveSubscriptions.call(self)
	return {
		liveActive: self.liveActive,
		liveHandles: self.liveHandles,
		liveUnwatch: self.liveUnwatch,
	}
}

afterEach(() => {
	calls.length = 0
	vi.restoreAllMocks()
})

describe('SkillTree live subscriptions', () => {
	it('does nothing without subscribe(), or when already active', async () => {
		store = recordingStore({ objectTypes: ['skill'], subscribe: false })
		expect(await run(view())).toEqual({ liveActive: false, liveHandles: [], liveUnwatch: null })
		store = recordingStore({ objectTypes: ['skill'] })
		expect(await run(view({ liveActive: true }))).toEqual({ liveActive: true, liveHandles: [], liveUnwatch: null })
		expect(calls).toEqual([])
	})

	it('does nothing when neither type is registered', async () => {
		store = recordingStore({ objectTypes: ['event'] })
		expect(await run(view())).toEqual({ liveActive: false, liveHandles: [], liveUnwatch: null })
		store = recordingStore({ objectTypes: undefined })
		expect(await run(view())).toEqual({ liveActive: false, liveHandles: [], liveUnwatch: null })
		expect(calls).toEqual([])
	})

	it('subscribes the registered types and bridges them to the local copies', async () => {
		store = recordingStore({ objectTypes: ['character', 'skill', 'event'] })
		expect(await run(view())).toEqual({
			liveActive: true,
			liveHandles: ['handle-skill', 'handle-character'],
			liveUnwatch: 'unwatch',
		})
		expect(calls).toEqual([
			['subscribe', 'skill'],
			['subscribe', 'character'],
			['getCollection', 'skill'],
			['getCollection', 'character'],
			['watch', [['skill'], ['character']]],
			['syncFromStore', true],
		])
	})

	it('drops the subscriptions when released while waiting', async () => {
		const self = view()
		store = recordingStore({ objectTypes: ['skill'], onSubscribe: () => { self.liveEpoch++ } })
		expect(await run(self)).toEqual({ liveActive: true, liveHandles: [], liveUnwatch: null })
		expect(calls).toEqual([['subscribe', 'skill'], ['unsubscribe', 'handle-skill']])
	})

	it('resets and warns when a subscription fails', async () => {
		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
		store = recordingStore({ objectTypes: ['skill'], fail: true })
		expect(await run(view({ liveHandles: ['old'] }))).toEqual({ liveActive: false, liveHandles: [], liveUnwatch: null })
		expect(calls).toEqual([['subscribe', 'skill']])
		expect(warn.mock.calls).toEqual([['[larpinq] skill-tree live subscription failed:', 'no push']])
	})
})

describe('SkillTree load', () => {
	it('loads every collection, and degrades a failure to an empty tree', async () => {
		const fetched = []
		const answers = {
			skill: [{ id: 's1' }],
			character: 'not a list',
			ability: [{ id: 'a1', name: 'Bash' }],
			condition: [],
			effect: [{ id: 'e1', name: 'Glow' }],
		}
		store = {
			fetchCollection: async (type, params) => {
				fetched.push([type, params])
				return answers[type]
			},
		}
		const self = view({ loading: null, skills: ['old'], characters: ['old'] })
		const loading = []
		const done = SkillTree.methods.load.call(self)
		loading.push(self.loading)
		await done
		loading.push(self.loading)
		expect(loading).toEqual([true, false])
		expect(fetched.map(([type, params]) => [type, params._limit])).toEqual([
			['skill', 1000],
			['character', 1000],
			['ability', 1000],
			['condition', 1000],
			['effect', 1000],
		])
		expect([self.skills, self.characters, self.abilityNames, self.conditionNames, self.effectNames]).toEqual([
			[{ id: 's1' }],
			[],
			{ a1: 'Bash' },
			{},
			{ e1: 'Glow' },
		])

		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
		store = { fetchCollection: async () => { throw new Error('down') } }
		const failed = view({ loading: null, skills: ['kept'] })
		await SkillTree.methods.load.call(failed)
		expect([failed.loading, failed.skills]).toEqual([false, ['kept']])
		expect(warn.mock.calls.map(([message]) => message)).toEqual(['[larpinq] skill-tree data unavailable'])
	})
})
