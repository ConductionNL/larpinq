/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the admin storage settings view's logic, run against a
 * fake instance: object types in board order, register and schema options
 * and labels, the per-type configuration built from the stored settings,
 * clearing register and schema when the source or register changes, saving
 * with its success and failure messages, and the re-import messages.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ CnAdminSettingsShell: {}, CnSettingsSection: {} }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => `/index.php${url}` }))
vi.mock('@nextcloud/vue', () => ({ NcButton: {}, NcLoadingIcon: {}, NcNoteCard: {}, NcSelect: {} }))
vi.mock('vue-material-design-icons/ContentSave.vue', () => ({ default: {} }))

const { default: Settings } = await import('../../src/views/settings/Settings.vue')

const registers = [
	{ id: 7, title: 'Larpinq', schemas: [{ id: 11, title: 'Character' }, { id: 12, title: 'Event' }] },
	{ id: 8, title: 'Archive', schemas: [] },
]

/**
 * A fake instance with the view's data, computed (as getters) and methods.
 *
 * @param {object} overrides Fields to set.
 * @return {object} The instance.
 */
function view(overrides = {}) {
	const vm = { ...Settings.data(), ...overrides }
	for (const [name, fn] of Object.entries(Settings.computed)) {
		Object.defineProperty(vm, name, { get: () => fn.call(vm) })
	}
	for (const [name, fn] of Object.entries(Settings.methods)) {
		vm[name] = fn.bind(vm)
	}
	return vm
}

/**
 * A fetch response answering `body`.
 *
 * @param {*} body The JSON body.
 * @param {boolean} ok Whether the status is 2xx.
 * @return {object} The response.
 */
function answer(body, ok = true) {
	return { ok, json: async () => body }
}

beforeEach(() => {
	globalThis.t = (_app, text) => text
	globalThis.OC = { requestToken: 'token-1' }
})

afterEach(() => {
	delete globalThis.t
	delete globalThis.OC
	delete globalThis.fetch
})

describe('Settings — options and labels', () => {
	it('orders the object types as the board does, unknown ones last', () => {
		const vm = view({ settings: { objectTypes: ['faction', 'item', 'character', 'setting', 'player'] } })
		expect(vm.orderedObjectTypes).toEqual(['character', 'player', 'item', 'setting', 'faction'])
		expect(view({ settings: {} }).orderedObjectTypes).toEqual([])
	})

	it('offers each register by its title and id', () => {
		const vm = view({ settings: { availableRegisters: registers } })
		expect(vm.registerOptions).toEqual([{ label: 'Larpinq', value: '7' }, { label: 'Archive', value: '8' }])
	})

	it('offers the schemas of a register, and none without one', () => {
		const vm = view({ settings: { availableRegisters: registers } })
		expect(vm.getSchemaOptions('7')).toEqual([{ label: 'Character', value: '11' }, { label: 'Event', value: '12' }])
		expect(vm.getSchemaOptions('')).toEqual([])
		expect(vm.getSchemaOptions('99')).toEqual([])
	})

	it('names registers and schemas, empty when unknown', () => {
		const vm = view({ settings: { availableRegisters: registers } })
		expect(vm.getRegisterLabel('8')).toBe('Archive')
		expect(vm.getRegisterLabel('99')).toBe('')
		expect(vm.getSchemaLabel('7', '12')).toBe('Event')
		expect(vm.getSchemaLabel('7', '99')).toBe('')
		expect(vm.getSchemaLabel('99', '11')).toBe('')
	})

	it('labels known types and capitalises others', () => {
		const vm = view()
		expect(vm.typeLabel('setting')).toBe('Worlds')
		expect(vm.typeLabel('ability')).toBe('Abilities')
		expect(vm.typeLabel('faction')).toBe('Faction')
	})

	it('leads the path back to the admin settings', () => {
		expect(view().breadcrumbs).toEqual([
			{ label: 'Admin settings', href: '/index.php/settings/admin' },
			{ label: 'Larpinq' },
		])
	})
})

describe('Settings — loading', () => {
	it('builds each type\'s configuration from the stored settings', async () => {
		globalThis.fetch = vi.fn(async () => answer({
			objectTypes: ['character', 'event'],
			availableRegisters: registers,
			configuration: { character_source: 'openregister', character_register: '7', character_schema: '11' },
		}))
		const vm = view()

		await Settings.created.call(vm)

		expect(globalThis.fetch).toHaveBeenCalledWith('/index.php/apps/larpinq/api/settings')
		expect(vm.loading).toBe(false)
		expect(vm.configuration.character).toEqual({
			source: { label: 'Open Register', value: 'openregister' },
			register: { label: 'Larpinq', value: '7' },
			schema: { label: 'Character', value: '11' },
		})
		expect(vm.configuration.event).toEqual({
			source: { label: 'Internal', value: 'internal' },
			register: null,
			schema: null,
		})
	})

	it('stays loading when the settings cannot be read', async () => {
		globalThis.fetch = vi.fn(async () => {
			throw new Error('offline')
		})
		const vm = view()

		await vm.loadSettings()

		expect(vm.loading).toBe(true)
		expect(vm.configuration).toEqual({})
	})
})

