/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the object tabs that resolve their object id from the
 * `objectId` prop, the injected object context or the route. Each tab is
 * mounted with a renderer that draws nothing, once per id source, and the
 * resolved id, the prop/attr split and the service calls made on mount are
 * compared with fixtures/objectTabs.golden.json. The compiled template and
 * the style block are pinned by hash, so what the tab renders stays the same.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { compileTemplate, parse } from '@vue/compiler-sfc'
import { createRenderer, h } from '@vue/runtime-core'
import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import BuildReport from '../../src/views/BuildReport.vue'
import CharacterCustomFields from '../../src/views/CharacterCustomFields.vue'
import CharacterStatSheet from '../../src/views/CharacterStatSheet.vue'
import EventChoiceCounts from '../../src/views/EventChoiceCounts.vue'
import EventRoster from '../../src/views/EventRoster.vue'
import PlayerAttendanceHistory from '../../src/views/PlayerAttendanceHistory.vue'
import RegistrationChanges from '../../src/views/RegistrationChanges.vue'
import RegistrationChoices from '../../src/views/RegistrationChoices.vue'

const { trace, recordModule } = vi.hoisted(() => {
	const calls = []
	return {
		trace: calls,
		recordModule: (name, real) => {
			const out = {}
			for (const [key, value] of Object.entries(real)) {
				if (typeof value !== 'function') {
					out[key] = value
				} else if (key.startsWith('fetch')) {
					out[key] = async (...args) => {
						calls.push([`${name}.${key}`, ...args])
						return {}
					}
				} else {
					out[key] = value
				}
			}
			return out
		},
	}
})

vi.mock('@nextcloud/l10n', () => ({ translate: (app, key) => key }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (url, params) => `${url} ${JSON.stringify(params)}`,
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnDataTable: {},
	CnStatusBadge: {},
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: {},
}))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))
vi.mock('../../src/services/registrationChoices.js', async (load) =>
	recordModule('registrationChoices', await load()),
)
vi.mock('../../src/services/registrationChanges.js', async (load) =>
	recordModule('registrationChanges', await load()),
)
vi.mock('../../src/services/characterCustomFields.js', async (load) =>
	recordModule('characterCustomFields', await load()),
)
vi.mock('../../src/services/characterStats.js', async (load) =>
	recordModule('characterStats', await load()),
)
vi.mock('../../src/services/playerAttendance.js', async (load) =>
	recordModule('playerAttendance', await load()),
)
vi.mock('../../src/services/characterBuilds.js', async (load) =>
	recordModule('characterBuilds', await load()),
)

const FIXTURE = resolve(__dirname, 'fixtures/objectTabs.golden.json')

const TABS = [
	['BuildReport', BuildReport, 'buildId'],
	['CharacterCustomFields', CharacterCustomFields, 'characterId'],
	['CharacterStatSheet', CharacterStatSheet, 'characterId'],
	['EventChoiceCounts', EventChoiceCounts, 'eventId'],
	['EventRoster', EventRoster, 'eventId'],
	['PlayerAttendanceHistory', PlayerAttendanceHistory, 'playerId'],
	['RegistrationChanges', RegistrationChanges, 'registrationId'],
	['RegistrationChoices', RegistrationChoices, 'registrationId'],
]

const NONE = Symbol('none')

const SOURCES = {
	'prop wins': { prop: 'p-1', context: { objectId: 'c-1' }, route: 'r-1' },
	'context next': { prop: NONE, context: { objectId: 'c-1' }, route: 'r-1' },
	'empty prop falls through': {
		prop: '',
		context: { objectId: 'c-1' },
		route: 'r-1',
	},
	'route when no context is provided': {
		prop: NONE,
		context: NONE,
		route: 'r-1',
	},
	'numeric route id': { prop: NONE, context: {}, route: 7 },
	'literal placeholder in the context': {
		prop: NONE,
		context: { objectId: '@objectId' },
		route: 'r-2',
	},
	'nothing at all': { prop: NONE, context: NONE, route: NONE },
}

