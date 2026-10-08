/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/activeWorld.js and the manifest wiring of
 * events-world-scope-and-upcoming: the active-world lens on the lists and the
 * dashboard (setting-management, "A per-user active setting MUST filter lists
 * server-side"; REQ-EWU-003), and upcoming events first (REQ-EWU-002).
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import manifest from '../../src/manifest.json'
import {
	applyWorld,
	listWorlds,
	resolveWorld,
	saveWorld,
	WORLD_FILTER,
	WORLD_KEY,
	WORLD_SCOPED_PAGES,
} from '../../src/services/activeWorld.js'

// registry.js imports .vue files, which this node environment cannot load,
// so the registration is read as source text.
const REGISTRY = fs.readFileSync(
	path.resolve(
		path.dirname(fileURLToPath(import.meta.url)),
		'../../src/registry.js',
	),
	'utf8',
)

const ALDMOOR = '11111111-1111-4111-8111-111111111111'
const pages = Object.fromEntries(manifest.pages.map((p) => [p.id, p]))
const widget = (id) => pages.Dashboard.config.widgets.find((w) => w.id === id)

function mockFetch(...answers) {
	const fn = vi.fn()
	for (const { ok = true, status = 200, json = {} } of answers) {
		fn.mockResolvedValueOnce({ ok, status, json: async () => json })
	}
	globalThis.fetch = fn
	return fn
}

afterEach(() => {
	delete globalThis.fetch
})

describe('the active-world lens on the lists', () => {
	it('names the seven world-scoped index pages', () => {
		expect(WORLD_SCOPED_PAGES).toEqual([
			'Characters',
			'Abilities',
			'Skills',
			'Items',
			'Conditions',
			'Effects',
			'Events',
		])
	})

	it('narrows every world-scoped index page in its list query and shows the switcher', () => {
		for (const id of WORLD_SCOPED_PAGES) {
			expect(pages[id].config.filter?.setting, id).toBe(WORLD_FILTER)
			expect(pages[id].headerComponent, id).toBe('WorldSwitcher')
		}
		expect(WORLD_FILTER).toBe(`@workspace.${WORLD_KEY}?`)
	})

	it('narrows the dashboard counts and lists of world-scoped schemas', () => {
		for (const id of ['kpi-characters', 'kpi-events', 'kpi-items']) {
			expect(widget(id).content.source.filter.setting, id).toBe(WORLD_FILTER)
		}
		for (const id of ['recent-characters', 'upcoming-events']) {
			expect(widget(id).content.filter.setting, id).toBe(WORLD_FILTER)
		}
		expect(pages.Dashboard.actionsComponent).toBe('WorldSwitcherActions')
	})

	it('registers the switcher as a header and as dashboard actions', () => {
		expect(REGISTRY).toMatch(/WorldSwitcher: \{\s*kind: 'header'/)
		expect(REGISTRY).toMatch(/WorldSwitcherActions: \{\s*kind: 'actions'/)
	})
})

describe('upcoming events first', () => {
	it('opens the Events list on upcoming events, soonest first, with past events a tab away', () => {
		const events = pages.Events.config
		expect(events.sortKey).toBe('startDate')
		expect(events.sortOrder).toBe('asc')
		const [upcoming, past] = events.quickFilters
		expect(upcoming.default).toBe(true)
		expect(upcoming.filter).toEqual({ 'startDate[gte]': '@today' })
		expect(past.filter).toEqual({ 'startDate[lt]': '@today' })
	})

	it('shows the next six upcoming events on the dashboard', () => {
		const upcoming = widget('upcoming-events')
		expect(widget('recent-events')).toBeUndefined()
		expect(upcoming.title).toBe('Upcoming events')
		expect(upcoming.content.filter['startDate[gte]']).toBe('@today')
		expect(upcoming.content.sort).toEqual({ field: 'startDate', dir: 'asc' })
		expect(upcoming.content.limit).toBe(6)
		const layout = pages.Dashboard.config.layout.map((l) => l.widgetId)
		expect(layout).toContain('upcoming-events')
		expect(layout).not.toContain('recent-events')
	})
})

describe('applyWorld', () => {
	it('writes the world into a workspace ref as a new object, so lists re-fetch', () => {
		const bag = { value: { other: 1 } }
		const before = bag.value
		applyWorld(bag, ALDMOOR)
		expect(bag.value).toEqual({ other: 1, [WORLD_KEY]: ALDMOOR })
		expect(bag.value).not.toBe(before)
	})

	it('removes the key for all worlds, so the optional token drops the filter', () => {
		const bag = { value: { [WORLD_KEY]: ALDMOOR } }
		applyWorld(bag, '')
		expect(bag.value).toEqual({})
	})

	it('ignores a missing bag', () => {
		expect(() => applyWorld(null, ALDMOOR)).not.toThrow()
	})
})

describe('the stored world', () => {
	it('saves the choice through the preferences API', async () => {
		const fetch = mockFetch({ json: { value: ALDMOOR } })
		await saveWorld(ALDMOOR)
		const [url, init] = fetch.mock.calls[0]
		expect(url).toContain('/apps/larpinq/api/preferences/active-world')
		expect(init.method).toBe('PUT')
		expect(JSON.parse(init.body)).toEqual({ value: ALDMOOR })
	})

	it('keeps an active world', async () => {
		mockFetch({ json: { id: ALDMOOR, name: 'Aldmoor', status: 'active' } })
		expect(await resolveWorld(ALDMOOR)).toBe(ALDMOOR)
	})

	it('falls back to all worlds and clears the preference when the world is archived', async () => {
		const fetch = mockFetch(
			{ json: { id: ALDMOOR, name: 'Aldmoor', status: 'archived' } },
			{ json: { value: null } },
		)
		expect(await resolveWorld(ALDMOOR)).toBe('')
		expect(fetch.mock.calls[1][1].method).toBe('PUT')
		expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ value: '' })
	})

	it('falls back to all worlds when the world is gone', async () => {
		mockFetch({ ok: false, status: 404 }, { json: { value: null } })
		expect(await resolveWorld(ALDMOOR)).toBe('')
	})

	it('asks nothing when no world is stored', async () => {
		const fetch = mockFetch()
		expect(await resolveWorld('')).toBe('')
		expect(fetch).not.toHaveBeenCalled()
	})

	it('lists the active worlds by name', async () => {
		const fetch = mockFetch({
			json: {
				results: [
					{ id: 'b', name: 'Outer Rim' },
					{ id: ALDMOOR, name: 'Aldmoor' },
				],
			},
		})
		expect(await listWorlds()).toEqual([
			{ id: ALDMOOR, label: 'Aldmoor' },
			{ id: 'b', label: 'Outer Rim' },
		])
		expect(fetch.mock.calls[0][0]).toContain(
			'/apps/openregister/api/objects/larpinq/setting',
		)
		expect(fetch.mock.calls[0][0]).toContain('status=active')
	})
})
