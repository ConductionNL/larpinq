/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the requests the roster, the skill tree and the settings
 * page send to larpinq's own API. Each method runs against a
 * fake component and a recorded fetch, and every fetch call (url and init, as
 * JSON text so key order counts) and the state left behind are compared with
 * fixtures/ocsRequests.golden.json.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EventRoster from '../../src/views/EventRoster.vue'
import Settings from '../../src/views/settings/Settings.vue'
import SkillTree from '../../src/views/SkillTree.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
	translatePlural: (app, one) => one,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({ default: {} }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnAdminSettingsShell: {},
	CnDataTable: {},
	CnIcon: {},
	CnSettingsSection: {},
	CnStatusBadge: {},
	useObjectStore: () => ({}),
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (url, params) => `${url} ${JSON.stringify(params ?? null)}`,
}))
vi.mock('@nextcloud/vue', () => ({
	NcButton: {},
	NcLoadingIcon: {},
	NcNoteCard: {},
	NcSelect: {},
}))
vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({ default: {} }))
vi.mock('vue-material-design-icons/ContentSave.vue', () => ({ default: {} }))
vi.mock('../../src/store/store.js', () => ({ ensureObjectTypes: async () => {} }))

const FIXTURE = resolve(__dirname, 'fixtures/ocsRequests.golden.json')

/**
 * Run one method with a recorded fetch that answers with the given response.
 *
 * @param {Function} method The method, called with `self` as `this`.
 * @param {object} self The fake component.
 * @param {Array} args The method arguments.
 * @param {object} answer The response fields: ok, status and the JSON body.
 * @return {Promise<object>} The fetch calls and the state left behind.
 */
async function record(method, self, args, answer) {
	const calls = []
	globalThis.OC = { requestToken: 'token-1' }
	globalThis.t = (app, key) => key
	globalThis.fetch = async (url, init) => {
		calls.push(JSON.stringify([url, init]))
		return {
			ok: answer.ok,
			status: answer.status,
			json: async () => answer.body,
		}
	}
	vi.spyOn(console, 'warn').mockImplementation(() => {})
	vi.spyOn(console, 'error').mockImplementation(() => {})
	let result
	try {
		result = await method.apply(self, args)
	} catch (error) {
		result = `threw ${error.message}`
	}
	const state = {}
	for (const [key, value] of Object.entries(self)) {
		if (typeof value !== 'function') {
			state[key] = value
		}
	}
	return {
		calls,
		result: result === undefined ? null : result,
		state: JSON.stringify(state),
	}
}

const OK = {
	ok: true,
	status: 200,
	body: { participants: [{ character: 'c1' }], isGm: true, status: 'checked-in' },
}
const FAIL = { ok: false, status: 500, body: {} }

/**
 * The cases: name => [component options, method path, fake component, args, answer].
 *
 * @return {object} The cases.
 */
function cases() {
	return {
		'roster fetch ok': [
			EventRoster,
			'methods.fetchRoster',
			{ eventId: 'e1' },
			[],
			OK,
		],
		'roster fetch failing': [
			EventRoster,
			'methods.fetchRoster',
			{ eventId: 'e1' },
			[],
			FAIL,
		],
		'roster fetch without event': [
			EventRoster,
			'methods.fetchRoster',
			{ eventId: '' },
			[],
			OK,
		],
		'attendance write ok': [
			EventRoster,
			'methods.setStatus',
			{ eventId: 'e1' },
			[{ character: 'c1' }, 'checked-in'],
			OK,
		],
		'attendance write failing': [
			EventRoster,
			'methods.setStatus',
			{ eventId: 'e1' },
			[{ character: 'c1' }, 'no-show'],
			FAIL,
		],
		'requirement report ok': [
			SkillTree,
			'methods.onCharacterChange',
			{ selectedCharacter: { value: 'c9' } },
			[],
			OK,
		],
		'requirement report failing': [
			SkillTree,
			'methods.onCharacterChange',
			{ selectedCharacter: { value: 'c9' } },
			[],
			FAIL,
		],
		'requirement report without character': [
			SkillTree,
			'methods.onCharacterChange',
			{ selectedCharacter: null },
			[],
			OK,
		],
		'settings save ok': [
			Settings,
			'methods.saveAll',
			{ configuration: { register: 'r1' } },
			[],
			OK,
		],
		'settings save failing': [
			Settings,
			'methods.saveAll',
			{ configuration: { register: 'r1' } },
			[],
			FAIL,
		],
	}
}

/**
 * Read a dotted path from a component's options.
 *
 * @param {object} options The component options.
 * @param {string} path The path, such as `methods.fetchRoster`.
 * @return {Function} The method.
 */
function methodAt(options, path) {
	return path.split('.').reduce((node, key) => node[key], options)
}

describe('larpinq API requests', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
		delete globalThis.OC
		delete globalThis.t
	})

	it('match the golden', async () => {
		const actual = {}
		for (const [name, [options, method, self, args, answer]] of Object.entries(
			cases(),
		)) {
			actual[name] = await record(
				methodAt(options, method),
				{ ...self },
				args,
				answer,
			)
		}

		if (process.env.RECORD_GOLDEN === '1') {
			writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(readFileSync(FIXTURE, 'utf8'))
		expect(Object.keys(actual)).toStrictEqual(Object.keys(expected))
		for (const name of Object.keys(expected)) {
			expect(actual[name], name).toStrictEqual(expected[name])
		}
	})
})
