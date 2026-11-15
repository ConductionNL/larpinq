/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/dialogs/CopyWorldDialog.vue: the preview it loads, the
 * name it proposes, copying, and opening the new world. Only fetch is faked;
 * the real worldCopy service runs. The service itself is pinned in
 * worldCopy.spec.js.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import CopyWorldDialog from '../../src/dialogs/CopyWorldDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

const COPY = '/index.php/apps/larpinq/api/worlds/w-1/copy'

/**
 * Fake fetch answering each request in turn.
 *
 * @param {Array<{status: number, body: object}>} answers The answers, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({ url, method: options.method ?? 'GET', body: options.body ? JSON.parse(options.body) : undefined })
		const answer = answers.shift()
		return { ok: answer.status < 400, status: answer.status, json: async () => answer.body }
	}
	return calls
}

afterEach(() => {
	delete globalThis.fetch
})

describe('CopyWorldDialog mounted', () => {
	it('loads the preview and proposes a copy name', async () => {
		const calls = fakeFetch([{ status: 200, body: { world: { name: 'Aldmoor' }, counts: { skills: 12 } } }])
		const self = { ...CopyWorldDialog.data(), id: 'w-1' }

		await CopyWorldDialog.mounted.call(self)

		expect(calls).toEqual([{ url: COPY, method: 'GET', body: undefined }])
		expect(self).toMatchObject({ status: 'ready', worldName: 'Aldmoor', counts: { skills: 12 }, error: '', name: 'Aldmoor (copy)' })
	})

	it('proposes no name for a world too large to copy', async () => {
		fakeFetch([{ status: 422, body: { error: 'Too many objects.' } }])
		const self = { ...CopyWorldDialog.data(), id: 'w-1' }

		await CopyWorldDialog.mounted.call(self)

		expect(self).toMatchObject({ status: 'too-large', error: 'Too many objects.', name: '' })
	})
})

describe('CopyWorldDialog computed', () => {
	it.each([
		['the prop', { worldId: 'w-1', $route: { params: { id: 'w-2' } } }, 'w-1'],
		['the route', { worldId: '', $route: { params: { id: 'w-2' } } }, 'w-2'],
		['a placeholder skipped', { worldId: '@new' }, ''],
	])('reads the world id from %s', (name, self, id) => {
		expect(CopyWorldDialog.computed.id.call(self)).toBe(id)
	})

	it('lists every kind with its count, zero when absent', () => {
		const rows = CopyWorldDialog.computed.rows.call({ counts: { skills: 12, lorePages: '3' } })

		expect(rows.map((row) => [row.key, row.count])).toEqual([
			['abilities', 0],
			['effects', 0],
			['skills', 12],
			['items', 0],
			['conditions', 0],
			['lorePages', 3],
			['characterFields', 0],
		])
		expect(rows[5].label).toBe('Lore pages')
	})

	it.each([
		[{ status: 'ready', name: 'Copy', busy: false }, true],
		[{ status: 'ready', name: '  ', busy: false }, false],
		[{ status: 'ready', name: 'Copy', busy: true }, false],
		[{ status: 'failed', name: 'Copy', busy: false }, false],
	])('%j copyable: %s', (self, expected) => {
		expect(CopyWorldDialog.computed.copyable.call(self)).toBe(expected)
	})
})

describe('CopyWorldDialog copy', () => {
	it('copies under the trimmed name and keeps the new world id', async () => {
		const calls = fakeFetch([{ status: 201, body: { world: { id: 'w-9' } } }])
		const self = { ...CopyWorldDialog.data(), copyable: true, id: 'w-1', name: ' New Aldmoor ', copyError: 'old', leftovers: ['x'] }

		await CopyWorldDialog.methods.copy.call(self)

		expect(calls).toEqual([{ url: COPY, method: 'POST', body: { name: 'New Aldmoor' } }])
		expect(self).toMatchObject({ busy: false, newWorldId: 'w-9', copyError: '', leftovers: [] })
	})

	it('shows the error and the leftovers of a failed copy', async () => {
		fakeFetch([{ status: 500, body: { error: 'Copy stopped.', leftovers: ['skill-3'] } }])
		const self = { ...CopyWorldDialog.data(), copyable: true, id: 'w-1', name: 'Copy' }

		await CopyWorldDialog.methods.copy.call(self)

		expect(self).toMatchObject({ busy: false, newWorldId: '', copyError: 'Copy stopped.', leftovers: ['skill-3'] })
	})

	it('falls back to a generic error', async () => {
		fakeFetch([{ status: 500, body: {} }])
		const self = { ...CopyWorldDialog.data(), copyable: true, id: 'w-1', name: 'Copy' }

		await CopyWorldDialog.methods.copy.call(self)

		expect(self.copyError).toBe('unknown error')
	})

	it('does nothing while it cannot copy', async () => {
		const calls = fakeFetch([])
		const self = { ...CopyWorldDialog.data(), copyable: false }

		await CopyWorldDialog.methods.copy.call(self)

		expect(calls).toEqual([])
		expect(self.busy).toBe(false)
	})

	it('opens the new world and closes', () => {
		const push = vi.fn()
		const emitted = []

		CopyWorldDialog.methods.openNewWorld.call({ newWorldId: 'w 9', $router: { push }, $emit: (...args) => emitted.push(args) })

		expect(push).toHaveBeenCalledWith('/settings/w%209')
		expect(emitted).toEqual([['close']])
	})
})
