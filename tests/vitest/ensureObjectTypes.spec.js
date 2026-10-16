/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for ensureObjectTypes() in src/store/store.js: a page that reads
 * an object store by type registers the types from the app settings first,
 * and only when one of them is still missing.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const objectStore = {
	registry: {},
	get objectTypes() {
		return Object.keys(this.registry)
	},
	registerObjectType: vi.fn(function(slug, schema, register) {
		this.registry[slug] = { schema, register }
	}),
}
const settingsStore = { fetchSettings: vi.fn() }

vi.mock('../../src/store/modules/object.js', () => ({ useObjectStore: () => ({}) }))
vi.mock('../../src/store/modules/settings.js', () => ({ useSettingsStore: () => settingsStore }))

const { ensureObjectTypes } = await import('../../src/store/store.js')

describe('ensureObjectTypes', () => {
	beforeEach(() => {
		objectStore.registry = {}
		objectStore.registerObjectType.mockClear()
		settingsStore.fetchSettings.mockReset()
		settingsStore.fetchSettings.mockResolvedValue({
			register: '36',
			skill_schema: '1250',
			skill_register: '36',
			character_schema: '1228',
		})
	})

	it('registers the types from the settings when one is missing', async () => {
		await ensureObjectTypes(objectStore, ['skill', 'character'])

		expect(settingsStore.fetchSettings).toHaveBeenCalledTimes(1)
		expect(objectStore.objectTypes).toEqual(expect.arrayContaining(['skill', 'character']))
		expect(objectStore.registry.skill).toEqual({ schema: '1250', register: '36' })
		expect(objectStore.registry.character).toEqual({ schema: '1228', register: '36' })
	})

	it('does not fetch the settings when every type is registered', async () => {
		objectStore.registry = { skill: {}, character: {} }

		await ensureObjectTypes(objectStore, ['skill', 'character'])

		expect(settingsStore.fetchSettings).not.toHaveBeenCalled()
		expect(objectStore.registerObjectType).not.toHaveBeenCalled()
	})

	it('leaves a type unregistered when the settings do not configure it', async () => {
		settingsStore.fetchSettings.mockResolvedValue({ register: '36' })

		await ensureObjectTypes(objectStore, ['skill'])

		expect(objectStore.objectTypes).toEqual([])
	})
})
