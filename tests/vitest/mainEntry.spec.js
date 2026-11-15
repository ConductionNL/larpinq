/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The app entry (src/main.js): builds the effective manifest from the bundled
 * manifest, the manifest.d fragments and the menu layout, gates the imports
 * for a user who may not import, turns every page into a route, picks the
 * router base from the URL the visitor actually used, and mounts the app on
 * #content with the t / n mixin, pinia and the router installed.
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
		icons: [],
		translations: 0,
		translationsThrow: options.translationsThrow ?? 0,
		loadTranslations: options.loadTranslations ?? (() => Promise.reject(new Error('404'))),
		builds: [],
		histories: [],
		routers: [],
		apps: [],
		gameMaster: options.gameMaster ?? false,
		warnings: [],
	})
}
reset()

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
	CnPageRenderer: Object.freeze({ name: 'CnPageRenderer' }),
	defaultPageTypes: Object.freeze({ index: 'IndexType' }),
	registerBuiltinDashboardWidgets: () => {
		seen.widgets++
	},
	registerIcons: (icons) => {
		seen.icons.push(icons)
	},
	registerTranslations: () => {
		seen.translations++
		if (seen.translationsThrow > 0) {
			seen.translationsThrow--
			throw new Error('no catalogue')
		}
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
	translate: (app, text) => `t:${app}:${text}`,
	translatePlural: (app, one, many, count) => `n:${app}:${count === 1 ? one : many}`,
}))
vi.mock('vue-router', () => ({
	createWebHistory: (base) => {
		seen.histories.push(base)
		return { base }
	},
	createRouter: (options) => {
		const router = { options }
		seen.routers.push(router)
		return router
	},
}))
vi.mock('vue', async (importOriginal) => {
	const vue = await importOriginal()
	return {
		...vue,
		createApp: (root) => {
			const app = { root, mixins: [], plugins: [], mountedOn: null }
			app.mixin = (mixin) => {
				app.mixins.push(mixin)
				return app
			}
			app.use = (plugin) => {
				app.plugins.push(plugin)
				return app
			}
			app.mount = (selector) => {
				app.mountedOn = selector
				return app
			}
			seen.apps.push(app)
			return app
		},
	}
})

/**
 * Load src/main.js afresh, as a page load at the given path.
 *
 * @param {string} pathname The location pathname of the page load.
 * @return {Promise<object>} The created app.
 */
async function loadEntry(pathname) {
	vi.stubGlobal('window', { location: { pathname } })
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
	return seen.apps[0]
}

