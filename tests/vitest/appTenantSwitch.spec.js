/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the tenant-switch watcher in src/App.vue: every scenario
 * records the calls the handler makes (object store tenant header, store
 * cache reset, router navigation) and compares them with
 * fixtures/appTenantSwitch.golden.json. The compiled template is pinned by
 * hash, so the render output stays the same as well.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { compileTemplate, parse } from '@vue/compiler-sfc'
import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import App from '../../src/App.vue'

const trace = []
const storeMode = { value: 'ok' }

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: {},
	CnObjectSidebar: {},
	useTenantContext: () => ({ activeOrganisationUuid: null }),
}))
vi.mock('@nextcloud/initial-state', () => ({ loadState: () => '' }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, key) => key }))
vi.mock('@nextcloud/vue', () => ({ NcAppSettingsSection: {} }))
vi.mock('../../src/services/activeWorld.js', () => ({
	startActiveWorld: () => ({}),
}))
vi.mock('../../src/store/modules/object.js', () => ({
	setObjectStoreTenantUuid: (uuid) =>
		trace.push(['setObjectStoreTenantUuid', uuid]),
	useObjectStore: () => {
		trace.push(['useObjectStore', storeMode.value])
		if (storeMode.value === 'throws') {
			throw new Error('no pinia')
		}
		if (storeMode.value === 'null') {
			return null
		}
		if (storeMode.value === 'no-action') {
			return { setActiveTenantOrganisation: 'not a function' }
		}
		return {
			setActiveTenantOrganisation: (uuid) =>
				trace.push(['setActiveTenantOrganisation', uuid]),
		}
	},
}))

const FIXTURE = resolve(__dirname, 'fixtures/appTenantSwitch.golden.json')

const detail = {
	path: '/characters/abc',
	params: { id: 'abc' },
	matched: [{ path: '/characters/:id' }],
}

const SCENARIOS = [
	{
		name: 'first fire with a uuid',
		synced: undefined,
		uuid: 'org-1',
		route: detail,
	},
	{ name: 'same uuid again', synced: 'org-1', uuid: 'org-1', route: detail },
	{ name: 'empty string becomes null', synced: 'org-1', uuid: '', route: null },
	{ name: 'non-string becomes null', synced: 'org-1', uuid: 42, route: null },
	{
		name: 'undefined after null is a no-op',
		synced: null,
		uuid: undefined,
		route: detail,
	},
	{
		name: 'detail route goes to its index',
		synced: 'org-1',
		uuid: 'org-2',
		route: detail,
	},
	{
		name: 'no matched record strips the last segment',
		synced: 'org-1',
		uuid: 'org-2',
		route: { path: '/characters/abc/', params: { id: 'abc' } },
	},
	{
		name: 'empty matched list strips the last segment',
		synced: 'org-1',
		uuid: 'org-2',
		route: { path: '/a/b', params: { id: 'b' }, matched: [] },
	},
	{
		name: 'params all empty',
		synced: 'org-1',
		uuid: 'org-2',
		route: {
			path: '/characters/x',
			params: { id: '', a: null, b: undefined },
			matched: [{ path: '/characters/:id' }],
		},
	},
	{
		name: 'no params object',
		synced: 'org-1',
		uuid: 'org-2',
		route: { path: '/characters/x', matched: [{ path: '/characters/:id' }] },
	},
	{
		name: 'index path empty becomes root',
		synced: 'org-1',
		uuid: 'org-2',
		route: { path: '/abc', params: { id: 'abc' }, matched: [{ path: '/:id' }] },
	},
	{
		name: 'target equals current path',
		synced: 'org-1',
		uuid: 'org-2',
		route: {
			path: '/characters',
			params: { id: 'x' },
			matched: [{ path: '/characters' }],
		},
	},
	{
		name: 'no router',
		synced: 'org-1',
		uuid: 'org-2',
		route: detail,
		router: 'none',
	},
	{
		name: 'router push rejects',
		synced: 'org-1',
		uuid: 'org-2',
		route: detail,
		router: 'rejects',
	},
	{ name: 'no route', synced: 'org-1', uuid: 'org-2', route: null },
	{
		name: 'route path without separator',
		synced: 'org-1',
		uuid: 'org-2',
		route: { path: 'home', params: { id: 'x' }, matched: [{ path: 'z/:id' }] },
	},
	{
		name: 'route path with only a colon',
		synced: 'org-1',
		uuid: 'org-2',
		route: { path: 'a:b', params: { id: 'x' }, matched: [{ path: 'x/:id' }] },
	},
	{
		name: 'store throws',
		synced: 'org-1',
		uuid: 'org-2',
		route: null,
		store: 'throws',
	},
	{
		name: 'store is null',
		synced: 'org-1',
		uuid: 'org-2',
		route: null,
		store: 'null',
	},
	{
		name: 'store without the action',
		synced: 'org-1',
		uuid: 'org-2',
		route: null,
		store: 'no-action',
	},
	{
		name: 'null to uuid on a detail route',
		synced: null,
		uuid: 'org-3',
		route: detail,
	},
]

/**
 * Builds the component instance the watcher handler runs against.
 *
 * @param {object} scenario One entry of SCENARIOS.
 * @return {object} The `this` of the handler, with the component methods bound.
 */
function instanceFor(scenario) {
	const vm = {
		tenantSyncedUuid: scenario.synced,
		$route: scenario.route ?? undefined,
	}
	if (scenario.router !== 'none') {
		vm.$router = {
			push(target) {
				trace.push(['push', target])
				return scenario.router === 'rejects'
					? Promise.reject(new Error('dup'))
					: Promise.resolve()
			},
		}
	}
	for (const [key, fn] of Object.entries(App.methods || {})) {
		vm[key] = fn.bind(vm)
	}
	return vm
}

/**
 * Runs every scenario and the template hash.
 *
 * @return {Promise<object>} The recorded result.
 */
async function record() {
	const scenarios = {}
	for (const scenario of SCENARIOS) {
		trace.length = 0
		storeMode.value = scenario.store ?? 'ok'
		const vm = instanceFor(scenario)
		const returned = App.watch.cnActiveOrganisationUuid.handler.call(
			vm,
			scenario.uuid,
		)
		await Promise.resolve()
		scenarios[scenario.name] = {
			returned: returned === undefined ? '(undefined)' : returned,
			tenantSyncedUuid:
				vm.tenantSyncedUuid === undefined
					? '(undefined)'
					: vm.tenantSyncedUuid,
			calls: [...trace],
		}
	}
	const source = readFileSync(resolve(__dirname, '../../src/App.vue'), 'utf8')
	const { descriptor } = parse(source, { filename: 'App.vue' })
	const render = compileTemplate({
		source: descriptor.template.content,
		filename: 'App.vue',
		id: 'app',
	}).code
	return {
		immediate: App.watch.cnActiveOrganisationUuid.immediate,
		templateRenderSha256: createHash('sha256').update(render).digest('hex'),
		scenarios,
	}
}

describe('App.vue tenant-switch watcher (golden)', () => {
	it('matches the recorded behaviour', async () => {
		const actual = await record()
		if (process.env.RECORD_GOLDEN === '1') {
			writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		expect(actual).toEqual(JSON.parse(readFileSync(FIXTURE, 'utf8')))
	})
})
