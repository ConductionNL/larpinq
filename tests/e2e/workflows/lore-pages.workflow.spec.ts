/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: lore pages per world, read by players once revealed
 * (worlds-lore-pages).
 *
 * As admin, provisions player Anna (a Nextcloud user in `larpers`) and a world
 * with four lore pages: two revealed to players, one for game masters only,
 * and one for players with a reveal moment in the future. Then reads as Anna
 * through OpenRegister, the path every Larpinq screen uses, and opens the
 * article page in the browser.
 *
 * @spec openspec/specs/world-lore/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
	RUN_ID,
} from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const LORE = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq/larping_lore_page`

const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`

let admin: APIRequestContext
let anna: APIRequestContext
const ledger = new FixtureLedger()
const lore: string[] = []
const ids: Record<string, string> = {}
let worldId = ''

/**
 * Create one lore page as admin.
 *
 * @param {object} body The page.
 * @return {Promise<string>} Its id.
 */
async function createLore(body: Record<string, unknown>): Promise<string> {
	const res = await admin.post(LORE, {
		headers: { 'OCS-APIRequest': 'true' },
		data: { setting: worldId, ...body },
	})
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	lore.push(id)
	return id
}

/**
 * The titles Anna gets from the Lore index.
 *
 * @param {string} [search] Optional search term.
 * @return {Promise<string[]>} The titles.
 */
async function annaTitles(search = ''): Promise<string[]> {
	const query = new URLSearchParams({ setting: worldId, _limit: '100' })
	if (search) {
		query.set('_search', search)
	}
	const res = await anna.get(`${LORE}?${query.toString()}`, { headers: OCS })
	expect(res.ok()).toBe(true)
	return ((await res.json())?.results ?? []).map(
		(row: { title: string }) => row.title,
	)
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
		headers: OCS,
		form: { groupid: 'larpers' },
	})
	const created = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
		headers: OCS,
		form: { userid: annaUid, password: annaPass, 'groups[]': 'larpers' },
	})
	expect(created.ok(), await created.text()).toBe(true)
	anna = await request.newContext({
		httpCredentials: { username: annaUid, password: annaPass },
	})

	worldId = ledger.track(
		'setting',
		await createObject(admin, 'setting', { name: fixtureName('Aldmoor') }),
	)
	const past = '2026-01-01T00:00:00+00:00'
	ids.city = await createLore({
		title: 'The city of Aldmoor',
		category: 'place',
		visibility: 'players',
		revealFrom: past,
		body: '# Aldmoor\n\nA walled trade city.',
	})
	ids.war = await createLore({
		title: 'The war of the two queens',
		category: 'history',
		visibility: 'players',
		revealFrom: past,
		body: 'Two sisters, one crown.',
	})
	ids.ash = await createLore({
		title: 'The Ash Circle',
		category: 'faction',
		visibility: 'gamemasters',
		revealFrom: past,
	})
	ids.gate = await createLore({
		title: 'The fall of the north gate',
		category: 'history',
		visibility: 'players',
		revealFrom: '2099-10-10T18:00:00+00:00',
	})
})

test.afterAll(async () => {
	for (const id of lore) {
		await admin.delete(`${LORE}/${id}`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
	}
	await cleanupLedger(admin, ledger)
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${annaUid}`, {
		headers: OCS,
	})
	await anna.dispose()
	await admin.dispose()
})

test.describe('lore pages', () => {
	// @e2e openspec/specs/world-lore/spec.md#a-game-master-describes-the-city
	test('a game master sees every page of the world', async ({ page }) => {
		const res = await admin.get(`${LORE}?setting=${worldId}&_limit=100`, {
			headers: OCS,
		})
		const titles = ((await res.json())?.results ?? []).map(
			(row: { title: string }) => row.title,
		)
		expect(titles.sort()).toEqual([
			'The Ash Circle',
			'The city of Aldmoor',
			'The fall of the north gate',
			'The war of the two queens',
		])

		await page.goto(`${BASE_URL}/index.php${BASE}/settings/${worldId}`)
		await expect(page.getByText('The city of Aldmoor')).toBeVisible()
	})

	// @e2e openspec/specs/world-lore/spec.md#a-secret-faction-page-stays-hidden
	test('a player never gets the game master page', async () => {
		expect(await annaTitles('Ash')).toEqual([])
		const direct = await anna.get(`${LORE}/${ids.ash}`, { headers: OCS })
		expect(direct.ok()).toBe(false)
	})

	// @e2e openspec/specs/world-lore/spec.md#the-north-gate-falls-on-the-evening-of-the-event
	test('a player gets a page only from its reveal moment', async () => {
		expect(await annaTitles()).not.toContain('The fall of the north gate')
		const reveal = await admin.patch(`${LORE}/${ids.gate}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { revealFrom: new Date(Date.now() - 60_000).toISOString() },
		})
		expect(reveal.ok(), await reveal.text()).toBe(true)
		expect(await annaTitles()).toContain('The fall of the north gate')
	})

	// @e2e openspec/specs/world-lore/spec.md#anna-reads-about-the-war
	test('the article renders with the pages the reader may see', async ({
		page,
	}) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/lore/${ids.war}`)
		await expect(page.getByTestId('cn-wiki-page')).toBeVisible()
		await expect(
			page.getByRole('heading', { name: 'The war of the two queens' }),
		).toBeVisible()
		await expect(page.getByText('The city of Aldmoor')).toBeVisible()
	})
})
