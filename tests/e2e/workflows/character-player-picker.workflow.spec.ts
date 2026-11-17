/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: a character's player is picked from the players, the owner's
 * Nextcloud account follows from that player, and Approved is a toggle.
 *
 * Covers test-plan.md TC-1 to TC-7 of openspec/changes/character-player-picker:
 * the form cases (TC-1 player dropdown, TC-2 no Owner UID field, TC-7 the
 * Approved toggle) through the character dialogs, the data cases (TC-3
 * ownerUid materialised, TC-4 player.userUid, TC-5 the approval notice reaches
 * the linked player, TC-6 an unlinked character approves without error)
 * through the OpenRegister object API and the notifications API. TC-8 (the
 * register re-import) is covered by tests/unit/Settings/CharacterPlayerPickerSeedTest.php.
 *
 * As admin, provisions two Nextcloud users and their players; the linked
 * player's own context starts with an empty storage state. Base URL from
 * tests/e2e/_base-url.ts.
 *
 * @spec openspec/changes/character-player-picker/specs/character-management/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	getObject,
	newApi,
	OR_BASE,
	REGISTER_ID,
	resolveSchemaIds,
	RUN_ID,
	SCHEMA_IDS,
} from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' }

const aliceUid = `alice-${RUN_ID}`
const alicePass = `Alice-${RUN_ID}-pw!9`
const bobUid = `bob-${RUN_ID}`
const bobPass = `Bob-${RUN_ID}-pw!9`

let admin: APIRequestContext
let alice: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
const names = {
	alice: fixtureName('Alice'),
	bob: fixtureName('Bob'),
	lancelot: fixtureName('Sir Lancelot'),
	gawain: fixtureName('Sir Gawain'),
	legacy: fixtureName('Legacy Knight'),
}

/**
 * The object URL of one character.
 *
 * @param {string} id Character UUID.
 * @return {string} The URL.
 */
function characterUrl(id: string): string {
	return `${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.character}/${id}`
}

/**
 * The characters with this exact name.
 *
 * @param {string} name The name.
 * @return {Promise<Array<Record<string, unknown>>>} The characters.
 */
async function charactersNamed(name: string): Promise<Array<Record<string, unknown>>> {
	const res = await admin.get(`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.character}?name=${encodeURIComponent(name)}`, { headers: JSON_HEADERS })
	expect(res.ok(), await res.text()).toBe(true)
	const body = await res.json()
	return (Array.isArray(body.results) ? body.results : []).filter((c: Record<string, unknown>) => c.name === name)
}

/**
 * The id of an object as the API returns it.
 *
 * @param {Record<string, unknown>} object The object.
 * @return {string} Its id.
 */
function idOf(object: Record<string, unknown>): string {
	return String((object['@self'] as Record<string, unknown> | undefined)?.id ?? object.id ?? '')
}

/**
 * The subjects of a user's Nextcloud notifications.
 *
 * @param {APIRequestContext} user The user's context.
 * @return {Promise<string[]>} The subjects and messages.
 */
async function notifications(user: APIRequestContext): Promise<string[]> {
	const res = await user.get(`${BASE_URL}/ocs/v2.php/apps/notifications/api/v2/notifications?format=json`, { headers: OCS })
	if (!res.ok()) {
		return []
	}
	const body = await res.json()
	const data = body?.ocs?.data
	return (Array.isArray(data) ? data : []).map((n: Record<string, unknown>) => `${n.subject ?? ''} ${n.message ?? ''}`)
}

/**
 * Open the character list in the app.
 *
 * @param {Page} page The page.
 */
async function openCharacters(page: Page): Promise<void> {
	await page.goto(`${BASE}/characters`)
	await expect(page.locator('.app-content').first()).toBeVisible({ timeout: 30_000 })
}

/**
 * Open the "Add Character" dialog.
 *
 * @param {Page} page The page.
 * @return {Promise<Locator>} The dialog.
 */
async function openCreateDialog(page: Page): Promise<Locator> {
	await openCharacters(page)
	const add = page.locator('.app-content button').filter({ hasText: /Add Character|Personage toevoegen/i }).first()
	await expect(add).toBeVisible({ timeout: 10_000 })
	await add.click()
	const dialog = page.locator('[role="dialog"]').first()
	await expect(dialog).toBeVisible({ timeout: 8_000 })
	return dialog
}

/**
 * Open the edit dialog of one character from its detail page.
 *
 * @param {Page} page The page.
 * @param {string} id The character id.
 * @return {Promise<Locator>} The dialog.
 */
async function openEditDialog(page: Page, id: string): Promise<Locator> {
	await page.goto(`${BASE}/characters/${id}`)
	const actions = page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()
	await expect(actions).toBeVisible({ timeout: 15_000 })
	await actions.click()
	await page.getByRole('menuitem', { name: /^(Edit|Bewerken)$/i }).first().click()
	const dialog = page.locator('[role="dialog"]').first()
	await expect(dialog).toBeVisible({ timeout: 8_000 })
	return dialog
}

/**
 * Save the open form dialog and wait for it to close.
 *
 * @param {Page} page The page.
 * @param {Locator} dialog The dialog.
 */
