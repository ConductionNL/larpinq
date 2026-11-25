/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: world maps with pins that link to lore (worlds-maps).
 *
 * As admin, provisions player Anna (a Nextcloud user in `larpers`), world
 * Aldmoor, lore page "The city of Aldmoor", the players' map "The valley of
 * Aldmoor" with pins "Aldmoor city" (players, linked to the lore page) and
 * "Ash grove" (game masters), and a game-master-only map "The smugglers'
 * tunnels". Then reads as Anna through the OpenRegister object API, the path
 * every Larpinq screen uses, and in the browser from a fresh context with an
 * EMPTY storage state, so the page is rendered for Anna and never for the
 * admin session the suite's global setup stores.
 *
 * The rules under test live in lib/Settings/register.d/worlds-maps.json.
 *
 * The clickable map itself (REQ-WMP-002, tasks.md 2.2) is the map widget on
 * the map's detail page: its picture, width and height come from the map
 * object (nextcloud-vue map-image-from-record), its pins from larping_map_pin.
 *
 * @spec openspec/specs/world-maps/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
	RUN_ID,
} from './workflows/fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const MAPS = `${OBJECTS}/larping_world_map`
const PINS = `${OBJECTS}/larping_map_pin`
const LORE = `${OBJECTS}/larping_lore_page`

/** A 1x1 transparent PNG: the map image only has to be a real image file. */
const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='

const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`

let admin: APIRequestContext
let anna: APIRequestContext
const ledger = new FixtureLedger()
const created: string[] = []
const ids: Record<string, string> = {}
let worldId = ''

/**
 * Create one object of a Larpinq schema as admin, addressed by slug.
 *
 * @param {string} url The collection URL.
 * @param {object} body The object.
 * @return {Promise<string>} Its id.
 */
async function createAs(url: string, body: Record<string, unknown>): Promise<string> {
	const res = await admin.post(url, {
		headers: { 'OCS-APIRequest': 'true' },
		data: body,
	})
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	created.unshift(`${url}/${id}`)
	return id
}

/**
 * The values of one property in a collection read as the given user.
 *
 * @param {APIRequestContext} api The reader.
 * @param {string} url The collection URL.
 * @param {object} filter Query filters.
 * @param {string} key The property to collect.
 * @return {Promise<string[]>} The values, sorted.
 */
async function listed(
	api: APIRequestContext,
	url: string,
	filter: Record<string, string>,
	key: string,
): Promise<string[]> {
	const query = new URLSearchParams({ ...filter, _limit: '100' })
	const res = await api.get(`${url}?${query.toString()}`, { headers: OCS })
	expect(res.ok(), await res.text()).toBe(true)
	return ((await res.json())?.results ?? [])
		.map((row: Record<string, string>) => row[key])
		.sort()
}

/**
 * Open a page as Anna: a new browser context with an empty storage state,
 * logged in through the Nextcloud login form.
 *
 * @param {Browser} browser The browser.
 * @return {Promise<Page>} A page logged in as Anna.
 */
async function annaPage(browser: Browser): Promise<Page> {
	const context = await browser.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
	})
	const page = await context.newPage()
	await page.goto(`${BASE_URL}/index.php/login`)
	const user = page
		.locator('input#user, input[name="user"], input[autocomplete="username"]')
		.first()
	const password = page
		.locator(
			'input#password, input[name="password"], input[autocomplete="current-password"]',
		)
		.first()
	await user.waitFor({ state: 'visible', timeout: 30_000 })
	const submit = page.locator('button[type="submit"]').first()
	await expect(submit).toBeEnabled({ timeout: 30_000 })
	await user.fill(annaUid)
	await password.fill(annaPass)
	await submit.click()
	await page.waitForURL((url) => /\/login(\?|$|\/)/.test(url.pathname) === false, {
		timeout: 60_000,
	})
	return page
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
		headers: OCS,
		form: { groupid: 'larpers' },
	})
	const user = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
		headers: OCS,
		form: { userid: annaUid, password: annaPass, 'groups[]': 'larpers' },
	})
	expect(user.ok(), await user.text()).toBe(true)
	anna = await request.newContext({
		httpCredentials: { username: annaUid, password: annaPass },
	})

	worldId = ledger.track(
		'setting',
		await createObject(admin, 'setting', { name: fixtureName('Aldmoor') }),
	)
	ids.lore = await createAs(LORE, {
		setting: worldId,
		title: 'The city of Aldmoor',
		category: 'place',
		visibility: 'players',
		revealFrom: '2026-01-01T00:00:00+00:00',
		body: '# Aldmoor\n\nA walled trade city.',
	})
	ids.valley = await createAs(MAPS, {
		setting: worldId,
		title: 'The valley of Aldmoor',
		image: PNG,
		width: 1600,
		height: 1200,
		visibility: 'players',
	})
	ids.tunnels = await createAs(MAPS, {
		setting: worldId,
		title: "The smugglers' tunnels",
		width: 800,
		height: 600,
		visibility: 'gamemasters',
	})
	ids.city = await createAs(PINS, {
		map: ids.valley,
		label: 'Aldmoor city',
		x: 820,
		y: 610,
		kind: 'town',
		lorePage: ids.lore,
		visibility: 'players',
	})
	ids.grove = await createAs(PINS, {
		map: ids.valley,
		label: 'Ash grove',
		x: 300,
		y: 240,
		kind: 'danger',
		visibility: 'gamemasters',
	})
	ids.tunnelPin = await createAs(PINS, {
		map: ids.tunnels,
		label: 'Hidden landing',
		x: 120,
		y: 90,
		kind: 'other',
		visibility: 'players',
	})
})

test.afterAll(async () => {
	for (const url of created) {
		await admin.delete(url, { headers: { 'OCS-APIRequest': 'true' } })
	}
	await cleanupLedger(admin, ledger)
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${annaUid}`, {
		headers: OCS,
	})
	await anna?.dispose()
	await admin.dispose()
})

