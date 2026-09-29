/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: Download as PDF from the character detail page.
 *
 * The action's visibility follows GET /apps/larpinq/api/pdf/templates. With
 * the document app installed, the action opens the dialog, Download PDF stays
 * disabled until a template is chosen, and confirming opens the download URL
 * in a new tab. Without it the action is absent. The run takes whichever
 * branch the instance is in, and says which.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
} from './fixtures.ts'

let api: APIRequestContext
const ledger = new FixtureLedger()
let characterId = ''
let pdfState: {
	available: boolean
	templates: Array<{ id: string; name: string }>
} = { available: false, templates: [] }

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)
	const player = ledger.track(
		'player',
		await createObject(api, 'player', { name: fixtureName('pdf-player') }),
	)
	characterId = ledger.track(
		'character',
		await createObject(api, 'character', {
			name: fixtureName('Sir Lancelot'),
			ocName: player,
		}),
	)
	const res = await api.get(
		`${BASE_URL}/index.php/apps/larpinq/api/pdf/templates`,
		{ headers: { 'OCS-APIRequest': 'true' } },
	)
	if (res.ok()) {
		pdfState = await res.json()
	}
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

/**
 * Open the character's detail page and its Actions menu.
 *
 * @param {Page} page The page.
 * @return {Promise<void>}
 */
async function openActions(page: Page): Promise<void> {
	await page.goto(`${BASE}/characters/${characterId}`)
	await page
		.locator('#app-content, .app-content, #content')
		.first()
		.waitFor({ state: 'visible', timeout: 30_000 })
		.catch(() => {})
	await page.getByRole('button', { name: 'Actions' }).first().click()
}

// @e2e openspec/specs/pdf-export/spec.md#the-pdf-download-flow-has-a-real-playwright-test-not-an-exclusion
test.describe('larpinq-pdf-frontend-download-action', () => {
	// @e2e openspec/specs/pdf-export/spec.md#docudesk-not-installed-hides-the-action-entirely
	test('without the document app the action is absent', async ({ page }) => {
		test.skip(
			pdfState.available,
			'the document app is installed on this instance; the other tests cover it',
		)
		await openActions(page)

		await expect(
			page.getByRole('menuitem', { name: 'Download as PDF' }),
		).toHaveCount(0)
	})

	// @e2e openspec/specs/pdf-export/spec.md#download-disabled-until-a-template-is-chosen
	test('Download PDF stays disabled until a template is chosen', async ({
		page,
	}) => {
		test.skip(
			!pdfState.available || pdfState.templates.length === 0,
			'needs the document app with a larpingapp template',
		)
		await openActions(page)
		await page.getByRole('menuitem', { name: 'Download as PDF' }).click()

		await expect(page.getByTestId('character-pdf-download')).toBeDisabled()
	})

	// @e2e openspec/specs/pdf-export/spec.md#player-downloads-their-own-character-sheet-from-the-ui
	test('choosing a template opens the download in a new tab', async ({
		page,
		context,
	}) => {
		test.skip(
			!pdfState.available || pdfState.templates.length === 0,
			'needs the document app with a larpingapp template',
		)
		await openActions(page)
		await page.getByRole('menuitem', { name: 'Download as PDF' }).click()
		await page.getByTestId('character-pdf-template').click()
		await page.getByText(pdfState.templates[0].name, { exact: true }).click()

		const [tab] = await Promise.all([
			context.waitForEvent('page'),
			page.getByTestId('character-pdf-download').click(),
		])

		expect(tab.url()).toContain(
			`/apps/larpinq/characters/${characterId}/download/${pdfState.templates[0].id}`,
		)
	})
})
