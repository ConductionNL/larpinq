/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * players-self-signup REQ-PSS-004: game masters see the self-registered
 * players awaiting review and mark them reviewed.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import fragment from '../../src/manifest.d/players-self-signup.json'
import layout from '../../src/menu-layout.json'
import { markPlayersReviewed } from '../../src/services/playerReview.js'

const here = path.dirname(fileURLToPath(import.meta.url))
// registry.js and icons.js import .vue files, which this node environment
// cannot load, so they are read as source text.
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ICONS = fs.readFileSync(path.resolve(here, '../../src/icons.js'), 'utf8')

describe('the New players page (REQ-PSS-004)', () => {
	const page = fragment.pages.find((p) => p.id === 'NewPlayers')

	it('lists the players awaiting review', () => {
		expect(page.type).toBe('index')
		expect(page.route).toBe('/new-players')
		expect(page.config.schema).toBe('player')
		expect(page.config.filter).toEqual({ awaitingReview: true })
		expect(page.config.columns.map((c) => c.key)).toEqual([
			'name',
			'description',
			'selfRegistered',
		])
	})

	it('marks the selected players reviewed through a registered handler', () => {
		expect(page.config.selectable).toBe(true)
		const action = page.config.bulkActions.find((a) => a.id === 'mark-reviewed')
		expect(action.label).toBe('Mark reviewed')
		expect(REGISTRY).toMatch(/larpinqMarkPlayersReviewed:\s*{\s*kind: 'handler'/)
		expect(ICONS).toMatch(new RegExp(`\\b${action.icon},`))
	})

	it('sits in the Characters menu group with a registered icon', () => {
		const entry = fragment.menu.find((m) => m.id === 'NewPlayers')
		expect(entry.route).toBe('NewPlayers')
		expect(layout.relocations.NewPlayers).toBe('CharactersGroup')
		expect(ICONS).toMatch(new RegExp(`\\b${entry.icon},`))
	})
})

describe('markPlayersReviewed', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('clears awaitingReview on each player and nothing else', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true })
		const report = await markPlayersReviewed(['lotte', 'sam'])
		expect(report).toEqual({ reviewed: ['lotte', 'sam'], refused: [] })
		for (const [index, id] of ['lotte', 'sam'].entries()) {
			const [url, init] = globalThis.fetch.mock.calls[index]
			expect(url).toBe(`/index.php/apps/openregister/api/objects/larpinq/player/${id}`)
			expect(init.method).toBe('PATCH')
			expect(JSON.parse(init.body)).toEqual({ awaitingReview: false })
		}
	})

	it('goes on after a refusal and reports it', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({ ok: false, status: 403 })
			.mockRejectedValueOnce(new Error('offline'))
			.mockResolvedValueOnce({ ok: true })
		const report = await markPlayersReviewed(['a', 'b', 'c'])
		expect(report).toEqual({ reviewed: ['c'], refused: ['a', 'b'] })
	})
})
