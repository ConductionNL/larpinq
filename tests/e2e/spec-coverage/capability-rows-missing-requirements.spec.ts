/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec coverage for openspec/changes/capability-rows-missing-requirements:
 * the world page's description and timeline, the player page's account and
 * characters, and the game master override on the character page.
 *
 * As admin, provisions player Anna (a Nextcloud user in `larpers`) and seeds,
 * with this run's RUN_ID in every name, world Aldmoor with a description, a
 * history entry for its founding, a game-master-only history entry and the
 * past event "Summer Siege 2025"; Anna's player record with her account and
 * two characters; and skill "Dragon Speech" requiring a skill "Chosen One"
 * lacks. Set-up and cleanup go through the OpenRegister object API; every
 * assertion is read from the rendered pages. Anna's pages are opened in a
 * fresh browser context with an empty storage state, signed in as her.
 *
 * Scenarios not driven here:
 * - leaf-integrations REQ-LFI-001 (milestones are Deck cards): the cards are
 *   made in the Deck app's own UI; the event's Deck tab, and its absence
 *   without Deck, are covered by tests/e2e/workflows/leaf-integrations.workflow.spec.ts.
 * - event-registration REQ-RTO-007 (a choice per registration option): covered
 *   by tests/e2e/workflows/registration-tickets.workflow.spec.ts.
 * - the run sheet download from the event page: tests/e2e/spec-coverage/event-runsheet-export.spec.ts.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/setting-management/spec.md
 * @spec openspec/changes/capability-rows-missing-requirements/specs/events-players/spec.md
 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import { APP_BASE, dismissSupportDialog } from '../_nav.ts'
