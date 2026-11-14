/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin settings entry (src/settings.js): a separate bundle from main.js,
 * so it registers the library's translations itself, renders the admin
 * settings view, installs the t / n mixin and mounts on #settings.
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'

const seen = vi.hoisted(() => ({ registered: 0, apps: [] }))

vi.mock('@conduction/nextcloud-vue', () => ({
	registerTranslations: () => {
		seen.registered++
	},
}))
vi.mock('@conduction/nextcloud-vue/css/index.css', () => ({}))
vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text) => `t:${app}:${text}`,
	translatePlural: (app, one, many, count) => `n:${app}:${count === 1 ? one : many}`,
}))
vi.mock('../../src/views/settings/Settings.vue', () => ({ default: { name: 'AdminSettingsStub' } }))
vi.mock('vue', async (importOriginal) => {
	const vue = await importOriginal()
	return {
		...vue,
		createApp: (root) => {
			const app = { root, mixins: [], mountedOn: null }
			app.mixin = (mixin) => {
				app.mixins.push(mixin)
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

describe('settings entry', () => {
	let app

	beforeAll(async () => {
		await import('../../src/settings.js')
		app = seen.apps[0]
	})

	it('registers the library translations once', () => {
		expect(seen.registered).toBe(1)
	})

	it('creates one app and mounts it on #settings', () => {
		expect(seen.apps).toHaveLength(1)
		expect(app.mountedOn).toBe('#settings')
	})

	it('renders the admin settings view', () => {
		const vnode = app.root.render()

		expect(vnode.type).toEqual({ name: 'AdminSettingsStub' })
	})

	it('installs the app-wide t and n helpers', () => {
		expect(app.mixins).toHaveLength(1)
		const { t, n } = app.mixins[0].methods

		expect(t('larpinq', 'Save')).toBe('t:larpinq:Save')
		expect(n('larpinq', 'one world', '%n worlds', 2)).toBe('n:larpinq:%n worlds')
	})
})
