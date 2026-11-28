/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/characterBulkEdit.js and the manifest wiring of
 * characters-status-and-bulk-edit (REQ-CSB-001, REQ-CSB-003, REQ-CSB-004):
 * one PATCH per selected character, a refusal that does not stop the others
 * and is named with its reason, and the status and bulk action on the pages.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import manifest from '../../src/manifest.json'
import {
	applyBulkEdit,
	bulkBody,
	fetchWorlds,
} from '../../src/services/characterBulkEdit.js'

const here = path.dirname(fileURLToPath(import.meta.url))
// registry.js and icons.js import .vue files, which this node environment
// cannot load, so they are read as source text.
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ICONS = fs.readFileSync(path.resolve(here, '../../src/icons.js'), 'utf8')
const page = (id) => manifest.pages.find((p) => p.id === id)

describe('bulkBody', () => {
	it('sends only the fields with a chosen value', () => {
		expect(bulkBody({ status: 'retired', type: null, setting: '' })).toEqual({
			status: 'retired',
		})
		expect(
			bulkBody({ status: 'dead', type: 'npc', setting: 'w1', name: 'x' }),
		).toEqual({
			status: 'dead',
			type: 'npc',
			setting: 'w1',
		})
	})
})

describe('applyBulkEdit (REQ-CSB-003, REQ-CSB-004)', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('writes each selected character with its own PATCH', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true })
		const progress = []
		const report = await applyBulkEdit(
			['harrow', 'venn', 'tomas'],
			{ status: 'retired' },
			{},
			(count) => progress.push(count),
		)
		expect(report).toEqual({ changed: ['harrow', 'venn', 'tomas'], refused: [] })
		expect(globalThis.fetch).toHaveBeenCalledTimes(3)
		for (const [index, id] of ['harrow', 'venn', 'tomas'].entries()) {
			const [url, init] = globalThis.fetch.mock.calls[index]
			expect(url).toBe(
				`/index.php/apps/openregister/api/objects/larpinq/character/${id}`,
			)
			expect(init.method).toBe('PATCH')
			expect(JSON.parse(init.body)).toEqual({ status: 'retired' })
		}
		expect(progress).toEqual([1, 2, 3])
	})

	it('goes on after a refusal and names the refused character with the reason', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({ ok: true })
			.mockResolvedValueOnce({
				ok: false,
				status: 423,
				json: async () => ({ message: 'Object is locked by another user' }),
			})
			.mockResolvedValueOnce({ ok: true })
		const report = await applyBulkEdit(
			['harrow', 'venn', 'tomas'],
			{ status: 'retired' },
			{ venn: 'Lady Venn' },
		)
		expect(report.changed).toEqual(['harrow', 'tomas'])
		expect(report.refused).toEqual([
			{
				id: 'venn',
				name: 'Lady Venn',
				reason: 'Object is locked by another user',
			},
		])
	})

	it('names the HTTP status when the refusal has no message', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 403,
			json: async () => {
				throw new Error('no body')
			},
		})
		const report = await applyBulkEdit(['x'], { type: 'npc' })
		expect(report.refused).toEqual([{ id: 'x', name: 'x', reason: 'HTTP 403' }])
	})
})

describe('fetchWorlds', () => {
	afterEach(() => {
		delete globalThis.fetch
	})

	it('offers the worlds by name', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			json: async () => ({ results: [{ id: 'w1', name: 'Aldmoor' }] }),
		})
		expect(await fetchWorlds()).toEqual([{ id: 'w1', label: 'Aldmoor' }])
	})
})

describe('the pages (REQ-CSB-001, REQ-CSB-003)', () => {
	it('shows the status as a column on the Characters index', () => {
		const index = page('Characters')
		expect(index.config.columns.map((c) => c.key)).toContain('status')
	})

	it('offers Edit selected on a selection', () => {
		const index = page('Characters')
		expect(index.config.selectable).toBe(true)
		expect(index.config.bulkActions).toEqual([
			{
				id: 'bulk-edit',
				label: 'Edit selected',
				icon: 'PencilOutline',
				handler: 'larpinqBulkEditCharacters',
			},
		])
		expect(REGISTRY).toMatch(
			/larpinqBulkEditCharacters: \{\s*kind: 'handler',\s*handler: bulkEditCharactersAction,?\s*\}/,
		)
		expect(ICONS).toMatch(/^\tPencilOutline,$/m)
	})

	it('shows the status on the character page', () => {
		const progress = page('CharacterDetail').config.widgets.find(
			(w) => w.id === 'char-progress',
		)
		expect(progress.content.include).toContain('status')
	})
})
