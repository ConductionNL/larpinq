/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Marketplace page of admin-configuration-store (task 1.3, REQ-ACS-001 to
 * REQ-ACS-003).
 *
 * Runs now: with no registry connected the page renders and calls no registry
 * (REQ-ACS-001), and a player who is not an administrator is offered no
 * Install (REQ-ACS-002).
 *
 * `test.fixme` until its parts land: installing a seeded bundle into one world
 * needs the target picker of nextcloud-vue CnStorePage (task 1.1), the
 * `target` of OpenRegister's install endpoint (task 1.2) and `config.targets`
 * on the Store page (task 1.3); browsing a published bundle needs a registry
 * seeded with "Fantasy basisregels (vaardighedenboom)" on the test instance.
 *
 * Not run in the build that wrote it: it needs a live instance.
 *
 * @spec openspec/changes/admin-configuration-store/specs/admin-configuration-store/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL, OR_OBJECTS_API } from './_base-url.ts'

const RUN = `store${Date.now().toString(36)}`
const BASE = '/apps/larpinq'
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const ADMIN = { username: process.env.NC_ADMIN_USER ?? 'admin', password: process.env.NC_ADMIN_PASS ?? 'admin' }
const BUNDLE = 'Fantasy basisregels (vaardighedenboom)'
const WORLDS = { south: `${RUN} De Zuiderlanden`, coast: `${RUN} Nevelkust` }

const sem = { username: `${RUN}-sem`, password: `Sem-${RUN}-pw!1` }
type User = typeof sem

let admin: APIRequestContext
const worldIds: Record<keyof typeof WORLDS, string> = { south: '', coast: '' }

/**
 * A request context that signs in as one user and carries no stored session.
 *
 * @param user The account.
 * @return The context.
 */
function apiAs(user: User): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: user.username, password: user.password, send: 'always' },
	})
}

/**
 * A browser page signed in as one user, with an empty storage state.
 *
 * @param browser The browser.
 * @param user The account.
 * @return The page.
 */
async function pageAs(browser: Browser, user: User): Promise<Page> {
	const context = await browser.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: user.username, password: user.password, send: 'always' },
	})
	return context.newPage()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await apiAs(ADMIN)
	const res = await admin.post('/ocs/v2.php/cloud/users', {
		headers: OCS,
		form: { userid: sem.username, password: sem.password, 'groups[]': 'larpers' },
	})
	expect(res.status(), `create ${sem.username}`).toBe(200)

	for (const key of Object.keys(WORLDS) as Array<keyof typeof WORLDS>) {
		const made = await admin.post(`${OR_OBJECTS_API}/larpinq/setting`, { headers: JSON_HEADERS, data: { name: WORLDS[key] } })
		expect(made.ok(), await made.text()).toBe(true)
		const json = await made.json()
		worldIds[key] = String(json?.id ?? json?.['@self']?.id)
	}
})

test.afterAll(async () => {
	if (!admin) return
	for (const id of Object.values(worldIds).filter(Boolean)) {
		await admin.delete(`${OR_OBJECTS_API}/larpinq/setting/${id}`, { headers: OCS }).catch(() => undefined)
	}
	await admin.delete(`/ocs/v2.php/cloud/users/${sem.username}`, { headers: OCS }).catch(() => undefined)
	await admin.dispose()
})

test.describe('admin-configuration-store', () => {
	/**
	 * @e2e store::no-registry-is-connected
	 */
	test('with no registry the Marketplace renders and calls no registry', async ({ page }) => {
		const origin = new URL(BASE_URL).origin
		const foreign: string[] = []
		page.on('request', (req) => {
			const url = req.url()
			if (url.startsWith('http') && !url.startsWith(origin)) {
				foreign.push(url)
			}
		})

		await page.goto(`${BASE}/store`, { waitUntil: 'domcontentloaded' })
		await expect(page.getByRole('heading', { name: 'Marketplace' }).first()).toBeVisible({ timeout: 30_000 })
		expect(foreign, 'no request leaves the instance').toEqual([])
	})

	/**
	 * @e2e store::a-player-sees-no-install-action
	 */
	test('a player who is not an administrator is offered no Install', async ({ browser }) => {
		const page = await pageAs(browser, sem)
		try {
			await page.goto(`${BASE}/store`, { waitUntil: 'domcontentloaded' })
			await expect(page.getByRole('heading', { name: 'Marketplace' }).first()).toBeVisible({ timeout: 30_000 })
			await expect(page.getByRole('button', { name: /^Install/ })).toHaveCount(0)
		} finally {
			await page.context().close()
		}
	})

	/**
	 * @e2e store::a-game-master-browses-the-store
	 */
	test.fixme('a published bundle shows its kind, publisher, contents and version', async ({ page }) => {
		await page.goto(`${BASE}/store`, { waitUntil: 'domcontentloaded' })
		const card = page.locator('[data-testid^="store-card"]').filter({ hasText: BUNDLE }).first()
		await expect(card).toBeVisible({ timeout: 30_000 })
		await expect(card).toContainText('Larpvereniging Zuiddrecht')
		await expect(card).toContainText('2.1')
	})

	/**
	 * @e2e store::a-skill-tree-from-another-campaign-goes-into-one-world
	 */
	test.fixme('the administrator installs a seeded bundle into one world', async ({ page }) => {
		await page.goto(`${BASE}/store`, { waitUntil: 'domcontentloaded' })
		const card = page.locator('[data-testid^="store-card"]').filter({ hasText: BUNDLE }).first()
		await card.getByRole('button', { name: /^Install/ }).click()
		await page.getByRole('combobox', { name: /world/i }).click()
		await page.getByRole('option', { name: WORLDS.south }).click()
		await page.getByRole('button', { name: /^Install/ }).last().click()
		await expect(card).toContainText(`Geïnstalleerd in ${WORLDS.south}`, { timeout: 60_000 })

		const skills = await admin.get(`${OR_OBJECTS_API}/larpinq/skill?setting=${worldIds.coast}&_limit=500`, { headers: OCS })
		expect(((await skills.json())?.results ?? []).length, 'nothing lands in Nevelkust').toBe(0)
		const installed = await admin.get(`${OR_OBJECTS_API}/larpinq/skill?setting=${worldIds.south}&_limit=500`, { headers: OCS })
		expect(((await installed.json())?.results ?? []).length).toBe(28)
	})

	/**
	 * @e2e store::the-list-shows-what-fits-the-active-world
	 */
	test.fixme('the list narrows to the bundles that fit the active world', async ({ page }) => {
		await admin.put(`${BASE_URL}/index.php${BASE}/api/preferences/active-world`, { data: { value: worldIds.south } })
		try {
			await page.goto(`${BASE}/store`, { waitUntil: 'domcontentloaded' })
			await expect(page.getByTestId('store-fits-target')).toHaveText(`Past bij: ${WORLDS.south}`, { timeout: 30_000 })
			await page.getByTestId('store-fits-target-clear').click()
			await expect(page.getByTestId('store-active-filters')).toHaveCount(0)
		} finally {
			await admin.put(`${BASE_URL}/index.php${BASE}/api/preferences/active-world`, { data: { value: '' } })
		}
	})
})