const nodeOps = {
	insert: () => {},
	remove: () => {},
	createElement: (tag) => ({ tag }),
	createText: (text) => ({ text }),
	createComment: (text) => ({ text }),
	setText: () => {},
	setElementText: () => {},
	parentNode: () => null,
	nextSibling: () => null,
	patchProp: () => {},
}

const flush = () => new Promise((done) => setTimeout(done, 0))

/**
 * The resolved prop definitions of a mounted tab.
 *
 * @param {object} vm The mounted tab.
 * @return {object} Type names and default per prop.
 */
function propsOf(vm) {
	const out = {}
	for (const [key, def] of Object.entries(vm.$.propsOptions[0])) {
		out[key] = {
			type: [def.type].flat().map((type) => type?.name ?? String(type)),
			default:
				typeof def.default === 'function'
					? `function ${JSON.stringify(def.default())}`
					: (def.default ?? '(none)'),
		}
	}
	return out
}

/**
 * Mount one tab for one id source and record what it resolved and called.
 *
 * @param {Array} tab [name, component, id computed]
 * @param {object} source The prop, context and route to mount with.
 * @return {Promise<object>} The recorded scenario.
 */
async function scenario(tab, source) {
	const [, component, idName] = tab
	trace.length = 0
	globalThis.OC = { requestToken: 'token' }
	globalThis.fetch = async (url) => {
		trace.push(['fetch', url])
		return { ok: false, status: 503, json: async () => ({}) }
	}
	const props = source.prop === NONE ? {} : { objectId: source.prop }
	let vm = null
	const { createApp } = createRenderer(nodeOps)
	const app = createApp({
		render: () =>
			h(
				{
					extends: component,
					render: () => null,
					mounted() {
						vm = this
					},
				},
				props,
			),
	})
	if (source.context !== NONE) {
		app.provide('cnObjectContext', source.context)
	}
	if (source.route !== NONE) {
		app.config.globalProperties.$route = { params: { id: source.route } }
	}
	app.mount({ tag: 'root' })
	await flush()
	await flush()
	const out = {
		id: vm[idName],
		objectIdProp: vm.objectId,
		objectIdInAttrs: 'objectId' in vm.$attrs,
		props: propsOf(vm),
		calls: [...trace],
	}
	app.unmount()
	return out
}

/**
 * Hash the compiled template and the style block of one tab.
 *
 * @param {string} name The file name without extension.
 * @return {object} The two hashes.
 */
function fileHashes(name) {
	const file = `${name}.vue`
	const source = readFileSync(
		resolve(__dirname, `../../src/views/${file}`),
		'utf8',
	)
	const { descriptor } = parse(source, { filename: file })
	const render = compileTemplate({
		source: descriptor.template.content,
		filename: file,
		id: name,
	}).code
	const sha = (text) => createHash('sha256').update(text).digest('hex')
	return {
		templateRenderSha256: sha(render),
		styleSha256: sha(descriptor.styles.map((s) => s.content).join('\n')),
	}
}

/**
 * Record every tab under every id source.
 *
 * @return {Promise<object>} The golden record.
 */
async function record() {
	const out = {}
	for (const tab of TABS) {
		const scenarios = {}
		for (const [label, source] of Object.entries(SOURCES)) {
			scenarios[label] = await scenario(tab, source)
		}
		out[tab[0]] = { ...fileHashes(tab[0]), scenarios }
	}
	return out
}

describe('object tabs id resolution (golden)', () => {
	it('matches the recorded behaviour', async () => {
		const actual = await record()
		if (process.env.RECORD_GOLDEN === '1') {
			writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(readFileSync(FIXTURE, 'utf8'))
		for (const name of Object.keys(expected)) {
			expect(actual[name], name).toEqual(expected[name])
		}
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
	})
})