import { fixtureName, newApi, RUN_ID } from '../workflows/fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' }
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`
const description = 'A rain-soaked river kingdom where two queens claim one crown'
const names = {
	world: fixtureName('Aldmoor'),
	founding: fixtureName('The founding of Aldmoor'),
	secret: fixtureName('The queen of Aldmoor is a changeling'),
	siege: fixtureName('Summer Siege 2025'),
	anna: fixtureName('Anna de Vries'),
	mirela: fixtureName('Mirela the Wanderer'),
	harrow: fixtureName('Old Captain Harrow'),
	chosen: fixtureName('Chosen One'),
	lore: fixtureName('Old Lore'),
	dragon: fixtureName('Dragon Speech'),
}
const reason = 'Plot reward from Summer LARP finale'

let admin: APIRequestContext
const seeded: Array<[string, string]> = []
const ids: Record<string, string> = {}

test.describe.configure({ mode: 'serial' })

/**
 * Create one object by schema slug and remember it for cleanup.
 *
 * @param {string} slug The schema slug.
 * @param {object} data The object.
 * @return {Promise<string>} The new uuid.
 */
async function seed(slug: string, data: Record<string, unknown>): Promise<string> {
	const res = await admin.post(`${OBJECTS}/${slug}`, { headers: JSON_HEADERS, data })
	expect(res.ok(), `seed ${slug}: ${res.status()} ${await res.text()}`).toBe(true)
	const json = await res.json()
	const id = String(json?.['@self']?.id ?? json?.id ?? '')
	expect(id, `seed ${slug} returned no id`).not.toBe('')
	seeded.push([slug, id])
	return id
}

/**
 * A browser page signed in as Anna, from an empty storage state.
 *
 * @param {Browser} browser The browser.
 * @return {Promise<Page>} The page.
 */
async function asAnna(browser: Browser): Promise<Page> {
	const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })
	const page = await context.newPage()
	await page.goto(`${BASE_URL}/index.php/login`)
	await page.locator('input#user, input[name="user"], input[autocomplete="username"]').first().fill(annaUid)
	await page.locator('input#password, input[name="password"], input[autocomplete="current-password"]').first().fill(annaPass)
	await Promise.all([
		page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 }),
		page.locator('button[type="submit"]').first().click(),
	])
	return page
}

/**
 * Open a larpinq route and wait for the shell.
 *
 * @param {Page} page The page.
 * @param {string} route The route, e.g. `/settings/<id>`.
 * @return {Promise<void>}
 */
async function open(page: Page, route: string): Promise<void> {
	await page.goto(`${BASE_URL}/index.php${APP_BASE}${route}`)
	await dismissSupportDialog(page)
}

test.beforeAll(async () => {
	admin = await newApi()
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, { headers: OCS, form: { groupid: 'larpers' } })
	const created = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
		headers: OCS,
		form: { userid: annaUid, password: annaPass, 'groups[]': 'larpers' },
	})
	expect(created.ok(), await created.text()).toBe(true)

	ids.world = await seed('setting', { name: names.world, description })
	ids.founding = await seed('larping_history_entry', {
		setting: ids.world, date: '0812-04-01', dateLabel: 'Spring of 812', title: names.founding, visibility: 'players',
	})
	ids.secret = await seed('larping_history_entry', {
		setting: ids.world, date: '0900-01-01', title: names.secret, visibility: 'gamemasters',
	})
	ids.siege = await seed('larping_event', { name: names.siege, setting: ids.world, startDate: '2025-07-12', endDate: '2025-07-14' })

	ids.anna = await seed('player', { name: names.anna, userUid: annaUid })
	ids.mirela = await seed('character', { name: names.mirela, ocName: ids.anna, ownerUid: annaUid, setting: ids.world })
	ids.harrow = await seed('character', { name: names.harrow, ocName: ids.anna, ownerUid: annaUid, setting: ids.world })

	ids.lore = await seed('larping_skill', { name: names.lore, title: names.lore, setting: ids.world })
	ids.dragon = await seed('larping_skill', { name: names.dragon, title: names.dragon, setting: ids.world, requiredSkills: [ids.lore] })
	ids.chosen = await seed('character', { name: names.chosen, ocName: ids.anna, ownerUid: annaUid, setting: ids.world })
})

test.afterAll(async () => {
	if (!admin) return
	for (const [slug, id] of seeded.reverse()) {
		await admin.delete(`${OBJECTS}/${slug}/${id}`, { headers: OCS }).catch(() => undefined)
	}
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${annaUid}`, { headers: OCS }).catch(() => undefined)
	await admin.dispose()
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/setting-management/spec.md#a-player-reads-what-aldmoor-is-like
test('a player reads the world description on the world page', async ({ browser }) => {
	const page = await asAnna(browser)
	await open(page, `/settings/${ids.world}`)
	await expect(page.getByText(description).first()).toBeVisible({ timeout: 30_000 })
	await page.context().close()
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/setting-management/spec.md#the-founding-comes-before-the-siege
test('the world timeline lists the founding before the Summer Siege', async ({ page }) => {
	await open(page, `/settings/${ids.world}`)
	const timeline = page.locator('section, div').filter({ has: page.getByRole('heading', { name: 'Timeline' }) }).last()
	await expect(timeline).toContainText(names.founding, { timeout: 30_000 })
	await expect(timeline).toContainText(names.siege)
	const text = await timeline.innerText()
	expect(text.indexOf(names.founding)).toBeLessThan(text.indexOf(names.siege))
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/setting-management/spec.md#a-hidden-entry-stays-hidden
test('a game-master history entry is in the game master\'s timeline and not in a player\'s', async ({ page, browser }) => {
	await open(page, `/settings/${ids.world}`)
	await expect(page.getByText(names.secret).first()).toBeVisible({ timeout: 30_000 })

	const anna = await asAnna(browser)
	await open(anna, `/settings/${ids.world}`)
	await expect(anna.getByText(names.founding).first()).toBeVisible({ timeout: 30_000 })
	await expect(anna.getByText(names.secret)).toHaveCount(0)
	await anna.context().close()
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/events-players/spec.md#a-game-master-links-anna-s-account
test('the player page shows the linked Nextcloud account', async ({ page }) => {
	await open(page, `/players/${ids.anna}`)
	await expect(page.getByText(names.anna).first()).toBeVisible({ timeout: 30_000 })
	await expect(page.getByText(annaUid).first()).toBeVisible()
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/events-players/spec.md#anna-s-characters
test('the player page lists the player\'s characters and opens one', async ({ page }) => {
	await open(page, `/players/${ids.anna}`)
	await expect(page.getByText(names.mirela).first()).toBeVisible({ timeout: 30_000 })
	await expect(page.getByText(names.harrow).first()).toBeVisible()
	await page.getByText(names.mirela).first().click()
	await expect(page).toHaveURL(new RegExp(`/characters/${ids.mirela}`), { timeout: 30_000 })
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#a-game-master-grants-a-skill-as-a-plot-reward
test('a game master grants Dragon Speech with a reason and the history shows it', async ({ page }) => {
	await open(page, `/characters/${ids.chosen}`)
	await page.getByRole('button', { name: 'Override a requirement' }).first().click()
	const dialog = page.getByRole('dialog').filter({ hasText: 'Override a requirement' })
	await expect(dialog).toBeVisible({ timeout: 30_000 })
	await dialog.getByRole('combobox').first().click()
	await page.getByRole('option', { name: names.dragon }).first().click()
	await dialog.getByLabel('Reason').fill(reason)
	await dialog.getByTestId('override-save').click()
	await expect(dialog).toContainText(`${names.chosen} now has ${names.dragon}`, { timeout: 30_000 })
	await dialog.getByTestId('override-cancel').click()

	await page.reload()
	await dismissSupportDialog(page)
	await expect(page.getByText(names.dragon).first()).toBeVisible({ timeout: 30_000 })
	await page.getByRole('tab', { name: 'History' }).first().click()
	await expect(page.getByText(reason).first()).toBeVisible({ timeout: 30_000 })
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#a-player-has-no-override-action
test('a player gets no override action on her own character', async ({ browser }) => {
	const page = await asAnna(browser)
	await open(page, `/characters/${ids.mirela}`)
	await expect(page.getByText(names.mirela).first()).toBeVisible({ timeout: 30_000 })
	await expect(page.getByRole('button', { name: 'Override a requirement' })).toHaveCount(0)
	await page.context().close()
})
