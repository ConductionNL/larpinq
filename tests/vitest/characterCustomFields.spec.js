/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/characterCustomFields.js and the manifest
 * wiring of characters-custom-fields (REQ-CCF-001 to REQ-CCF-003): which
 * definitions a character's sheet shows, in which order, what is saved where,
 * and the Extra fields tab on the character page.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import fragment from '../../src/manifest.d/characters-custom-fields.json'
import manifest from '../../src/manifest.json'
import {
	fetchCustomFields,
	inputValue,
	saveCustomFields,
	sheetFrom,
	valuesFrom,
} from '../../src/services/characterCustomFields.js'

const HERE = path.dirname(fileURLToPath(import.meta.url))
const REGISTRY = fs.readFileSync(path.resolve(HERE, '../../src/registry.js'), 'utf8')

const ALDMOOR = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'
const DEFINITIONS = [
	{
		id: 'f3',
		label: 'Scars',
		key: 'scars',
		fieldType: 'number',
		visibility: 'owner',
		order: 3,
		setting: ALDMOOR,
	},
	{
		id: 'f1',
		label: 'Bloodline',
		key: 'bloodline',
		fieldType: 'choice',
		choices: ['human', 'elven', 'dwarven'],
		visibility: 'owner',
		order: 1,
		setting: ALDMOOR,
	},
	{
		id: 'f4',
		label: 'True allegiance',
		key: 'true-allegiance',
		fieldType: 'choice',
		choices: ['crown', 'rebels', 'none'],
		visibility: 'gamemasters',
		order: 4,
		setting: ALDMOOR,
	},
	{
		id: 'f5',
		label: 'Vegetarian',
		key: 'vegetarian',
		fieldType: 'yes-no',
		visibility: 'owner',
		order: 2,
	},
	{
		id: 'f6',
		label: 'Clan',
		key: 'clan',
		fieldType: 'text',
		visibility: 'owner',
		order: 1,
		setting: 'elsewhere',
	},
]
const MIRELA = {
	id: 'mirela',
	setting: ALDMOOR,
	customFields: { bloodline: 'elven', scars: 2, 'old-field': 'kept' },
	customFieldsPrivate: { 'true-allegiance': 'rebels' },
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('sheetFrom (REQ-CCF-001, REQ-CCF-002)', () => {
	it('shows the fields of the character world and the global ones, in order', () => {
		const sheet = sheetFrom(DEFINITIONS, MIRELA)
		expect(sheet.rows.map((row) => row.key)).toEqual([
			'bloodline',
			'vegetarian',
			'scars',
			'true-allegiance',
		])
		expect(sheet.rows[0]).toMatchObject({
			label: 'Bloodline',
			fieldType: 'choice',
			choices: ['human', 'elven', 'dwarven'],
			private: false,
			value: 'elven',
		})
		expect(sheet.rows[3]).toMatchObject({ private: true, value: 'rebels' })
	})

	it('lists values that lost their definition, so nothing disappears silently', () => {
		expect(sheetFrom(DEFINITIONS, MIRELA).orphans).toEqual([
			{ key: 'old-field', value: 'kept' },
		])
	})

	it('a player never gets a private row (REQ-CCF-003)', () => {
		// OpenRegister gives a player neither the private definitions nor the
		// private values, so the sheet has nothing private to show.
		const player = { ...MIRELA }
		delete player.customFieldsPrivate
		const sheet = sheetFrom(
			DEFINITIONS.filter((d) => d.visibility === 'owner'),
			player,
		)
		expect(sheet.rows.some((row) => row.private)).toBe(false)
		expect(sheet.canEditPrivate).toBe(false)
	})
})

describe('valuesFrom and inputValue', () => {
	it('puts each value in the property of its visibility', () => {
		const sheet = sheetFrom(DEFINITIONS, MIRELA)
		const values = valuesFrom(sheet, {
			scars: 3,
			'true-allegiance': 'crown',
			'patron-god': 'ignored, no definition',
		})
		expect(values).toEqual({
			customFields: { bloodline: 'elven', scars: 3, 'old-field': 'kept' },
			customFieldsPrivate: { 'true-allegiance': 'crown' },
		})
	})

	it('leaves the private property out for a player', () => {
		const sheet = sheetFrom(
			DEFINITIONS.filter((d) => d.visibility === 'owner'),
			{ id: 'mirela', setting: ALDMOOR, customFields: {} },
		)
		expect(valuesFrom(sheet, { scars: 1 })).toEqual({
			customFields: { scars: 1 },
		})
	})

	it('turns form input into the stored type', () => {
		expect(inputValue('number', '4')).toBe(4)
		expect(inputValue('number', '')).toBe(null)
		expect(inputValue('number', 'many')).toBe(null)
		expect(inputValue('yes-no', true)).toBe(true)
		expect(inputValue('text', '  The Grey Lady ')).toBe('The Grey Lady')
		expect(inputValue('choice', null)).toBe(null)
	})
})

describe('fetchCustomFields and saveCustomFields', () => {
	it('reads the character and the definitions from OpenRegister', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({ ok: true, json: async () => MIRELA })
			.mockResolvedValueOnce({
				ok: true,
				json: async () => ({ results: DEFINITIONS }),
			})
		const sheet = await fetchCustomFields('mirela')
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/openregister/api/objects/larpinq/character/mirela',
		)
		expect(globalThis.fetch.mock.calls[1][0]).toContain(
			'/index.php/apps/openregister/api/objects/larpinq/larping_character_field?',
		)
		expect(sheet.rows).toHaveLength(4)
	})

	it('is null when the character cannot be read', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValue({ ok: false, status: 404, json: async () => ({}) })
		expect(await fetchCustomFields('gone')).toBe(null)
	})

	it('saves with one PATCH and returns the field errors of a refusal', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: false,
			status: 422,
			json: async () => ({ fields: { scars: 'The value must be a number.' } }),
		})
		const result = await saveCustomFields('mirela', {
			customFields: { scars: 'x' },
		})
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(
			'/index.php/apps/openregister/api/objects/larpinq/character/mirela',
		)
		expect(init.method).toBe('PATCH')
		expect(JSON.parse(init.body)).toEqual({ customFields: { scars: 'x' } })
		expect(result).toEqual({
			ok: false,
			errors: { scars: 'The value must be a number.' },
		})
	})
})

describe('the manifest (REQ-CCF-001, REQ-CCF-002)', () => {
	it('has a Character fields list and detail page', () => {
		const ids = fragment.pages.map((page) => page.id)
		expect(ids).toEqual(['CharacterFields', 'CharacterFieldDetail'])
		expect(fragment.pages[0].config.schema).toBe('larping_character_field')
		expect(fragment.menu[0]).toMatchObject({
			id: 'CharacterFields',
			route: 'CharacterFields',
		})
	})

	it('shows an Extra fields tab on the character page', () => {
		const page = manifest.pages.find((p) => p.id === 'CharacterDetail')
		const tab = page.config.sidebar.tabs.find((t) => t.id === 'extra-fields')
		expect(tab).toMatchObject({
			label: 'Extra fields',
			component: 'CharacterCustomFields',
		})
		expect(REGISTRY).toMatch(
			/CharacterCustomFields: \{ kind: 'section', component: CharacterCustomFields \}/,
		)
	})
})
