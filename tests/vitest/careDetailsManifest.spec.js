/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of players-care-details-and-erasure: the care details
 * widget on PlayerDetail (REQ-PCE-001) and, on EventDetail, the event's care
 * records with the count of records due for erasure (REQ-PCE-002).
 *
 * @spec openspec/specs/player-care-details/spec.md#requirement-players-keep-care-details-that-only-they-and-game-masters-see-req-pce-001
 * @spec openspec/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import fragment from '../../lib/Settings/register.d/players-care-details-and-erasure.json'
import icons from '../../src/icons.js'
import manifest from '../../src/manifest.json'

const CARE_FIELDS = ['allergies', 'medicalNotes', 'dietaryNeeds', 'emergencyContactName', 'emergencyContactPhone']

const page = (id) => manifest.pages.find((p) => p.id === id)
const widget = (pageId, widgetId) => page(pageId).config.widgets.find((w) => w.id === widgetId)
const placed = (pageId, widgetId) => page(pageId).config.layout.some((cell) => cell.widgetId === widgetId)
const schemas = fragment.components?.schemas ?? fragment
const en = JSON.parse(readFileSync(resolve(__dirname, '../../l10n/en.json'), 'utf8')).translations

describe('the care details on the player page (REQ-PCE-001)', () => {
	const care = widget('PlayerDetail', 'player-care')

	it('shows exactly the five care fields in their own widget', () => {
		expect(care.type).toBe('data')
		expect(care.title).toBe('Care details')
		expect(care.content.include).toEqual(CARE_FIELDS)
		expect(placed('PlayerDetail', 'player-care')).toBe(true)
	})

	it('keeps the care fields out of the identity widget, so they are not shown twice', () => {
		expect(widget('PlayerDetail', 'player-data').content.exclude).toEqual(CARE_FIELDS)
	})

	it('names fields the player schema declares', () => {
		const declared = Object.keys(schemas.player.properties)
		for (const field of CARE_FIELDS) {
			expect(declared).toContain(field)
		}
	})
})

describe('the care list on the event page (REQ-PCE-002)', () => {
	const list = widget('EventDetail', 'event-care')
	const due = widget('EventDetail', 'event-stats-care-due')

	it('lists the care records of this event with the player and the five fields', () => {
		expect(list.type).toBe('object-list')
		expect(list.content.schema).toBe(schemas.eventCareRecord.slug)
		expect(list.content.filter).toEqual({ event: '@objectId' })
		expect(list.content.columns.map((c) => c.key)).toEqual(['player', ...CARE_FIELDS, 'eraseAfter'])
		expect(placed('EventDetail', 'event-care')).toBe(true)
	})

	it('only names columns the care record declares', () => {
		const declared = Object.keys(schemas.eventCareRecord.properties)
		for (const column of list.content.columns) {
			expect(declared).toContain(column.key)
		}
	})

	it('counts the records of this event whose erase date has passed', () => {
		expect(due.type).toBe('stats-block')
		const [entry] = due.content.entries
		expect(entry.schema).toBe(schemas.eventCareRecord.slug)
		expect(entry.metric).toBe('count')
		expect(entry.filter).toEqual({ event: '@objectId', eraseAfter: { lte: '@now' } })
		expect(placed('EventDetail', 'event-stats-care-due')).toBe(true)
	})
})

describe('labels and icons', () => {
	it('registers every icon the care widgets name', () => {
		for (const w of [widget('PlayerDetail', 'player-care'), widget('EventDetail', 'event-care'), widget('EventDetail', 'event-stats-care-due')]) {
			expect(icons[w.icon]).toBeDefined()
		}
	})

	it('has an English source string for every new label', () => {
		for (const label of ['Care details', 'Due for erasure', 'No care records yet.']) {
			expect(en[label]).toBe(label)
		}
	})
})
