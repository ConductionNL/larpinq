/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin storage form (views/settings/Settings.vue): board order of the
 * object types, register/schema option building, the load that fills the
 * form from the stored configuration, the source/register change handlers,
 * and the save and re-import feedback.
 *
 * The component's options run against a fake `this`; only fetch and the
 * Nextcloud globals are mocked.
 *
 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import Settings from '../../src/views/settings/Settings.vue'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAdminSettingsShell: {},
	CnSettingsSection: {},
}))
vi.mock('@nextcloud/vue', () => ({
	NcButton: {},
	NcLoadingIcon: {},
	NcNoteCard: {},
	NcSelect: {},
}))
vi.mock('vue-material-design-icons/ContentSave.vue', () => ({ default: {} }))

const REGISTERS = [
	{
		id: 4,
		title: 'Larp',
		schemas: [
			{ id: 17, title: 'Character' },
			{ id: 18, title: 'Item' },
		],
	},
	{ id: 9, title: 'Other', schemas: [] },
]

/**
 * A fake component: data, computed getters and bound methods.
 *
 * @param {object} overrides Data to override.
 * @return {object} The fake `this`.
 */
function component(overrides = {}) {
	const self = { ...Settings.data(), ...overrides }
	for (const [name, method] of Object.entries(Settings.methods)) {
		self[name] = method.bind(self)
	}
	for (const [name, getter] of Object.entries(Settings.computed)) {
		Object.defineProperty(self, name, { get: () => getter.call(self) })
	}
	return self
}

/**
 * Mock fetch to answer every call with the given response.
 *
 * @param {object} answer `{ ok, body }` or `{ reject }`.
 * @return {Array} The recorded calls.
 */
function mockFetch(answer) {
	const calls = []
	globalThis.fetch = vi.fn(async (url, init) => {
		calls.push([url, init])
		if (answer.reject) {
			throw answer.reject
		}
		return { ok: answer.ok, json: async () => answer.body }
	})
	return calls
}

beforeEach(() => {
	globalThis.t = (app, key) => key
	globalThis.OC = { requestToken: 'token-1' }
})

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('admin storage form: computed', () => {
	it('orders known object types as the board does and appends unknown ones', () => {
		const self = component({
			settings: { objectTypes: ['setting', 'zebra', 'item', 'character', 'alpha'] },
		})
		expect(self.orderedObjectTypes).toEqual(['character', 'item', 'setting', 'zebra', 'alpha'])
	})

	it('has no ordered types when the server sent none', () => {
		expect(component({ settings: {} }).orderedObjectTypes).toEqual([])
	})

	it('offers each available register by title with a string id', () => {
		const self = component({ settings: { availableRegisters: REGISTERS } })
		expect(self.registerOptions).toEqual([
			{ label: 'Larp', value: '4' },
			{ label: 'Other', value: '9' },
		])
		expect(component({ settings: {} }).registerOptions).toEqual([])
	})

	it('links the path line to the admin settings', () => {
		expect(component().breadcrumbs).toEqual([
			{ label: 'Admin settings', href: '/index.php/settings/admin' },
			{ label: 'Larpinq' },
		])
	})
})

describe('admin storage form: lookups', () => {
	it('resolves register and schema titles, empty when unknown', () => {
		const self = component({ settings: { availableRegisters: REGISTERS } })
		expect(self.getRegisterLabel('4')).toBe('Larp')
		expect(self.getRegisterLabel('99')).toBe('')
		expect(self.getSchemaLabel('4', '18')).toBe('Item')
		expect(self.getSchemaLabel('4', '99')).toBe('')
		expect(self.getSchemaLabel('99', '17')).toBe('')
	})

	it('builds schema options for one register only', () => {
		const self = component({ settings: { availableRegisters: REGISTERS } })
		expect(self.getSchemaOptions('4')).toEqual([
			{ label: 'Character', value: '17' },
			{ label: 'Item', value: '18' },
		])
		expect(self.getSchemaOptions('9')).toEqual([])
		expect(self.getSchemaOptions('99')).toEqual([])
		expect(self.getSchemaOptions(undefined)).toEqual([])
	})

	it('labels known types as the menu does and capitalises unknown ones', () => {
		const self = component()
		expect(self.typeLabel('setting')).toBe('Worlds')
		expect(self.typeLabel('character')).toBe('Characters')
		expect(self.typeLabel('quest')).toBe('Quest')
	})
})

