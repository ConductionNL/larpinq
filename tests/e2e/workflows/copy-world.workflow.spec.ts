/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: a game master copies a world's rules into a new world
 * (worlds-copy-ruleset).
 *
 * As admin (a game master), builds world Aldmoor with an ability, an effect
 * on it, two skills where "Master swordsman" requires "Swordsmanship", and an
 * item held by a character. Copies it through the dialog on the world page,
 * then reads the copy through OpenRegister. Player Anna (in `larpers` only)
 * gets 403 on the copy endpoint.
 *
 * @spec openspec/specs/setting-management/spec.md
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
	OR_BASE,
	REGISTER_ID,
	resolveSchemaIds,
	RUN_ID,
	SCHEMA_IDS,
} from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`

let admin: APIRequestContext
let anna: APIRequestContext
const ledger = new FixtureLedger()
let worldId = ''
let copyId = ''
const ids: Record<string, string> = {}

/**
 * The objects of one type in one world.
 *
 * @param {string} type The larpinq type.
 * @param {string} world The world id.
 * @return {Promise<Array<Record<string, unknown>>>} The objects.
 */
async function inWorld(
	type: string,
	world: string,
): Promise<Array<Record<string, unknown>>> {
	const url = `${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS[type]}?setting=${world}&_limit=100`
	const res = await admin.get(url, { headers: OCS })
	expect(res.ok(), await res.text()).toBe(true)
	return (await res.json())?.results ?? []
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
	const setting = worldId
	ids.strength = ledger.track(
		'ability',
		await createObject(admin, 'ability', { name: 'Strength', setting }),
	)
	ids.effect = ledger.track(
		'effect',
		await createObject(admin, 'effect', {
			name: 'Strength +3',
			setting,
			abilities: [ids.strength],
		}),
	)
	ids.sword = ledger.track(
		'skill',
		await createObject(admin, 'skill', {
			name: 'Swordsmanship',
			setting,
			effects: [ids.effect],
		}),
	)
	ids.master = ledger.track(
		'skill',
		await createObject(admin, 'skill', {
			name: 'Master swordsman',
			setting,
			requiredSkills: [ids.sword],
		}),
	)
	ids.hero = ledger.track(
		'character',
		await createObject(admin, 'character', { name: fixtureName('Hero'), setting }),
	)
	ids.shield = ledger.track(
		'item',
		await createObject(admin, 'item', {
			name: 'Iron shield',
			setting,
			characters: [ids.hero],
		}),
	)
})

test.afterAll(async () => {
	if (copyId) {
		for (const type of ['item', 'skill', 'effect', 'ability']) {
			for (const row of await inWorld(type, copyId)) {
				ledger.track(type, String(row.id ?? row['@self']?.id))
			}
		}
		ledger.track('setting', copyId)
	}
	await cleanupLedger(admin, ledger)
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${annaUid}`, {
		headers: OCS,
	})
	await anna.dispose()
	await admin.dispose()
})

test.describe('copy a world', () => {
	// @e2e openspec/specs/setting-management/spec.md#a-player-tries-to-copy
	test('a player gets 403 and no world is made', async () => {
		const res = await anna.post(
			`${BASE_URL}/index.php${BASE}/api/worlds/${worldId}/copy`,
			{ headers: OCS, data: { name: 'Stolen season' } },
		)
		expect(res.status()).toBe(403)
	})

	// @e2e openspec/specs/setting-management/spec.md#a-new-season-on-the-same-rules
	test('a game master copies the world from its page', async ({ page }) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/settings/${worldId}`)
		await page.getByRole('button', { name: 'Actions' }).click()
		await page.getByText('Copy world').click()
		const dialog = page.getByTestId('copy-world-dialog')
		await expect(dialog.getByTestId('copy-world-counts')).toContainText('Skills: 2')
		const name = dialog.getByTestId('copy-world-name').locator('input')
		await name.fill(`Aldmoor season 2 ${RUN_ID}`)
		await dialog.getByTestId('copy-world-confirm').click()
		await expect(dialog.getByTestId('copy-world-done')).toBeVisible()
		await dialog.getByTestId('copy-world-open').click()
		await expect(page).toHaveURL(/\/settings\/[0-9a-f-]{36}$/)
		copyId = page.url().split('/').pop() ?? ''
		expect(copyId).not.toBe(worldId)

		expect(await inWorld('skill', copyId)).toHaveLength(2)
		expect(await inWorld('ability', copyId)).toHaveLength(1)
		expect(await inWorld('character', copyId)).toHaveLength(0)
		const [shield] = await inWorld('item', copyId)
		expect(shield.characters ?? []).toEqual([])
	})

	// @e2e openspec/specs/setting-management/spec.md#a-prerequisite-follows-the-copy
	test('the copied prerequisite points at the copied skill', async () => {
		expect(copyId).not.toBe('')
		const skills = await inWorld('skill', copyId)
		const byName = Object.fromEntries(skills.map((row) => [row.name, row]))
		const copiedSword = String(byName.Swordsmanship.id)
		expect(copiedSword).not.toBe(ids.sword)
		expect(byName['Master swordsman'].requiredSkills).toEqual([copiedSword])
		const [effect] = await inWorld('effect', copyId)
		expect(byName.Swordsmanship.effects).toEqual([String(effect.id)])
	})
})
