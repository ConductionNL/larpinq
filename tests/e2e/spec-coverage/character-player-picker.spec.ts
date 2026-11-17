/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * character-player-picker, test plan TC-1 to TC-7: a character names its
 * player, its ownerUid is derived from that player's Nextcloud user, the
 * form shows no ownerUid field and approval is a switch, and the owner is
 * notified when the character is approved.
 *
 * The owner is a real account made for the run and reads its notifications
 * with its own context and no stored session.
 *
 * @spec openspec/changes/character-player-picker/specs/character-management/spec.md
 * @spec openspec/changes/character-player-picker/specs/events-players/spec.md
 * @spec openspec/changes/character-player-picker/specs/notifications/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { runBackgroundJobs } from '../_background-jobs.ts'
import { BASE_URL } from '../_base-url.ts'
import { FixtureLedger, fixtureName, newApi, OR_BASE } from '../workflows/fixtures.ts'

const BASE = '/apps/larpinq'
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const RUN = `cpp${Date.now().toString(36)}`
const alice = { username: `${RUN}-alice`, password: `Alice-${RUN}-pw!1` }

let api: APIRequestContext
let asAlice: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
const lancelot = fixtureName('Sir Lancelot')

/**
 * Create one larpinq object by schema slug and track it for cleanup.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<object>} The created object.
 */
async function make(slug: string, body: Record<string, unknown>): Promise<Record<string, any>> {
	const res = await api.post(`${OR_BASE}/larpinq/${slug}`, { headers: JSON_HEADERS, data: body })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const created = await res.json()
	ledger.track(slug, String(created.id))
	return created
}

test.beforeAll(async () => {
	api = await newApi()
	const created = await api.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
		headers: OCS,
		form: { userid: alice.username, password: alice.password, 'groups[]': 'larpers' },
	})
	expect(created.status()).toBe(200)
	asAlice = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: alice.username, password: alice.password, send: 'always' },
	})
	ids.alice = (await make('player', { name: fixtureName('Alice'), userUid: alice.username })).id
})

test.afterAll(async () => {
	for (const slug of ['character', 'player']) {
		for (const id of ledger.ids(slug)) {
			await api.delete(`${OR_BASE}/larpinq/${slug}/${id}`, { headers: JSON_HEADERS }).catch(() => undefined)
		}
	}
	await api.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${alice.username}`, { headers: OCS })
	await asAlice?.dispose()
	await api.dispose()
})

test.describe.serial('a character names its player', () => {
	// TC-3
	test('ownerUid is derived from the linked player\'s Nextcloud user', async () => {
		const character = await make('character', { name: lancelot, ocName: ids.alice })
		ids.lancelot = character.id
		expect(character.ocName).toBe(ids.alice)
		expect(character.ownerUid).toBe(alice.username)
		expect(character.approved).toBe('no')
	})

	// TC-5
	test('approving the character notifies its owner', async () => {
		const res = await api.patch(`${OR_BASE}/larpinq/character/${ids.lancelot}`, {
			headers: JSON_HEADERS,
			data: { approved: 'approved' },
		})
		expect(res.ok(), await res.text()).toBe(true)

		runBackgroundJobs()
		const notifications = await asAlice.get('/ocs/v2.php/apps/notifications/api/v2/notifications', { headers: OCS })
		expect(notifications.status()).toBe(200)
		const subjects = ((await notifications.json()).ocs.data as Array<{ subject: string }>).map((n) => n.subject)
		expect(subjects.some((s) => s.includes(lancelot))).toBe(true)
	})

	// TC-6
	test('a character whose player reference resolves to nothing is approved without error', async () => {
		// A uuid no player answers to: OpenRegister refuses a non-uuid ocName.
		const legacy = await make('character', { name: fixtureName('Legacy knight'), ocName: '00000000-0000-4000-8000-00000000dead' })
		const res = await api.patch(`${OR_BASE}/larpinq/character/${legacy.id}`, {
			headers: JSON_HEADERS,
			data: { approved: 'approved' },
		})
		expect(res.ok(), await res.text()).toBe(true)
		expect((await res.json()).approved).toBe('approved')
		expect(() => runBackgroundJobs()).not.toThrow()
	})
})

// TC-1, TC-2, TC-7
test('the create form picks the player from a list, hides ownerUid and shows approval as a switch', async ({ page }) => {
	await page.goto(`${BASE}/characters`)
	const add = page.locator('.app-content button').filter({ hasText: /Add Character/i }).first()
	await expect(add).toBeVisible({ timeout: 30_000 })
	await add.click()
	const dialog = page.locator('[role="dialog"]').first()
	await expect(dialog).toBeVisible()

	await expect(dialog.getByRole('combobox', { name: /^Player/ })).toBeVisible()
	await expect(dialog.getByText(/Owner UID/i)).toHaveCount(0)
	await expect(dialog.getByRole('switch', { name: 'Approved' })).toBeVisible()
})
