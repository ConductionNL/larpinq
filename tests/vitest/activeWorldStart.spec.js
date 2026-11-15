/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * startActiveWorld() seeds the app workspace from the world the page was
 * served with, and the stored-world reads fall back to "all worlds" on any
 * unusable answer.
 *
 * @spec openspec/specs/setting-management/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	activeWorld,
	appWorkspace,
	listWorlds,
	resolveWorld,
	startActiveWorld,
	WORLD_KEY,
} from '../../src/services/activeWorld.js'

const realFetch = globalThis.fetch

afterEach(() => {
	globalThis.fetch = realFetch
	startActiveWorld('')
})

describe('startActiveWorld', () => {
	it('returns the app workspace narrowed to the served world, and resets the check', () => {
		activeWorld.checked = true
		const before = appWorkspace.value

		const workspace = startActiveWorld('world-1')

		expect(workspace).toBe(appWorkspace)
		expect(workspace.value).toEqual({ [WORLD_KEY]: 'world-1' })
		expect(workspace.value).not.toBe(before)
		expect(activeWorld.id).toBe('world-1')
		expect(activeWorld.checked).toBe(false)
	})

	it('drops the world filter for an empty or non-string world', () => {
		startActiveWorld('world-1')

		for (const served of ['', null, undefined, 42, { id: 'x' }]) {
			startActiveWorld(served)

			expect(activeWorld.id).toBe('')
			expect(appWorkspace.value).toEqual({})
		}
	})

	it('keeps other workspace keys when the world changes', () => {
		appWorkspace.value = { other: 'kept' }

		startActiveWorld('world-2')

		expect(appWorkspace.value).toEqual({ other: 'kept', [WORLD_KEY]: 'world-2' })
	})
})

describe('the stored world falls back to all worlds', () => {
	it('when the world answer is not JSON, clearing the preference', async () => {
		const fetch = vi.fn()
			.mockResolvedValueOnce({ ok: true, json: () => Promise.reject(new SyntaxError('html')) })
			.mockResolvedValueOnce({ ok: true })
		globalThis.fetch = fetch

		expect(await resolveWorld('w 1')).toBe('')
		expect(fetch.mock.calls[0][0]).toBe('/index.php/apps/openregister/api/objects/larpinq/setting/w%201')
		expect(fetch.mock.calls[1][1].method).toBe('PUT')
		expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ value: '' })
	})

	it('when the world answer is empty', async () => {
		globalThis.fetch = vi.fn()
			.mockResolvedValueOnce({ ok: true, json: () => Promise.resolve(null) })
			.mockResolvedValueOnce({ ok: true })

		expect(await resolveWorld('w1')).toBe('')
	})
})

describe('listWorlds', () => {
	it('lists nothing when the worlds cannot be read', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, json: () => Promise.reject(new Error('never read')) })

		expect(await listWorlds()).toEqual([])
	})

	it('lists nothing for an answer without results', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ results: 'nope' }) })

		expect(await listWorlds()).toEqual([])
	})

	it('skips worlds without an id and labels unnamed worlds by id', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve({ results: [null, { name: 'No id' }, { id: 7 }, { id: 'a', name: 'Bravo' }] }),
		})

		expect(await listWorlds()).toEqual([
			{ id: '7', label: '7' },
			{ id: 'a', label: 'Bravo' },
		])
	})
})
