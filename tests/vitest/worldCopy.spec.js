/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/worldCopy.js and the manifest wiring of
 * worlds-copy-ruleset (REQ-WCR-001, REQ-WCR-003): the preview states the
 * dialog renders, the copy request and its answers, and the World page
 * header action shown to game masters only.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import manifest from '../../src/manifest.json'
import {
	canCopy,
	copyWorld,
	fetchCopyPreview,
	totalOf,
} from '../../src/services/worldCopy.js'

// registry.js imports .vue files, which this node environment cannot load,
// so the registration is read as source text.
const REGISTRY = fs.readFileSync(
	path.resolve(
		path.dirname(fileURLToPath(import.meta.url)),
		'../../src/registry.js',
	),
	'utf8',
)

const WORLD = '11111111-1111-4111-8111-111111111111'

function mockFetch({ ok = true, status = 200, json = {} } = {}) {
	globalThis.fetch = vi
		.fn()
		.mockResolvedValueOnce({ ok, status, json: async () => json })
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('fetchCopyPreview', () => {
	it('asks larpinq for the counts of this world', async () => {
		mockFetch({ json: { world: { name: 'Aldmoor' }, counts: { skills: 2 } } })
		const state = await fetchCopyPreview(WORLD)
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			`/index.php/apps/larpinq/api/worlds/${WORLD}/copy`,
		)
		expect(state).toEqual({
			status: 'ready',
			worldName: 'Aldmoor',
			counts: { skills: 2 },
			error: '',
		})
	})

	it('says forbidden for a player', async () => {
		mockFetch({ ok: false, status: 403, json: { error: 'no' } })
		expect((await fetchCopyPreview(WORLD)).status).toBe('forbidden')
	})

	it('says too large above the cap, with the reason', async () => {
		mockFetch({ ok: false, status: 422, json: { error: 'more than 2000' } })
		expect(await fetchCopyPreview(WORLD)).toMatchObject({
			status: 'too-large',
			error: 'more than 2000',
		})
	})

	it('says failed on anything else', async () => {
		mockFetch({ ok: false, status: 500, json: {} })
		expect((await fetchCopyPreview(WORLD)).status).toBe('failed')
	})
})

describe('copyWorld', () => {
	it('posts the trimmed name and returns the new world', async () => {
		mockFetch({
			status: 201,
			json: { world: { id: 'new-1', name: 'Aldmoor season 2' }, counts: {} },
		})
		const result = await copyWorld(WORLD, '  Aldmoor season 2 ')
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(`/index.php/apps/larpinq/api/worlds/${WORLD}/copy`)
		expect(init.method).toBe('POST')
		expect(JSON.parse(init.body)).toEqual({ name: 'Aldmoor season 2' })
		expect(result).toEqual({
			ok: true,
			worldId: 'new-1',
			error: '',
			leftovers: [],
		})
	})

	it('returns the reason and the leftovers when the copy fails', async () => {
		mockFetch({
			ok: false,
			status: 500,
			json: {
				error: 'Copying skill "Swordsmanship" failed',
				leftovers: ['x'],
			},
		})
		expect(await copyWorld(WORLD, 'Broken')).toEqual({
			ok: false,
			worldId: '',
			error: 'Copying skill "Swordsmanship" failed',
			leftovers: ['x'],
		})
	})
})

describe('canCopy and totalOf', () => {
	it('needs a loaded preview, a name and no copy running', () => {
		expect(canCopy({ status: 'ready', name: 'x', busy: false })).toBe(true)
		expect(canCopy({ status: 'ready', name: '   ', busy: false })).toBe(false)
		expect(canCopy({ status: 'loading', name: 'x', busy: false })).toBe(false)
		expect(canCopy({ status: 'ready', name: 'x', busy: true })).toBe(false)
	})

	it('adds up the counts', () => {
		expect(totalOf({ abilities: 2, skills: 3, lorePages: 1 })).toBe(6)
		expect(totalOf(null)).toBe(0)
	})
})

describe('the World page action', () => {
	const page = manifest.pages.find((p) => p.id === 'SettingDetail')
	const action = (page.config.headerActions || []).find(
		(a) => a.id === 'copy-world',
	)

	it('opens the copy dialog', () => {
		expect(action).toMatchObject({
			type: 'open-modal',
			target: 'CopyWorldDialog',
			label: 'Copy world',
		})
		expect(REGISTRY).toMatch(
			/CopyWorldDialog: \{\s*kind: 'modal',\s*component: CopyWorldDialog,/,
		)
		expect(REGISTRY).toContain(
			"import CopyWorldDialog from './dialogs/CopyWorldDialog.vue'",
		)
	})

	it('is shown to game masters only', () => {
		expect(action.visibleWhen).toEqual({
			endpoint: '/apps/larpinq/api/worlds/copy-access',
			field: 'allowed',
			op: 'eq',
			value: true,
		})
	})
})
