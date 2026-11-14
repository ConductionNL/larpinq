/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: extra character fields per world (characters-custom-fields).
 *
 * As admin (a game master), builds world Aldmoor with a "Bloodline" choice
 * field, a "Patron god" text field, a "Scars" number field and a "True
 * allegiance" field for game masters, and a character owned by player Anna
 * (a Nextcloud user in `larpers`). Then works through OpenRegister as Anna,
 * the path every Larpinq screen uses, and opens the Extra fields tab.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
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
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const FIELDS = `${OBJECTS}/larping_character_field`

const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`

let admin: APIRequestContext
let anna: APIRequestContext
const ledger = new FixtureLedger()
const fields: string[] = []
let worldId = ''
let mirelaId = ''

/**
 * Create one field definition as admin.
 *
 * @param {object} body The definition.
 * @return {Promise<string>} Its id.
 */
async function createField(body: Record<string, unknown>): Promise<string> {
	const res = await admin.post(FIELDS, {
		headers: { 'OCS-APIRequest': 'true' },
		data: { setting: worldId, visibility: 'owner', ...body },
	})
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	fields.push(id)
	return id
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
	await createField({
		label: 'Patron god',
		key: 'patron-god',
		fieldType: 'text',
		order: 2,
	})
	await createField({
		label: 'Scars',
		key: 'scars',
		fieldType: 'number',
		order: 3,
	})
	await createField({
		label: 'True allegiance',
		key: 'true-allegiance',
		fieldType: 'choice',
		choices: ['crown', 'rebels', 'none'],
		visibility: 'gamemasters',
		order: 4,
	})
	mirelaId = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: fixtureName('Mirela the Wanderer'),
			setting: worldId,
			ownerUid: annaUid,
			customFieldsPrivate: { 'true-allegiance': 'rebels' },
		}),
	)
})

test.afterAll(async () => {
	for (const id of fields) {
		await admin.delete(`${FIELDS}/${id}`, {
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

test.describe('extra character fields', () => {
	// @e2e openspec/specs/character-custom-fields/spec.md#a-game-master-adds-a-bloodline-field
	test('a new field shows on the character page', async ({ page }) => {
		await createField({
			label: 'Bloodline',
			key: 'bloodline',
			fieldType: 'choice',
			choices: ['human', 'elven', 'dwarven'],
			order: 1,
		})
		await page.goto(`${BASE_URL}/index.php${BASE}/characters/${mirelaId}`)
		await page.getByRole('tab', { name: 'Extra fields' }).click()
		await expect(page.getByTestId('custom-field-bloodline')).toBeVisible()
		await expect(page.getByTestId('custom-field-true-allegiance')).toBeVisible()
	})

	// @e2e openspec/specs/character-custom-fields/spec.md#a-player-fills-in-her-patron-god
	test('the player fills in her own field', async () => {
		const res = await anna.patch(`${OBJECTS}/character/${mirelaId}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { customFields: { 'patron-god': 'The Grey Lady' } },
		})
		expect(res.ok(), await res.text()).toBe(true)
		const read = await admin.get(`${OBJECTS}/character/${mirelaId}`, {
			headers: OCS,
		})
		expect((await read.json()).customFields['patron-god']).toBe('The Grey Lady')
	})

	// @e2e openspec/specs/character-custom-fields/spec.md#a-secret-allegiance
	test('the player never gets the private field or value', async () => {
		const read = await anna.get(`${OBJECTS}/character/${mirelaId}`, {
			headers: OCS,
		})
		expect(read.ok()).toBe(true)
		expect((await read.json()).customFieldsPrivate ?? null).toBe(null)
		const list = await anna.get(`${FIELDS}?setting=${worldId}&_limit=100`, {
			headers: OCS,
		})
		const labels = ((await list.json())?.results ?? []).map(
			(row: { label: string }) => row.label,
		)
		expect(labels).not.toContain('True allegiance')
	})

	// @e2e openspec/specs/character-custom-fields/spec.md#text-in-a-number-field
	test('text in a number field is refused by key', async () => {
		const res = await admin.patch(`${OBJECTS}/character/${mirelaId}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { customFields: { scars: 'many' } },
		})
		expect(res.ok()).toBe(false)
		expect(await res.text()).toContain('scars')
	})
})