describe('main entry', () => {
	beforeEach(() => {
		reset()
	})

	afterEach(() => {
		delete Function.prototype.context
		vi.unstubAllGlobals()
		vi.restoreAllMocks()
	})

	it('registers the widget catalogue, the icons and the library translations twice', async () => {
		await loadEntry('/apps/larpinq/')

		expect(seen.widgets).toBe(1)
		expect(seen.icons).toEqual([{ Account: 'mdi-account' }])
		expect(seen.translations).toBe(2)
		expect(seen.warnings).toEqual([])
	})

	it('warns and carries on when registering the library translations fails', async () => {
		reset({ translationsThrow: 2 })
		const app = await loadEntry('/apps/larpinq/')

		expect(seen.warnings.map((args) => args[0])).toEqual([
			'[larpinq] registerTranslations failed; falling back to English',
			'[larpingapp] registerTranslations failed; falling back to English',
		])
		expect(seen.warnings[0][1]).toBeInstanceOf(Error)
		expect(app.mountedOn).toBe('#content')
	})

	it('loads the app translations whether they resolve, reject, throw or return nothing', async () => {
		const calls = []
		reset({
			loadTranslations: (app, callback) => {
				calls.push([app, typeof callback])
				return Promise.reject(new Error('404'))
			},
		})
		await loadEntry('/apps/larpinq/')
		expect(calls).toEqual([['larpinq', 'function']])

		reset({
			loadTranslations: () => {
				throw new Error('offline')
			},
		})
		const app = await loadEntry('/apps/larpinq/')
		expect(app.mountedOn).toBe('#content')

		reset({ loadTranslations: () => undefined })
		expect((await loadEntry('/apps/larpinq/')).mountedOn).toBe('#content')

		reset({
			loadTranslations: (app, callback) => {
				callback()
				return Promise.resolve()
			},
		})
		expect((await loadEntry('/apps/larpinq/')).mountedOn).toBe('#content')
	})

	it('builds the manifest from the bundled manifest, the sorted fragments and the menu layout', async () => {
		await loadEntry('/apps/larpinq/')

		expect(seen.context).toEqual({ dir: './manifest.d', deep: false, pattern: /\.json$/ })
		expect(seen.builds).toHaveLength(1)
		expect(seen.builds[0].fragments).toEqual([{ id: 'a' }, { id: 'b' }])
		expect(Array.isArray(seen.builds[0].bundled.pages)).toBe(true)
		expect(seen.builds[0].layout).toBeTypeOf('object')
	})

	it('removes the imports for a user who may not import', async () => {
		const app = await loadEntry('/apps/larpinq/')
		const manifest = app.root.render().props.manifest

		expect(manifest.pages[1].config).toEqual({ showMassImport: false, headerActions: [{ id: 'export' }] })
	})

	it('keeps the imports for a game master', async () => {
		reset({ gameMaster: true })
		const app = await loadEntry('/apps/larpinq/')
		const manifest = app.root.render().props.manifest

		expect(manifest.pages[1].config).toEqual({ showMassImport: true, headerActions: [{ id: 'import-campaign' }, { id: 'export' }] })
	})

	it('turns every page into a route, with props only for parameterised routes, plus a catch-all', async () => {
		await loadEntry('/apps/larpinq/')
		const routes = seen.routers[0].options.routes

		expect(routes.map(({ name, path, props, redirect }) => JSON.parse(JSON.stringify({ name, path, props, redirect })))).toEqual([
			{ name: 'dashboard', path: '/', props: false },
			{ name: 'characters', path: '/characters', props: false },
			{ name: 'character', path: '/characters/:id', props: true },
			{ path: '/:pathMatch(.*)*', redirect: '/' },
		])
		expect(routes[0].component).toEqual({ name: 'CnPageRenderer' })
		expect(Object.isExtensible(routes[0].component)).toBe(true)
		expect(routes[1].component).toBe(routes[0].component)
	})

	it('takes the router base from the URL the visitor used', async () => {
		await loadEntry('/index.php/apps/larpinq/characters/5')
		expect(seen.histories).toEqual(['/index.php/apps/larpinq'])
		expect(seen.routers[0].options.history).toEqual({ base: '/index.php/apps/larpinq' })

		reset()
		await loadEntry('/apps/larpinq')
		expect(seen.histories).toEqual(['/apps/larpinq'])
	})

	it('falls back to the generated app URL outside the app path', async () => {
		await loadEntry('/apps/larpinqx/')

		expect(seen.histories).toEqual(['/index.php/apps/larpinq'])
	})

	it('mounts one app on #content with extensible page types and registry', async () => {
		const app = await loadEntry('/apps/larpinq/')

		expect(seen.apps).toHaveLength(1)
		expect(app.mountedOn).toBe('#content')
		const vnode = app.root.render()
		expect(vnode.type).toEqual({ name: 'AppStub' })
		expect(vnode.props.pageTypes).toEqual({ index: 'IndexType' })
		expect(Object.isExtensible(vnode.props.pageTypes)).toBe(true)
		expect(vnode.props.registry).toEqual({ WorldSwitcher: { kind: 'section' } })
		expect(Object.isExtensible(vnode.props.registry)).toBe(true)
	})

	it('installs the t / n mixin, then pinia, then the router', async () => {
		const app = await loadEntry('/apps/larpinq/')
		const pinia = (await import('../../src/pinia.js')).default

		expect(app.mixins).toHaveLength(1)
		expect(app.mixins[0].methods.t('larpinq', 'Save')).toBe('t:larpinq:Save')
		expect(app.mixins[0].methods.n('larpinq', 'one world', '%n worlds', 2)).toBe('n:larpinq:%n worlds')
		expect(app.plugins).toEqual([pinia, seen.routers[0]])
	})
})
