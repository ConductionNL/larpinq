/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: export every list, import characters and players, and move a
 * campaign as one workbook (admin-import-export).
 *
 * Runs as the admin the fixtures authenticate as: the list export with a
 * filter, a two-row players CSV through the register import the Players page
 * uses, the Import action only on Characters and Players, and the campaign
 * workbook from the register export.
 *
 * @spec openspec/specs/data-portability/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	REGISTER_ID,
	resolveSchemaIds,
} from './fixtures.ts'

const OR_API = `${BASE_URL}/index.php/apps/openregister/api`

let api: APIRequestContext
const ledger = new FixtureLedger()
let settingId = ''
let characterName = ''

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)
	settingId = ledger.track(
		'setting',
		await createObject(api, 'setting', { name: fixtureName('Aldmoor') }),
	)
	characterName = fixtureName('Brannoc')
	ledger.track(
		'character',
		await createObject(api, 'character', {
			name: characterName,
			ocName: 'Brannoc',
			setting: settingId,
		}),
	)
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

test.describe('data portability', () => {
	// @e2e openspec/specs/data-portability/spec.md#a-game-master-exports-the-participants
	test('the characters list exports its filter to CSV', async ({ page }) => {
		const res = await api.get(
			`${OR_API}/objects/larpinq/character/export?format=csv&setting=${settingId}`,
		)
		expect(res.status()).toBe(200)
		expect(res.headers()['content-type']).toContain('text/csv')
		expect(await res.text()).toContain(characterName)

		await page.goto(`${BASE_URL}/index.php${BASE}/characters`)
		await expect(page.getByTestId('cn-index-export-menu')).toBeVisible()
	})

	// @e2e openspec/specs/data-portability/spec.md#moving-from-a-spreadsheet
	test('two players import from a CSV', async () => {
		const names = [fixtureName('Anna'), fixtureName('Bram')]
		const csv = `name,description\n${names[0]},Imported\n${names[1]},Imported\n`
		const res = await api.post(
			`${OR_API}/registers/larpinq/import?schema=player`,
			{
				headers: { 'OCS-APIRequest': 'true' },
				multipart: {
					schema: 'player',
					file: { name: 'players.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) },
				},
			},
		)
		expect(res.status()).toBe(200)
		const summary = (await res.json())?.summary ?? {}
		const sheets = Object.values(summary) as Array<Record<string, unknown>>
		const count = (key: string) => sheets.reduce((n, s) => n + (Array.isArray(s[key]) ? (s[key] as unknown[]).length : Number(s[key]) || 0), 0)
		expect(count('created')).toBe(2)
		expect(count('errors')).toBe(0)
		for (const sheet of sheets) {
			for (const row of (sheet.created as Array<Record<string, unknown>>) ?? []) {
				const id = (row?.['@self'] as Record<string, string>)?.id ?? (row?.id as string)
				if (id) {
					ledger.track('player', id)
				}
			}
		}
	})

	// @e2e openspec/specs/data-portability/spec.md#only-characters-and-players-import
	test('only characters and players offer an import', async ({ page }) => {
		for (const [route, offered] of [['players', true], ['skills', false], ['events', false]] as const) {
			await page.goto(`${BASE_URL}/index.php${BASE}/${route}`)
			await page.getByRole('button', { name: 'Actions' }).first().click()
			await expect(page.getByRole('menuitem', { name: 'Import' })).toHaveCount(offered ? 1 : 0)
			await page.keyboard.press('Escape')
		}
	})

	// @e2e openspec/specs/data-portability/spec.md#a-backup-before-the-season
	test('the campaign exports as one workbook', async ({ page }) => {
		const res = await api.get(`${OR_API}/registers/${REGISTER_ID}/export?format=excel`)
		expect(res.status()).toBe(200)
		expect(res.headers()['content-type']).toContain('spreadsheetml')
		expect((await res.body()).subarray(0, 2).toString()).toBe('PK')

		await page.goto(`${BASE_URL}/index.php${BASE}/settings`)
		await expect(page.getByText('Export campaign')).toBeVisible()
	})
})
