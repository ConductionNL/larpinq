/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of worlds-maps: the Maps index and menu entry, the map
 * page with its pins, and the maps on the world page (REQ-WMP-001,
 * REQ-WMP-002).
 */

import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import en from '../../l10n/en.json'
import nl from '../../l10n/nl.json'
import mock from '../../lib/Settings/larpinq_mock_register.json'
import register from '../../lib/Settings/register.d/worlds-maps.json'
import fragment from '../../src/manifest.d/worlds-maps.json'
import manifest from '../../src/manifest.json'

// icons.js imports .vue files, which this node environment cannot load, so it
// is read as source text.
const ICONS = fs.readFileSync(path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../src/icons.js'), 'utf8')

const page = (id) => manifest.pages.find((p) => p.id === id)
const fragmentPage = (id) => fragment.pages.find((p) => p.id === id)
const widget = (p, id) => p.config.widgets.find((w) => w.id === id)
const cells = (p, id) => p.config.layout.filter((cell) => cell.widgetId === id)
function schemaBySlug(slug) {
	return Object.values(register.components.schemas).find((s) => s.slug === slug)
}

describe('the Maps index (REQ-WMP-001)', () => {
	const index = fragmentPage('Maps')

	it('lists the maps of the larpinq register by title, world and visibility', () => {
		expect(index.type).toBe('index')
		expect(index.route).toBe('/maps')
		expect(index.config.register).toBe('larpinq')
		expect(index.config.schema).toBe('larping_world_map')
		expect(index.config.columns.map((c) => c.key)).toEqual([
			'title',
			'setting',
			'visibility',
		])
	})

	it('allows export but no mass import of maps', () => {
		expect(index.config.allowExport).toBe(true)
		expect(index.config.showMassImport).toBe(false)
	})

	it('shows only columns the map schema has', () => {
		const properties = Object.keys(schemaBySlug('larping_world_map').properties)
		for (const column of index.config.columns) {
			expect(properties).toContain(column.key)
		}
	})

	it('has a Maps menu entry that routes to the index', () => {
		const entry = fragment.menu.find((m) => m.id === 'Maps')
		expect(entry.route).toBe('Maps')
		expect(entry.label).toBe('Maps')
		expect(entry.icon).toBe('MapOutline')
		expect(Number.isInteger(entry.order)).toBe(true)
	})
})

describe('the map page (REQ-WMP-002)', () => {
	const detail = fragmentPage('MapDetail')

	it('opens one map of the larpinq register', () => {
		expect(detail.type).toBe('detail')
		expect(detail.route).toBe('/maps/:id/details')
		expect(detail.config.register).toBe('larpinq')
		expect(detail.config.schema).toBe('larping_world_map')
	})

	it('shows the map data in two columns', () => {
		const data = widget(detail, 'map-data')
		expect(data.type).toBe('data')
		expect(data.content.columns).toBe(2)
	})

	it('lists the pins of this map only, with label, kind, position and visibility', () => {
		const pins = widget(detail, 'map-pins')
		expect(pins.type).toBe('object-list')
		expect(pins.content.register).toBe('larpinq')
		expect(pins.content.schema).toBe('larping_map_pin')
		expect(pins.content.filter).toEqual({ map: '@objectId' })
		expect(pins.content.columns.map((c) => c.key)).toEqual([
			'label',
			'kind',
			'x',
			'y',
			'visibility',
		])
	})

	it('shows only pin columns the pin schema has', () => {
		const properties = Object.keys(schemaBySlug('larping_map_pin').properties)
		for (const column of widget(detail, 'map-pins').content.columns) {
			expect(properties).toContain(column.key)
		}
	})

	it('places each widget once: the map data, the map under it, the pins below the map', () => {
		const [data] = cells(detail, 'map-data')
		const [view] = cells(detail, 'map-view')
		const [pins] = cells(detail, 'map-pins')
		expect(cells(detail, 'map-data')).toHaveLength(1)
		expect(cells(detail, 'map-view')).toHaveLength(1)
		expect(cells(detail, 'map-pins')).toHaveLength(1)
		expect(view.gridY).toBe(data.gridY + data.gridHeight)
		expect(pins.gridY).toBe(view.gridY + view.gridHeight)
		for (const cell of [data, view, pins]) {
			expect(cell.gridWidth).toBe(12)
		}
	})

	// tasks.md 2.2: the map's own picture from the record, pins in pixels, a pin opens its lore page.
	it('draws the map object\'s picture with its pins (REQ-WMP-002)', () => {
		const view = widget(detail, 'map-view')
		expect(view.type).toBe('map')
		expect(view.content.layers).toEqual([
			{ type: 'image', url: '@object.image', width: '@object.width', height: '@object.height' },
		])
		const map = schemaBySlug('larping_world_map').properties
		for (const field of ['image', 'width', 'height']) {
			expect(Object.keys(map)).toContain(field)
		}
		const markers = view.content.markers
		expect(markers.dataSource).toEqual({ register: 'larpinq', schema: 'larping_map_pin', filter: { map: '@objectId' } })
		expect([markers.xField, markers.yField, markers.popupField]).toEqual(['x', 'y', 'label'])
		const pin = Object.keys(schemaBySlug('larping_map_pin').properties)
		for (const field of ['x', 'y', 'label', 'lorePage']) {
			expect(pin).toContain(field)
		}
		expect(markers.detailLink).toBe('/index.php/apps/larpinq/lore/{lorePage}')
	})

	it('only lays out widgets the page declares', () => {
		const ids = detail.config.widgets.map((w) => w.id)
		for (const cell of detail.config.layout) {
			expect(ids).toContain(cell.widgetId)
		}
	})
})

describe('the world page (REQ-WMP-001)', () => {
	const detail = page('SettingDetail')

	it('lists the maps of the world and opens a row on the map page', () => {
		const maps = widget(detail, 'setting-maps')
		expect(maps.type).toBe('object-list')
		expect(maps.title).toBe('Maps of this world')
		expect(maps.content.schema).toBe('larping_world_map')
		expect(maps.content.filter).toEqual({ setting: '@objectId' })
		expect(maps.content.columns.map((c) => c.key)).toEqual([
			'title',
			'visibility',
		])
		expect(maps.content.rowRoute).toBe('MapDetail')
		expect(fragmentPage(maps.content.rowRoute)).toBeDefined()
	})

	it('places the maps list once, below every other widget', () => {
		const [maps] = cells(detail, 'setting-maps')
		expect(cells(detail, 'setting-maps')).toHaveLength(1)
		const others = detail.config.layout.filter((cell) => cell.widgetId !== 'setting-maps')
		for (const cell of others) {
			expect(cell.gridY + cell.gridHeight).toBeLessThanOrEqual(maps.gridY)
		}
	})

	it('gives the maps cell a layout id no other cell uses', () => {
		const ids = detail.config.layout.map((cell) => cell.id)
		expect(new Set(ids).size).toBe(ids.length)
	})
})

describe('the Aldmoor seed against the pages (REQ-WMP-001, REQ-WMP-003)', () => {
	const seed = (slug) => mock.components.objects.filter((o) => o['@self'].schema === slug)
	const pins = seed('larping_map_pin')
	const [map] = seed('larping_world_map')

	it('seeds one map and three pins of the larpinq register', () => {
		expect(seed('larping_world_map')).toHaveLength(1)
		expect(pins).toHaveLength(3)
		for (const object of [map, ...pins]) {
			expect(object['@self'].register).toBe('larpinq')
		}
	})

	it('gives every seeded pin a value in each pin column but none it does not declare', () => {
		const columns = widget(fragmentPage('MapDetail'), 'map-pins').content.columns.map((c) => c.key)
		const properties = Object.keys(schemaBySlug('larping_map_pin').properties)
		for (const pin of pins) {
			for (const key of columns) {
				expect(pin[key]).toBeDefined()
			}
			for (const key of Object.keys(pin).filter((k) => k !== '@self')) {
				expect(properties).toContain(key)
			}
		}
	})

	it('only uses kinds and audiences the pin schema allows', () => {
		const { kind, visibility } = schemaBySlug('larping_map_pin').properties
		for (const pin of pins) {
			expect(kind.enum).toContain(pin.kind)
			expect(visibility.enum).toContain(pin.visibility)
		}
	})

	it('keeps the dangerous grove for game masters on a map players may see', () => {
		expect(map.visibility).toBe('players')
		const grove = pins.find((p) => p.label === 'Ash grove')
		expect(grove.visibility).toBe('gamemasters')
		expect(grove.lorePage).toBeUndefined()
		expect(pins.filter((p) => p.visibility === 'players').map((p) => p.label)).toEqual([
			'Aldmoor city',
			'North gate',
		])
	})
})

describe('the maps pages in the catalogues', () => {
	const columnLabels = (columns) => (Array.isArray(columns) ? columns.map((c) => c.label) : [])
	const labels = new Set(fragment.menu.map((m) => m.label))
	for (const p of fragment.pages) {
		labels.add(p.title)
		columnLabels(p.config.columns).forEach((label) => labels.add(label))
		for (const w of p.config.widgets ?? []) {
			labels.add(w.title)
			columnLabels(w.content.columns).forEach((label) => labels.add(label))
		}
	}

	it('has every title, menu label and column label in the English and Dutch catalogues', () => {
		expect(labels.size).toBeGreaterThan(0)
		for (const label of labels) {
			expect(en.translations[label], label).toBeDefined()
			expect(nl.translations[label], label).toBeTruthy()
		}
	})

	it('has the world page list title in both catalogues', () => {
		const title = widget(page('SettingDetail'), 'setting-maps').title
		expect(en.translations[title]).toBe(title)
		expect(nl.translations[title]).toBeTruthy()
		expect(nl.translations[title]).not.toBe(title)
	})
})

describe('the maps pages icons (REQ-WMP-002)', () => {
	it('registers every widget icon of the map pages, so none falls back to the default', () => {
		const icons = new Set(fragment.pages.flatMap((p) => (p.config?.widgets ?? []).map((w) => w.icon).filter(Boolean)))
		expect(icons.size).toBeGreaterThan(0)
		for (const icon of icons) {
			expect(ICONS).toMatch(new RegExp(`^\\t${icon},$`, 'm'))
		}
	})
})
