/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/dialogs/CharacterPdfDownloadDialog.vue: the templates it
 * offers and the download it opens. Only fetch and window.open are faked; the
 * real characterPdf service runs.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import CharacterPdfDownloadDialog from '../../src/dialogs/CharacterPdfDownloadDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))

/**
 * Mount the dialog over a fetch that answers once.
 *
 * @param {number} status The HTTP status.
 * @param {object} body The JSON body.
 * @return {Promise<object>} The state.
 */
async function mounted(status, body) {
	globalThis.fetch = async () => ({ ok: status < 400, status, json: async () => body })
	const self = CharacterPdfDownloadDialog.data()
	await CharacterPdfDownloadDialog.mounted.call(self)
	return self
}

afterEach(() => {
	delete globalThis.fetch
	delete globalThis.window
})

describe('CharacterPdfDownloadDialog mounted', () => {
	it('offers the templates with an id', async () => {
		const self = await mounted(200, { available: true, templates: [{ id: 't-1', name: 'Sheet' }, { name: 'no id' }] })

		expect(self).toMatchObject({ loading: false, available: true, forbidden: false, templates: [{ id: 't-1', name: 'Sheet' }] })
	})

	it('marks a forbidden read', async () => {
		const self = await mounted(403, {})

		expect(self).toMatchObject({ loading: false, available: true, forbidden: true, templates: [] })
	})

	it('marks the renderer unavailable on any other failure', async () => {
		const self = await mounted(500, {})

		expect(self).toMatchObject({ loading: false, available: false, forbidden: false, templates: [] })
	})
})

describe('CharacterPdfDownloadDialog computed', () => {
	it.each([
		['the prop', { characterId: 'c-1', $route: { params: { id: 'c-2' } } }, 'c-1'],
		['the route', { characterId: '', $route: { params: { id: 'c-2' } } }, 'c-2'],
		['a placeholder skipped', { characterId: '@new', $route: { params: { id: 'c-2' } } }, 'c-2'],
		['nothing', { characterId: '' }, ''],
	])('reads the character id from %s', (name, self, id) => {
		expect(CharacterPdfDownloadDialog.computed.id.call(self)).toBe(id)
	})

	it.each([
		[{ loading: false, selected: { id: 't-1' } }, true],
		[{ loading: true, selected: { id: 't-1' } }, false],
		[{ loading: false, selected: null }, false],
		[{ loading: false, selected: { id: '' } }, false],
	])('%j downloadable: %s', (self, expected) => {
		expect(CharacterPdfDownloadDialog.computed.downloadable.call(self)).toBe(expected)
	})
})

describe('CharacterPdfDownloadDialog download', () => {
	it('opens the PDF in a new tab and closes', () => {
		const open = vi.fn()
		globalThis.window = { open }
		const emitted = []

		CharacterPdfDownloadDialog.methods.download.call({
			downloadable: true,
			id: 'c 1',
			selected: { id: 't/1' },
			$emit: (...args) => emitted.push(args),
		})

		expect(open).toHaveBeenCalledWith('/index.php/apps/larpinq/characters/c%201/download/t%2F1', '_blank', 'noopener')
		expect(emitted).toEqual([['close']])
	})

	it('does nothing while it cannot download', () => {
		const open = vi.fn()
		globalThis.window = { open }
		const emitted = []

		CharacterPdfDownloadDialog.methods.download.call({ downloadable: false, $emit: (...args) => emitted.push(args) })

		expect(open).not.toHaveBeenCalled()
		expect(emitted).toEqual([])
	})
})
