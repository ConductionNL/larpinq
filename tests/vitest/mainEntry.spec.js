/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The app entry (src/main.js): builds the effective manifest from the bundled
 * manifest, the manifest.d fragments and the menu layout, gates the imports
 * for a user who may not import, and boots on CnAppRoot in one call through
 * the library's mountCnApp, with App.vue as the root, the kind-tagged
 * registry, the library's page types, the app's icons, pinia and vue-router's
 * factories. The router, its base, the icon and translation registration and
 * the t / n mixin are mountCnApp's work and are tested in the library.
 *
 * @spec openspec/changes/larpinq-manifest-tier-4/specs/larpinq-manifest-tier-4/spec.md#requirement-cnapproot-shall-be-the-boot-entry-point
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const seen = vi.hoisted(() => ({}))

/**
 * Reset what the mocks record and how they behave.
 *
 * @param {object} options Behaviour overrides.
 */
function reset(options = {}) {
	Object.assign(seen, {
		widgets: 0,
		loadTranslations: options.loadTranslations ?? (() => Promise.reject(new Error('404'))),
		builds: [],
		mounts: [],
		gameMaster: options.gameMaster ?? false,
		warnings: [],
	})
}
reset()

const createRouter = vi.hoisted(() => () => ({}))
const createWebHistory = vi.hoisted(() => () => ({}))

vi.mock('@conduction/nextcloud-vue', () => ({
	buildManifest: (bundled, fragments, layout) => {
		seen.builds.push({ bundled, fragments, layout })
		return {
			pages: [
				{ id: 'dashboard', route: '/', type: 'dashboard' },
				{ id: 'characters', route: '/characters', type: 'index', config: { showMassImport: true, headerActions: [{ id: 'import-campaign' }, { id: 'export' }] } },
				{ id: 'character', route: '/characters/:id', type: 'detail' },
			],
		}
	},
	defaultPageTypes: Object.freeze({ index: 'IndexType' }),
	mountCnApp: (target, options) => {
		seen.mounts.push({ target, options })
		return { app: {}, router: {} }
	},
	registerBuiltinDashboardWidgets: () => {
		seen.widgets++
	},
}))
vi.mock('@conduction/nextcloud-vue/css/index.css', () => ({}))
vi.mock('gridstack/dist/gridstack.min.css', () => ({}))
vi.mock('../../src/assets/app.css', () => ({}))
vi.mock('../../src/App.vue', () => ({ default: { name: 'AppStub' } }))
vi.mock('../../src/registry.js', () => ({ default: Object.freeze({ WorldSwitcher: { kind: 'section' } }) }))
vi.mock('../../src/icons.js', () => ({ default: { Account: 'mdi-account' } }))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: (app, key, fallback) => (app === 'larpinq' && key === 'isGameMaster' ? seen.gameMaster : fallback),
}))
vi.mock('@nextcloud/l10n', () => ({
	loadTranslations: (app, callback) => seen.loadTranslations(app, callback),
}))
vi.mock('vue-router', () => ({ createRouter, createWebHistory }))

/**
 * Load src/main.js afresh, as a page load.
 *
 * @return {Promise<object>} The one mountCnApp call: its target and options.
 */
async function loadEntry() {
	const fragments = {
		'./b.json': { id: 'b' },
		'./a.json': { id: 'a' },
	}
	const context = (key) => fragments[key]
	context.keys = () => Object.keys(fragments)
	// `require.context` is resolved by webpack at build time and has no
	// vitest equivalent; the module's own `require` is a plain function, so
	// the webpack API is provided on every function for the length of a test.
	Object.defineProperty(Function.prototype, 'context', {
		configurable: true,
		writable: true,
		value: (dir, deep, pattern) => {
			seen.context = { dir, deep, pattern }
			return context
		},
	})
	vi.spyOn(console, 'warn').mockImplementation((...args) => {
		seen.warnings.push(args)
	})
	vi.resetModules()
	await import('../../src/main.js')
	await Promise.resolve()
	return seen.mounts[0]
}

describe('main entry', () => {
	beforeEach(() => {
		reset()
	})

	afterEach(() => {
		delete Function.prototype.context
		vi.restoreAllMocks()
	})

	it('registers the widget catalogue once and warns about nothing', async () => {
		await loadEntry()

		expect(seen.widgets).toBe(1)
		expect(seen.warnings).toEqual([])
	})

	it('loads the app translations whether they resolve, reject, throw or return nothing', async () => {
		const calls = []
		reset({
			loadTranslations: (app, callback) => {
				calls.push([app, typeof callback])
				return Promise.reject(new Error('404'))
			},
		})
		await loadEntry()
		expect(calls).toEqual([['larpinq', 'function']])

		reset({
			loadTranslations: () => {
				throw new Error('offline')
			},
		})
		expect((await loadEntry()).target).toBe('#content')

		reset({ loadTranslations: () => undefined })
		expect((await loadEntry()).target).toBe('#content')

		reset({
			loadTranslations: (app, callback) => {
				callback()
				return Promise.resolve()
			},
		})
		expect((await loadEntry()).target).toBe('#content')
	})

	it('builds the manifest from the bundled manifest, the sorted fragments and the menu layout', async () => {
		await loadEntry()

		expect(seen.context).toEqual({ dir: './manifest.d', deep: false, pattern: /\.json$/ })
		expect(seen.builds).toHaveLength(1)
		expect(seen.builds[0].fragments).toEqual([{ id: 'a' }, { id: 'b' }])
		expect(Array.isArray(seen.builds[0].bundled.pages)).toBe(true)
		expect(seen.builds[0].layout).toBeTypeOf('object')
	})

	it('removes the imports for a user who may not import', async () => {
		const { options } = await loadEntry()

		expect(options.manifest.pages[1].config).toEqual({ showMassImport: false, headerActions: [{ id: 'export' }] })
	})

	it('keeps the imports for a game master', async () => {
		reset({ gameMaster: true })
		const { options } = await loadEntry()

		expect(options.manifest.pages[1].config).toEqual({ showMassImport: true, headerActions: [{ id: 'import-campaign' }, { id: 'export' }] })
	})

	it('boots once on #content through mountCnApp, with App.vue as the root', async () => {
		const { target, options } = await loadEntry()
		const pinia = (await import('../../src/pinia.js')).default

		expect(seen.mounts).toHaveLength(1)
		expect(target).toBe('#content')
		expect(options.appId).toBe('larpinq')
		expect(options.rootComponent).toEqual({ name: 'AppStub' })
		expect(options.registry).toEqual({ WorldSwitcher: { kind: 'section' } })
		expect(options.pageTypes).toEqual({ index: 'IndexType' })
		expect(options.icons).toEqual({ Account: 'mdi-account' })
		expect(options.plugins).toEqual([pinia])
		expect(options.vueRouter).toEqual({ createRouter, createWebHistory })
		expect(options.router).toBeUndefined()
		expect(options.manifest.pages.map((page) => page.id)).toEqual(['dashboard', 'characters', 'character'])
	})
})
