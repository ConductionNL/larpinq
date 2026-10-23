/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/characterPdf.js: the template-list states the
 * download dialog renders, the download URL, and the disabled-until-chosen
 * rule (PDF-044). fetch is mocked; @nextcloud/router + auth are stubs.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	canDownload,
	characterPdfUrl,
	fetchCharacterPdfTemplates,
} from '../../src/services/characterPdf.js'

function mockFetch({ ok = true, status = 200, json = {} } = {}) {
	globalThis.fetch = vi
		.fn()
		.mockResolvedValueOnce({ ok, status, json: async () => json })
}

describe('fetchCharacterPdfTemplates', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('asks larpinq, not the document app, for the templates', async () => {
		mockFetch({ json: { available: true, templates: [] } })
		await fetchCharacterPdfTemplates()
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/larpinq/api/pdf/templates',
		)
	})

	it('returns the templates when the document app is available', async () => {
		mockFetch({
			json: {
				available: true,
				templates: [
					{ id: 't-1', name: 'Character sheet' },
					{ name: 'no id' },
				],
			},
		})
		expect(await fetchCharacterPdfTemplates()).toEqual({
			available: true,
			forbidden: false,
			templates: [{ id: 't-1', name: 'Character sheet' }],
		})
	})

	it('reports unavailable when the document app is missing', async () => {
		mockFetch({ json: { available: false, templates: [] } })
		expect((await fetchCharacterPdfTemplates()).available).toBe(false)
	})

	it('reports forbidden on a 403, so the dialog says who may download', async () => {
		mockFetch({ ok: false, status: 403 })
		expect(await fetchCharacterPdfTemplates()).toEqual({
			available: true,
			forbidden: true,
			templates: [],
		})
	})

	it('reports unavailable on any other failure', async () => {
		mockFetch({ ok: false, status: 500 })
		expect((await fetchCharacterPdfTemplates()).available).toBe(false)
	})
})

describe('characterPdfUrl', () => {
	it('points at the existing download route with both ids encoded', () => {
		expect(characterPdfUrl('abc-1', 'tpl 2')).toBe(
			'/index.php/apps/larpinq/characters/abc-1/download/tpl%202',
		)
	})
})

describe('canDownload (PDF-044)', () => {
	it('is false while the list loads', () => {
		expect(canDownload({ loading: true, templateId: 't-1' })).toBe(false)
	})

	it('is false until a template is chosen', () => {
		expect(canDownload({ loading: false, templateId: null })).toBe(false)
	})

	it('is true once a template is chosen', () => {
		expect(canDownload({ loading: false, templateId: 't-1' })).toBe(true)
	})
})
