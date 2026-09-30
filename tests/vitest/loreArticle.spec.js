/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/loreArticle.js and the lore manifest fragment:
 * the article and sidebar requests (whose rows OpenRegister has already
 * filtered by the read rule), the tree the sidebar renders, and the pages
 * and menu entry (REQ-WLP-001, REQ-WLP-004).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import fragment from '../../src/manifest.d/worlds-lore-pages.json'
import manifest from '../../src/manifest.json'
import {
	buildLoreTree,
	fetchLoreArticle,
	fetchLoreTree,
	lorePageId,
} from '../../src/services/loreArticle.js'

function mockFetch(...responses) {
	const fn = vi.fn()
	for (const { ok = true, status = 200, json = {} } of responses) {
		fn.mockResolvedValueOnce({ ok, status, json: async () => json })
	}
	globalThis.fetch = fn
	return fn
}

describe('buildLoreTree (REQ-WLP-004)', () => {
	it('nests pages under their parent, ordered, and keeps orphans at the top', () => {
		const tree = buildLoreTree([
			{ id: 'war', title: 'The war of the two queens', order: 2 },
			{ id: 'city', title: 'The city of Aldmoor', order: 1 },
			{
				id: 'gate',
				title: 'The fall of the north gate',
				parent: 'war',
				order: 1,
			},
			{
				id: 'lost',
				title: 'Under a page the reader may not see',
				parent: 'secret',
				order: 3,
			},
		])
		expect(tree.map((node) => node.id)).toEqual(['city', 'war', 'lost'])
		expect(tree[1].children.map((node) => node.id)).toEqual(['gate'])
		expect(tree[0].children).toEqual([])
	})

	it('reads the id from @self when the row has none', () => {
		expect(lorePageId({ '@self': { id: 'abc' } })).toBe('abc')
		expect(lorePageId({ id: 'x', '@self': { id: 'abc' } })).toBe('x')
	})

	it('survives a cycle without looping', () => {
		const tree = buildLoreTree([
			{ id: 'a', title: 'A', parent: 'b' },
			{ id: 'b', title: 'B', parent: 'a' },
		])
		expect(tree.map((node) => node.id).sort()).toEqual(['a', 'b'])
	})
})

describe('fetching (REQ-WLP-002, REQ-WLP-004)', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('loads one page from the larpinq register', async () => {
		const fetch = mockFetch({
			json: { id: 'city', title: 'The city of Aldmoor', body: '# Aldmoor' },
		})
		const article = await fetchLoreArticle('city')
		expect(article.title).toBe('The city of Aldmoor')
		expect(fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/openregister/api/objects/larpinq/larping_lore_page/city',
		)
	})

	it('returns null for a page the reader may not see', async () => {
		mockFetch({ ok: false, status: 404 })
		expect(await fetchLoreArticle('ash')).toBeNull()
	})

	it('asks for the pages of the same world only', async () => {
		const fetch = mockFetch({
			json: { results: [{ id: 'city', title: 'The city of Aldmoor' }] },
		})
		const tree = await fetchLoreTree('aldmoor')
		expect(tree.map((node) => node.id)).toEqual(['city'])
		const url = fetch.mock.calls[0][0]
		expect(url).toContain(
			'/index.php/apps/openregister/api/objects/larpinq/larping_lore_page?',
		)
		expect(url).toContain('setting=aldmoor')
	})

	it('has no sidebar when the list fails', async () => {
		mockFetch({ ok: false, status: 500 })
		expect(await fetchLoreTree('aldmoor')).toEqual([])
	})
})

describe('the manifest (REQ-WLP-001)', () => {
	it('adds a Lore index whose rows open the article page', () => {
		const lore = fragment.pages.find((page) => page.id === 'Lore')
		expect(lore.type).toBe('index')
		expect(lore.config.schema).toBe('larping_lore_page')
		expect(lore.config.rowRoute).toBe('LoreArticle')
		const article = fragment.pages.find((page) => page.id === 'LoreArticle')
		expect(article.route).toBe('/lore/:id')
		expect(article.component).toBe('LoreArticle')
	})

	it('lists the lore of a world on the world page', () => {
		const world = manifest.pages.find((page) => page.id === 'SettingDetail')
		const widget = world.config.widgets.find((w) => w.id === 'setting-lore')
		expect(widget.type).toBe('object-list')
		expect(widget.content.schema).toBe('larping_lore_page')
		expect(widget.content.filter).toEqual({ setting: '@objectId' })
		expect(widget.content.rowRoute).toBe('LoreArticle')
		expect(world.config.layout.filter((cell) => cell.widgetId === 'setting-lore')).toHaveLength(1)
	})

	it('puts Lore in the World menu group', () => {
		const entry = fragment.menu.find((item) => item.id === 'Lore')
		expect(entry.route).toBe('Lore')
	})
})

describe('the menu layout (REQ-WLP-001)', () => {
	it('moves Lore into the World group', async () => {
		const layout = await import('../../src/menu-layout.json')
		expect(layout.relocations.Lore).toBe('WorldGroup')
	})
})
