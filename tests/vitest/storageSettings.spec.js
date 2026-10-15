/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The storage form lives on the admin page only, and its Save builds the
 * payload REQ-003 describes.
 *
 * The save used to return early on `this.selectedRegister`, a property the
 * component never defined, so "Save All" sent nothing on either page that
 * showed the form. The payload is now a pure function and pinned here.
 *
 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { describe, expect, it } from 'vitest'
import { buildStorageSettingsPayload } from '../../src/services/storageSettingsPayload.js'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8')

describe('storage settings payload', () => {
	it('sends register and schema for an OpenRegister type only', () => {
		const payload = buildStorageSettingsPayload({
			character: {
				source: { value: 'openregister' },
				register: { value: '4' },
				schema: { value: '17' },
			},
			item: { source: { value: 'internal' }, register: null, schema: null },
		})
		expect(payload).toEqual({
			character_source: 'openregister',
			character_register: '4',
			character_schema: '17',
			item_source: 'internal',
		})
	})

	it('treats a type without a source as internal', () => {
		expect(buildStorageSettingsPayload({ skill: {} })).toEqual({ skill_source: 'internal' })
	})

	it('saves through the payload builder, not through an undefined register', () => {
		const settings = read('src/views/settings/Settings.vue')
		expect(settings).toContain('buildStorageSettingsPayload(this.configuration)')
		expect(settings).not.toContain('selectedRegister')
	})
})

describe('storage form placement', () => {
	it('mounts only on the admin page', () => {
		expect(read('src/settings.js')).toContain("from './views/settings/Settings.vue'")
		expect(read('src/registry.js')).not.toContain('views/settings/Settings.vue')
	})

	it('has no in-app page or menu entry', () => {
		const manifest = JSON.parse(read('src/manifest.json'))
		const ids = [...manifest.pages, ...manifest.menu].map((entry) => entry.id)
		expect(ids).not.toContain('GameSettings')
		expect(JSON.parse(read('src/menu-layout.json')).settingsSection).not.toContain('GameSettings')
	})
})

describe('admin page frame (board LrBeheerinstellingen)', () => {
	it('draws the shell in the board look with the path line', () => {
		const settings = read('src/views/settings/Settings.vue')
		expect(settings).toContain('look="board"')
		expect(settings).toContain(':breadcrumbs="breadcrumbs"')
		expect(settings).toContain('reimportUrl="/index.php/apps/larpinq/api/settings/reimport"')
	})

	it('provides the board look, so the version card follows the shell', () => {
		expect(read('src/settings.js')).toContain("app.provide('cnLook', 'board')")
	})
})