async function saveDialog(page: Page, dialog: Locator): Promise<void> {
	await dialog.getByRole('button', { name: /^(Save|Create|Opslaan|Aanmaken)$/i }).last().click()
	await expect(dialog).toBeHidden({ timeout: 15_000 })
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)

	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, { headers: OCS, form: { groupid: 'larpers' } })
	for (const [uid, password] of [[aliceUid, alicePass], [bobUid, bobPass]]) {
		const created = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
			headers: OCS,
			form: { userid: uid, password, 'groups[]': 'larpers' },
		})
		expect(created.ok(), await created.text()).toBe(true)
	}
	alice = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: aliceUid, password: alicePass },
	})

	ids.alice = ledger.track('player', await createObject(admin, 'player', { name: names.alice, userUid: aliceUid }))
	ids.bob = ledger.track('player', await createObject(admin, 'player', { name: names.bob }))
})

test.afterAll(async () => {
	for (const name of [names.lancelot]) {
		for (const character of await charactersNamed(name).catch(() => [])) {
			ledger.track('character', idOf(character))
		}
	}
	await cleanupLedger(admin, ledger)
	for (const uid of [aliceUid, bobUid]) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid}`, { headers: OCS })
	}
	await alice?.dispose()
	await admin.dispose()
})

test.describe('character-player-picker', () => {
	test.describe.configure({ mode: 'serial' })

	// TC-1, TC-2: the player is a dropdown of players and stores the player's uuid; no Owner UID field.
	// @e2e openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations
	test('a character is created with its player picked from the players', async ({ page }) => {
		const dialog = await openCreateDialog(page)

		await expect(dialog.locator('#cn-form-ownerUid')).toHaveCount(0)
		await expect(dialog.getByText(/^Owner UID$/)).toHaveCount(0)

		await dialog.getByRole('textbox', { name: /^Name/i }).first().fill(names.lancelot)
		const picker = dialog.locator('#cn-form-ocName')
		await expect(picker).toBeVisible()
		await picker.click()
		await picker.fill(names.alice)
		await page.getByRole('option', { name: new RegExp(names.alice) }).first().click()
		await saveDialog(page, dialog)

		await expect.poll(async () => (await charactersNamed(names.lancelot)).length, { timeout: 15_000 }).toBe(1)
		const [lancelot] = await charactersNamed(names.lancelot)
		ids.lancelot = idOf(lancelot)
		expect(lancelot.ocName).toBe(ids.alice)
	})

	// TC-3: ownerUid follows the linked player's Nextcloud account.
	// @e2e openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-player-reference-resolution-on-character-objects
	test('ownerUid is materialised from the linked player', async () => {
		ids.gawain = ledger.track('character', await createObject(admin, 'character', { name: names.gawain, ocName: ids.alice }))

		const gawain = await getObject(admin, 'character', ids.gawain)
		expect(gawain?.ownerUid).toBe(aliceUid)
	})

	// TC-4: player.userUid links a player to a Nextcloud account.
	// @e2e openspec/changes/character-player-picker/specs/events-players/spec.md#requirement-player-to-nextcloud-account-link
	test('a player is linked to a Nextcloud account', async () => {
		const res = await admin.patch(`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.player}/${ids.bob}`, {
			headers: JSON_HEADERS,
			data: { userUid: bobUid },
		})
		expect(res.ok(), await res.text()).toBe(true)

		const bob = await getObject(admin, 'player', ids.bob)
		expect(bob?.userUid).toBe(bobUid)
	})

	// TC-7: Approved is a toggle and flipping it approves the character.
	// @e2e openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations
	test('Approved renders as a toggle and flipping it approves', async ({ page }) => {
		const id = ids.lancelot ?? ids.gawain
		const dialog = await openEditDialog(page, id)

		const toggle = dialog.locator('#cn-form-approved, [data-testid="cn-form-approved"]').or(dialog.getByRole('switch', { name: /Approved|Goedgekeurd/i })).first()
		await expect(toggle).toBeVisible()
		await expect(dialog.locator('select#cn-form-approved')).toHaveCount(0)
		await toggle.click()
		await saveDialog(page, dialog)

		await expect.poll(async () => (await getObject(admin, 'character', id))?.approved, { timeout: 15_000 }).toBe('approved')
	})

	// TC-5: the approval notice reaches the linked player.
	// @e2e openspec/changes/character-player-picker/specs/notifications/spec.md#requirement-structured-owner-uid-prerequisite-for-per-player-notifications
	test('the linked player is notified when the character is approved', async () => {
		const before = (await notifications(alice)).length
		const res = await admin.patch(characterUrl(ids.gawain), { headers: JSON_HEADERS, data: { approved: 'approved' } })
		expect(res.ok(), await res.text()).toBe(true)

		await expect.poll(async () => (await notifications(alice)).length, { timeout: 30_000 }).toBeGreaterThan(before)
	})

	// TC-6: a character whose player does not resolve approves without error.
	// @e2e openspec/changes/character-player-picker/specs/notifications/spec.md#requirement-structured-owner-uid-prerequisite-for-per-player-notifications
	test('a character without a resolvable player approves without error', async () => {
		ids.legacy = ledger.track('character', await createObject(admin, 'character', {
			name: names.legacy,
			ocName: '00000000-0000-4000-8000-000000000000',
		}).catch(async () => createObject(admin, 'character', { name: names.legacy, ocName: ids.bob })))
		await admin.patch(`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.player}/${ids.bob}`, { headers: JSON_HEADERS, data: { userUid: null } })

		const res = await admin.patch(characterUrl(ids.legacy), { headers: JSON_HEADERS, data: { approved: 'approved' } })
		expect(res.ok(), await res.text()).toBe(true)
		expect((await getObject(admin, 'character', ids.legacy))?.approved).toBe('approved')
	})
})
