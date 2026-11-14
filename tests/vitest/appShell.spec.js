/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The App.vue shell options outside the tenant-switch watcher (which
 * appTenantSwitch.spec.js pins): the setup() wiring of the tenant context and
 * the active world, the sidebar channel, the permissions passthrough, the
 * registry → customComponents shim and the app-bound translate.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { isReactive } from 'vue'
import App from '../../src/App.vue'

const calls = vi.hoisted(() => ({ loadState: [], startActiveWorld: [], translate: [] }))
const tenantUuid = vi.hoisted(() => ({ value: 'org-7' }))

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: {},
	CnObjectSidebar: {},
	useTenantContext: () => ({ activeOrganisationUuid: tenantUuid }),
}))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args) => {
		calls.loadState.push(args)
		return 'world-3'
	},
}))
vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => {
		calls.translate.push([app, key])
		return `[${app}] ${key}`
	},
}))
vi.mock('@nextcloud/vue', () => ({ NcAppSettingsSection: {} }))
vi.mock('../../src/services/activeWorld.js', () => ({
	startActiveWorld: (world) => {
		calls.startActiveWorld.push(world)
		return { activeWorld: world }
	},
}))
vi.mock('../../src/store/modules/object.js', () => ({
	setObjectStoreTenantUuid: () => {},
	useObjectStore: () => null,
}))

beforeEach(() => {
	calls.loadState.length = 0
	calls.startActiveWorld.length = 0
	calls.translate.length = 0
})

afterEach(() => {
	delete globalThis.window
})

describe('App setup()', () => {
	it('exposes the tenant context uuid and seeds the active world from initial state', () => {
		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})

		const exposed = App.setup()

		warn.mockRestore()
		expect(exposed).toEqual({ cnActiveOrganisationUuid: tenantUuid })
		expect(exposed.cnActiveOrganisationUuid).toBe(tenantUuid)
		expect(calls.loadState).toEqual([['larpinq', 'activeWorld', '']])
		expect(calls.startActiveWorld).toEqual(['world-3'])
	})
})

describe('App data() and provide()', () => {
	it('starts with a closed, empty, reactive object sidebar and no synced tenant', () => {
		const data = App.data()

		expect(isReactive(data.objectSidebarState)).toBe(true)
		expect({ ...data.objectSidebarState }).toEqual({
			active: false,
			open: true,
			objectType: '',
			objectId: '',
			title: '',
			subtitle: '',
			register: '',
			schema: '',
			hiddenTabs: [],
			tabs: undefined,
		})
		expect(data.tenantSyncedUuid).toBeUndefined()
		expect('tenantSyncedUuid' in data).toBe(true)
	})

	it('gives every instance its own sidebar state', () => {
		expect(App.data().objectSidebarState).not.toBe(App.data().objectSidebarState)
	})

	it('provides the instance sidebar state as objectSidebarState', () => {
		const state = App.data().objectSidebarState

		const provided = App.provide.call({ objectSidebarState: state })

		expect(Object.keys(provided)).toEqual(['objectSidebarState'])
		expect(provided.objectSidebarState).toBe(state)
	})
})

describe('App permissions', () => {
	it('passes the current user permissions through', () => {
		globalThis.window = { OC: { currentUser: { permissions: ['read', 'write'] } } }

		expect(App.computed.permissions.call({})).toEqual(['read', 'write'])
	})

	it.each([
		['no OC global', {}],
		['no current user', { OC: {} }],
		['no permissions', { OC: { currentUser: {} } }],
		['null permissions', { OC: { currentUser: { permissions: null } } }],
	])('falls back to none with %s', (_label, win) => {
		globalThis.window = win

		expect(App.computed.permissions.call({})).toEqual([])
	})
})

describe('App customComponents', () => {
	const compute = (registry) => App.computed.customComponents.call({ registry })

	it('maps each registry entry with a component to that component, by key', () => {
		const Page = { name: 'Page' }
		const Modal = { name: 'Modal' }

		expect(compute({
			SkillTree: { kind: 'page', component: Page },
			XpAwardDialog: { kind: 'modal', component: Modal, propsSchema: {} },
		})).toEqual({ SkillTree: Page, XpAwardDialog: Modal })
	})

	it('skips handlers, metadata-only and empty entries, but keeps a falsy component', () => {
		const handler = () => {}

		const shim = compute({
			larpinqExportCampaign: { kind: 'handler', handler },
			MetadataOnly: { kind: 'widget' },
			Missing: null,
			Explicit: { kind: 'page', component: null },
		})

		expect(Object.keys(shim)).toEqual(['Explicit'])
		expect(shim).toStrictEqual({ Explicit: null })
	})

	it('is empty without a registry', () => {
		expect(compute(undefined)).toEqual({})
		expect(compute(null)).toEqual({})
		expect(compute({})).toEqual({})
	})
})

describe('App translateForApp', () => {
	it('translates in the larpinq domain', () => {
		expect(App.methods.translateForApp('Characters')).toBe('[larpinq] Characters')
		expect(calls.translate).toEqual([['larpinq', 'Characters']])
	})
})