test.describe('world maps', () => {
	// @e2e openspec/specs/world-maps/spec.md#a-game-master-maps-the-valley
	test('a game master maps the valley', async ({ page }) => {
		expect(await listed(admin, MAPS, { setting: worldId }, 'title')).toEqual([
			"The smugglers' tunnels",
			'The valley of Aldmoor',
		])
		expect(await listed(admin, PINS, { map: ids.valley }, 'label')).toEqual([
			'Aldmoor city',
			'Ash grove',
		])
		const pin = await (await admin.get(`${PINS}/${ids.city}`, { headers: OCS })).json()
		expect(pin.x).toBe(820)
		expect(pin.y).toBe(610)
		expect(pin.lorePage).toBe(ids.lore)

		await page.goto(`${BASE_URL}/index.php${BASE}/maps/${ids.valley}/details`)
		await expect(page.getByText('The valley of Aldmoor').first()).toBeVisible()
		await expect(page.getByText('Aldmoor city')).toBeVisible()
		await expect(page.getByText('Ash grove')).toBeVisible()
	})

	// @e2e openspec/specs/world-maps/spec.md#the-dangerous-grove
	test('a player gets no game-master map or pin through the API', async () => {
		expect(await listed(anna, MAPS, { setting: worldId }, 'title')).toEqual([
			'The valley of Aldmoor',
		])
		expect(await listed(anna, PINS, { map: ids.valley }, 'label')).toEqual([
			'Aldmoor city',
		])
		const grove = await anna.get(`${PINS}/${ids.grove}`, { headers: OCS })
		expect(grove.ok()).toBe(false)
		const tunnels = await anna.get(`${MAPS}/${ids.tunnels}`, { headers: OCS })
		expect(tunnels.ok()).toBe(false)
	})

	// @e2e openspec/specs/world-maps/spec.md#the-dangerous-grove
	test('a player does not see the hidden pin on the map page', async ({ browser }) => {
		const page = await annaPage(browser)
		await page.goto(`${BASE_URL}/index.php${BASE}/maps/${ids.valley}/details`)
		await expect(page.getByText('Aldmoor city')).toBeVisible()
		await expect(page.getByText('Ash grove')).toHaveCount(0)
		await page.context().close()
	})

	// @e2e openspec/specs/world-maps/spec.md#anna-finds-the-city
	test('a player reaches the lore page of a pin she may see', async () => {
		const pin = await (await anna.get(`${PINS}/${ids.city}`, { headers: OCS })).json()
		expect(pin.label).toBe('Aldmoor city')
		const lore = await anna.get(`${LORE}/${pin.lorePage}`, { headers: OCS })
		expect(lore.ok(), await lore.text()).toBe(true)
		expect((await lore.json()).title).toBe('The city of Aldmoor')
	})

	// @e2e openspec/specs/world-maps/spec.md#anna-finds-the-city
	// tasks.md 2.2: the map detail page draws the map's own picture (nextcloud-vue
	// map-image-from-record) with the pins Anna may see; a pin opens its lore page.
	test('a player clicks the city pin on the map and opens its lore page', async ({ browser }) => {
		const page = await annaPage(browser)
		await page.goto(`${BASE_URL}/index.php${BASE}/maps/${ids.valley}/details`)
		const map = page.locator('.leaflet-container').first()
		await expect(map.locator('img.leaflet-image-layer')).toBeVisible()
		const pins = map.locator('.leaflet-overlay-pane path.leaflet-interactive, .leaflet-marker-pane .leaflet-marker-icon')
		await expect(pins, 'the game masters\' Ash grove is not on Anna\'s map').toHaveCount(1)
		const [lore] = await Promise.all([page.waitForEvent('popup'), pins.first().click()])
		await expect(lore).toHaveURL(new RegExp(`/lore/${ids.lore}`))
		await expect(lore.getByRole('heading', { name: 'The city of Aldmoor' })).toBeVisible()
		await page.context().close()
	})
})
