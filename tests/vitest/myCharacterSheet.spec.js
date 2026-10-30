/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Board LrTelefoon: the player's own character sheet, first in the menu.
 *
 * @spec openspec/specs/character-management/spec.md#requirement-a-player-opens-their-own-character-sheet-from-the-menu
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => JSON.parse(fs.readFileSync(path.join(ROOT, file), 'utf8'))
const fragment = read('src/manifest.d/characters-my-character-sheet.json')
const nl = read('l10n/nl.json').translations

describe('my character sheet', () => {
	const page = fragment.pages.find((entry) => entry.id === 'MyCharacters')

	it('lists only the signed-in player\'s characters, as cards', () => {
		expect(page.config.schema).toBe('character')
		expect(page.config.filter).toEqual({ ownerUid: '@me' })
		expect(page.config.viewMode).toBe('cards')
	})

	it('opens the character page from a card', () => {
		expect(page.config.actions[0].route).toBe('CharacterDetail')
	})

	it('sits right after the dashboard in the menu, in Dutch', () => {
		const entry = fragment.menu.find((item) => item.id === 'MyCharacters')
		expect(entry.route).toBe('MyCharacters')
		expect(entry.order).toBeGreaterThan(10)
		expect(entry.order).toBeLessThan(20)
		expect(nl['My character sheet']).toBe('Mijn karakterblad')
	})
})
