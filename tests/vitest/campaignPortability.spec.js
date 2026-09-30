/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/campaignPortability.js and the manifest wiring
 * of admin-import-export: every index page exports, only Characters and
 * Players import, only administrators see an import, and the Worlds page
 * carries the campaign actions (REQ-AIE-001..003).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import castFragment from '../../src/manifest.d/characters-player-visibility.json'
import manifest from '../../src/manifest.json'
import {
	applyImportGate,
	CAMPAIGN_IMPORT_ACTION,
	campaignExportUrl,
	canImport,
	isGameMaster,
	importCampaign,
	resolveRegisterId,
	summariseImport,
} from '../../src/services/campaignPortability.js'

const indexPages = [...manifest.pages, ...castFragment.pages].filter(
	(page) => page.type === 'index' && page.config?.register,
)

describe('the manifest (REQ-AIE-001, REQ-AIE-002)', () => {
	it('offers the export menu on every register index page', () => {
		expect(indexPages.length).toBeGreaterThanOrEqual(11)
		for (const page of indexPages) {
			expect(page.config.allowExport, page.id).toBe(true)
		}
	})

	it('offers import on Characters and Players only', () => {
		const importing = indexPages
			.filter((page) => page.config.showMassImport !== false)
			.map((page) => page.id)
		expect(importing.sort()).toEqual(['Characters', 'Players'])
	})

	it('puts the campaign export and import on the Worlds page', () => {
		const settings = manifest.pages.find((page) => page.id === 'Settings')
		const ids = (settings.config.headerActions || []).map((action) => [
			action.id,
			action.handler,
		])
		expect(ids).toEqual([
			['export-campaign', 'larpinqExportCampaign'],
			[CAMPAIGN_IMPORT_ACTION, 'larpinqImportCampaign'],
		])
	})
})

describe('applyImportGate (REQ-AIE-002)', () => {
	it('leaves the manifest alone for someone who may import', () => {
		const gated = applyImportGate(manifest, true)
		const players = gated.pages.find((page) => page.id === 'Players')
		expect(players.config.showMassImport).not.toBe(false)
		const settings = gated.pages.find((page) => page.id === 'Settings')
		expect(settings.config.headerActions.map((a) => a.id)).toContain(
			CAMPAIGN_IMPORT_ACTION,
		)
	})

	it('removes every import for someone who may not, and keeps the exports', () => {
		const gated = applyImportGate(manifest, false)
		for (const page of gated.pages.filter(
			(p) => p.type === 'index' && p.config?.register,
		)) {
			expect(page.config.showMassImport, page.id).toBe(false)
			expect(page.config.allowExport, page.id).toBe(true)
		}
		const settings = gated.pages.find((page) => page.id === 'Settings')
		expect(settings.config.headerActions.map((a) => a.id)).toEqual([
			'export-campaign',
		])
		// The bundled manifest itself is not mutated.
		expect(
			manifest.pages.find((page) => page.id === 'Players').config
				.showMassImport,
		).not.toBe(false)
	})

	it('lets administrators and game masters import, as OpenRegister does', () => {
		expect(canImport({ uid: 'gm', isAdmin: false }, true)).toBe(true)
		expect(canImport({ uid: 'admin', isAdmin: true }, false)).toBe(true)
		expect(canImport({ uid: 'anna', isAdmin: false }, false)).toBe(false)
		expect(canImport(null, false)).toBe(false)
	})

	it('reads the game master flag the page provides, and is false without it', () => {
		expect(isGameMaster()).toBe(false)
	})
})

describe('the campaign (REQ-AIE-003)', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('finds the numeric register id in the larpinq settings', () => {
		expect(resolveRegisterId({ setting_register: '7', register: '3' })).toBe('7')
		expect(resolveRegisterId({ register: 3 })).toBe('3')
		expect(resolveRegisterId({})).toBeNull()
		expect(resolveRegisterId(null)).toBeNull()
	})

	it('exports the whole register as one Excel workbook', () => {
		expect(campaignExportUrl('7')).toBe(
			'/index.php/apps/openregister/api/registers/7/export?format=excel',
		)
	})

	it('posts the workbook to the register import as Excel and counts the result', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => ({
				summary: {
					character: {
						created: [{}, {}],
						updated: [{}],
						unchanged: [],
						errors: [],
					},
					player: {
						created: [{}],
						updated: [],
						unchanged: [{}],
						errors: [{ row: 3 }],
					},
				},
			}),
		})
		const file = new Blob(['x'], {
			type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		})

		const totals = await importCampaign(file, '7')

		expect(totals).toEqual({ created: 3, updated: 1, unchanged: 1, failed: 1 })
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(
			'/index.php/apps/openregister/api/registers/7/import?type=excel',
		)
		expect(init.method).toBe('POST')
		expect(init.body.get('file')).toBeTruthy()
		expect(init.headers.requesttoken).toBe('test-token')
	})

	it("throws with OpenRegister's reason when the import is refused", async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: false,
			status: 403,
			json: async () => ({
				error: 'User does not have permission to manage this register',
			}),
		})
		await expect(importCampaign(new Blob(['x']), '7')).rejects.toThrow(
			'User does not have permission to manage this register',
		)
	})

	it('reads counts whether OpenRegister sends lists or numbers', () => {
		expect(
			summariseImport({
				a: { created: 2, updated: [], unchanged: 0, errors: 1 },
			}),
		).toEqual({ created: 2, updated: 0, unchanged: 0, failed: 1 })
		expect(summariseImport(undefined)).toEqual({
			created: 0,
			updated: 0,
			unchanged: 0,
			failed: 0,
		})
	})
})