describe('Settings — changing a type', () => {
	it('clears register and schema when the source becomes internal, and keeps them otherwise', () => {
		const vm = view({
			configuration: {
				character: { source: { value: 'internal' }, register: { value: '7' }, schema: { value: '11' } },
				event: { source: { value: 'openregister' }, register: { value: '7' }, schema: { value: '12' } },
			},
		})
		vm.handleSourceChange('character')
		vm.handleSourceChange('event')
		expect(vm.configuration.character.register).toBeNull()
		expect(vm.configuration.character.schema).toBeNull()
		expect(vm.configuration.event.schema).toEqual({ value: '12' })
	})

	it('clears the schema when the register changes', () => {
		const vm = view({ configuration: { event: { register: { value: '8' }, schema: { value: '12' } } } })
		vm.handleRegisterChange('event')
		expect(vm.configuration.event.schema).toBeNull()
		expect(vm.configuration.event.register).toEqual({ value: '8' })
	})
})

describe('Settings — saving', () => {
	const configuration = {
		character: { source: { value: 'openregister' }, register: { value: '7' }, schema: { value: '11' } },
	}

	it('posts the storage payload and says it saved', async () => {
		globalThis.fetch = vi.fn(async () => answer({}, true))
		const vm = view({ configuration, message: 'old' })

		await vm.saveAll()

		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe('/index.php/apps/larpinq/api/settings')
		expect(init.method).toBe('POST')
		expect(init.headers.requesttoken).toBe('token-1')
		expect(JSON.parse(init.body)).toEqual({ character_source: 'openregister', character_register: '7', character_schema: '11' })
		expect(vm.message).toBe('Settings saved successfully')
		expect(vm.messageType).toBe('success')
		expect(vm.saving).toBe(false)
	})

	it('says it failed on a refused save', async () => {
		globalThis.fetch = vi.fn(async () => answer({}, false))
		const vm = view({ configuration })
		await vm.saveAll()
		expect(vm.message).toBe('Failed to save settings')
		expect(vm.messageType).toBe('error')
	})

	it('shows the error of a failed request', async () => {
		globalThis.fetch = vi.fn(async () => {
			throw new Error('Network down')
		})
		const vm = view({ configuration })
		await vm.saveAll()
		expect(vm.message).toBe('Network down')
		expect(vm.messageType).toBe('error')
		expect(vm.saving).toBe(false)
	})
})

describe('Settings — re-import and configuration load', () => {
	it('says a re-import succeeded and reloads', async () => {
		globalThis.fetch = vi.fn(async () => answer({ objectTypes: [], availableRegisters: [], configuration: {} }))
		const vm = view()
		await vm.onReimported()
		expect(vm.message).toBe('Configuration re-imported successfully')
		expect(vm.messageType).toBe('success')
		expect(globalThis.fetch).toHaveBeenCalledTimes(1)
	})

	it('shows a re-import error, or a generic one', () => {
		const vm = view()
		vm.onReimportError({ message: 'Schema invalid' })
		expect(vm.message).toBe('Schema invalid')
		expect(vm.messageType).toBe('error')
		vm.onReimportError(undefined)
		expect(vm.message).toBe('Re-import failed')
	})

	it('reports a configuration load error from the answer, and a failed request', async () => {
		globalThis.fetch = vi.fn(async () => answer({ error: 'Not allowed' }))
		const vm = view()
		await vm.loadConfiguration()
		expect(vm.configurationResults).toEqual({ error: 'Not allowed' })
		expect(vm.loadingConfiguration).toBe(false)

		globalThis.fetch = vi.fn(async () => {
			throw new Error('offline')
		})
		await vm.loadConfiguration()
		expect(vm.configurationResults).toEqual({ error: 'Failed to load configuration: offline' })
	})

	it('reloads the settings after a successful configuration load', async () => {
		globalThis.fetch = vi.fn()
			.mockResolvedValueOnce(answer({ success: true }))
			.mockResolvedValueOnce(answer({ objectTypes: [], availableRegisters: [], configuration: {} }))
		const vm = view()
		await vm.loadConfiguration()
		expect(vm.configurationResults).toEqual({ success: true })
		expect(globalThis.fetch).toHaveBeenCalledTimes(2)
		expect(vm.loading).toBe(false)
	})
})