describe('admin storage form: change handlers', () => {
	it('clears register and schema when a type falls back to internal storage', () => {
		const self = component({
			configuration: {
				item: { source: { value: 'internal' }, register: { value: '4' }, schema: { value: '18' } },
				skill: { source: { value: 'openregister' }, register: { value: '4' }, schema: { value: '17' } },
			},
		})
		self.handleSourceChange('item')
		self.handleSourceChange('skill')
		expect(self.configuration.item).toEqual({ source: { value: 'internal' }, register: null, schema: null })
		expect(self.configuration.skill.register).toEqual({ value: '4' })
		expect(self.configuration.skill.schema).toEqual({ value: '17' })
	})

	it('drops the schema when the register changes', () => {
		const self = component({
			configuration: { item: { register: { value: '9' }, schema: { value: '18' } } },
		})
		self.handleRegisterChange('item')
		expect(self.configuration.item).toEqual({ register: { value: '9' }, schema: null })
	})
})

describe('admin storage form: load', () => {
	it('fills the form from the stored configuration', async () => {
		const calls = mockFetch({
			ok: true,
			body: {
				objectTypes: ['character', 'item'],
				availableRegisters: REGISTERS,
				configuration: {
					character_source: 'openregister',
					character_register: '4',
					character_schema: '17',
				},
			},
		})
		const self = component()

		await self.loadSettings()

		expect(calls[0][0]).toBe('/index.php/apps/larpinq/api/settings')
		expect(self.loading).toBe(false)
		expect(self.configuration.character).toEqual({
			source: { label: 'Open Register', value: 'openregister' },
			register: { label: 'Larp', value: '4' },
			schema: { label: 'Character', value: '17' },
		})
		expect(self.configuration.item).toEqual({
			source: { label: 'Internal', value: 'internal' },
			register: null,
			schema: null,
		})
	})

	it('stays loading when the settings cannot be read', async () => {
		mockFetch({ reject: new Error('offline') })
		const self = component()

		await self.loadSettings()

		expect(self.loading).toBe(true)
		expect(self.configuration).toEqual({})
	})
})

describe('admin storage form: save', () => {
	it('posts the payload with the request token and reports success', async () => {
		const calls = mockFetch({ ok: true, body: {} })
		const self = component({
			message: 'old',
			configuration: {
				character: { source: { value: 'openregister' }, register: { value: '4' }, schema: { value: '17' } },
			},
		})

		await self.saveAll()

		expect(calls).toHaveLength(1)
		const [url, init] = calls[0]
		expect(url).toBe('/index.php/apps/larpinq/api/settings')
		expect(init.method).toBe('POST')
		expect(init.headers.requesttoken).toBe('token-1')
		expect(JSON.parse(init.body)).toEqual({
			character_source: 'openregister',
			character_register: '4',
			character_schema: '17',
		})
		expect(self.message).toBe('Settings saved successfully')
		expect(self.messageType).toBe('success')
		expect(self.saving).toBe(false)
	})

	it('reports a refused save as an error', async () => {
		mockFetch({ ok: false, body: {} })
		const self = component()

		await self.saveAll()

		expect(self.message).toBe('Failed to save settings')
		expect(self.messageType).toBe('error')
		expect(self.saving).toBe(false)
	})

	it('shows the network error message when the save throws', async () => {
		mockFetch({ reject: new Error('Network down') })
		const self = component()

		await self.saveAll()

		expect(self.message).toBe('Network down')
		expect(self.messageType).toBe('error')
		expect(self.saving).toBe(false)
	})
})

describe('admin storage form: re-import', () => {
	it('reports success and reloads the settings', async () => {
		const self = component()
		self.loadSettings = vi.fn(async () => {})

		await self.onReimported()

		expect(self.message).toBe('Configuration re-imported successfully')
		expect(self.messageType).toBe('success')
		expect(self.loadSettings).toHaveBeenCalledTimes(1)
	})

	it('shows the shell error, or a fallback without one', () => {
		const self = component()
		self.onReimportError(new Error('Bad file'))
		expect(self.message).toBe('Bad file')
		expect(self.messageType).toBe('error')

		self.onReimportError(undefined)
		expect(self.message).toBe('Re-import failed')
	})
})

describe('admin storage form: configuration load', () => {
	it('keeps the error the server returned', async () => {
		mockFetch({ ok: true, body: { error: 'No file' } })
		const self = component()
		self.loadSettings = vi.fn(async () => {})

		await self.loadConfiguration()

		expect(self.configurationResults).toEqual({ error: 'No file' })
		expect(self.loadSettings).not.toHaveBeenCalled()
		expect(self.loadingConfiguration).toBe(false)
	})

	it('reloads the settings after a successful load', async () => {
		mockFetch({ ok: true, body: {} })
		const self = component()
		self.loadSettings = vi.fn(async () => {})

		await self.loadConfiguration()

		expect(self.configurationResults).toEqual({ success: true })
		expect(self.loadSettings).toHaveBeenCalledTimes(1)
		expect(self.loadingConfiguration).toBe(false)
	})

	it('reports a failed request', async () => {
		mockFetch({ reject: new Error('timeout') })
		const self = component()

		await self.loadConfiguration()

		expect(self.configurationResults).toEqual({ error: 'Failed to load configuration: timeout' })
		expect(self.loadingConfiguration).toBe(false)
	})
})
