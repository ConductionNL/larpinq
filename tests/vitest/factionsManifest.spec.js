/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of characters-factions-and-relationships: the Factions
 * pages and menu entry, the membership page with its status buttons, and the
 * factions and relationships on the character page (REQ-CFR-001,
 * REQ-CFR-003, REQ-CFR-004, REQ-CFR-005).
 */

import { describe, expect, it } from 'vitest'
import fragment from '../../src/manifest.d/characters-factions-and-relationships.json'
import manifest from '../../src/manifest.json'
import layout from '../../src/menu-layout.json'

const page = (id) => manifest.pages.find((p) => p.id === id)
const fragmentPage = (id) => fragment.pages.find((p) => p.id === id)
const widget = (p, id) => p.config.widgets.find((w) => w.id === id)
const cells = (p, id) => p.config.layout.filter((cell) => cell.widgetId === id)

describe('the Factions pages (REQ-CFR-001)', () => {
	it('lists factions and groups with kind, visibility and world', () => {
		const index = fragmentPage('Factions')
		expect(index.type).toBe('index')
		expect(index.route).toBe('/factions')
		expect(index.config.schema).toBe('larping_faction')
		expect(index.config.columns.map((c) => c.key)).toEqual([
			'name',
			'kind',
			'visibility',
			'setting',
		])
	})

	it('opens a faction with its members, their role and status', () => {
		const detail = fragmentPage('FactionDetail')
		expect(detail.route).toBe('/factions/:id')
		expect(detail.config.schema).toBe('larping_faction')
		const members = widget(detail, 'faction-members')
		expect(members.type).toBe('object-list')
		expect(members.content.schema).toBe('larping_faction_member')
		expect(members.content.filter).toEqual({ faction: '@objectId' })
		expect(members.content.columns.map((c) => c.key)).toEqual([
			'character',
			'role',
			'status',
		])
		expect(cells(detail, 'faction-members')).toHaveLength(1)
		expect(cells(detail, 'faction-data')).toHaveLength(1)
	})

	it('puts Factions in the Characters menu group', () => {
		expect(fragment.menu.find((m) => m.id === 'Factions').route).toBe('Factions')
		expect(layout.relocations.Factions).toBe('CharactersGroup')
	})
})

describe('a membership (REQ-CFR-003, REQ-CFR-004)', () => {
	it('moves its status with the accept, decline, leave and remove buttons', () => {
		const detail = fragmentPage('FactionMemberDetail')
		expect(detail.route).toBe('/faction-members/:id')
		expect(detail.config.schema).toBe('larping_faction_member')
		expect(detail.config.lifecycleActions).toEqual({ field: 'status' })
	})
})

describe('the character page (REQ-CFR-001, REQ-CFR-005)', () => {
	const detail = page('CharacterDetail')

	it('lists the factions and groups of the character by name', () => {
		const factions = widget(detail, 'char-factions')
		expect(factions.type).toBe('object-list')
		expect(factions.content.schema).toBe('larping_faction_member')
		expect(factions.content.filter).toEqual({ character: '@objectId' })
		expect(factions.content.columns.map((c) => c.key)).toEqual([
			'factionName',
			'role',
			'status',
		])
	})

	it('lists the relationships the character names and the ones naming it', () => {
		const own = widget(detail, 'char-relationships')
		expect(own.content.schema).toBe('larping_relationship')
		expect(own.content.filter).toEqual({ from: '@objectId' })
		expect(own.content.columns.map((c) => c.key)).toEqual([
			'to',
			'kind',
			'description',
			'knownTo',
		])
		const others = widget(detail, 'char-named-by-others')
		expect(others.title).toBe('Named by others')
		expect(others.content.filter).toEqual({ to: '@objectId' })
	})

	it('places each list once in the layout', () => {
		for (const id of [
			'char-factions',
			'char-relationships',
			'char-named-by-others',
		]) {
			expect(cells(detail, id)).toHaveLength(1)
		}
	})
})
