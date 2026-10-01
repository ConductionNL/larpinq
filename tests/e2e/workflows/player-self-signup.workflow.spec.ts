/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: players who sign up themselves (players-self-signup).
 *
 * portaliq's writer is stood in for by the admin: it creates the player with
 * a portal subject reference, and the character with `ownerRef`, exactly as
 * the portal's create actions stamp them. What larpinq adds on top is what is
 * proven here: the profile is marked self-registered and awaiting review, a
 * second profile for the same portal account is refused, the portal
 * character is played by its owner, and a game master marks the player
 * reviewed on the New players page. The claim itself is portaliq's and needs a
 * portal instance; PortalProfileListenerTest proves the event larpinq sends.
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	cleanupLedger,
	FixtureLedger,
	fixtureName,
	newApi,
	OR_BASE,
	resolveSchemaIds,
	RUN_ID,
} from './fixtures.ts'

const JSON_HEADERS = {
	Accept: 'application/json',
	'Content-Type': 'application/json',
}
const subject = `portal-subject-${RUN_ID}`

let admin: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Post an object by schema slug (the fixtures' SCHEMA_IDS has no id for
 * every slug, so this posts by slug).
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function post(slug: string, body: Record<string, unknown>) {
	const res = await admin.post(`${OR_BASE}/larpinq/${slug}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * Read an object by schema slug.
 *
 * @param {string} slug The schema slug.
 * @param {string} id The uuid.
 * @return {Promise<any>} The object.
 */
async function read(slug: string, id: string) {
	const res = await admin.get(`${OR_BASE}/larpinq/${slug}/${id}`, {
		headers: JSON_HEADERS,
	})
	expect(res.ok(), await res.text()).toBe(true)
	return res.json()
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('players who sign up themselves', () => {
	// @e2e openspec/specs/portal-contribution/spec.md#lotte-joins-the-campaign
	test('a portal profile is marked self-registered and awaiting review', async () => {
		const created = await post('player', {
			name: fixtureName('Lotte Bakker'),
			portalSubjectRef: subject,
		})
		expect(created.status, JSON.stringify(created.body)).toBeLessThan(300)
		ids.lotte = ledger.track(
			'player',
			String(created.body.id ?? created.body['@self']?.id),
		)

		const lotte = await read('player', ids.lotte)
		expect(lotte.portalSubjectRef).toBe(subject)
		expect(lotte.selfRegistered).toBe(true)
		expect(lotte.awaitingReview).toBe(true)
	})

	// @e2e openspec/specs/portal-contribution/spec.md#a-double-click
	test('a second profile for the same portal account is refused', async () => {
		const second = await post('player', {
			name: fixtureName('Lotte B.'),
			portalSubjectRef: subject,
		})
		expect(second.status).toBeGreaterThanOrEqual(400)
		expect(JSON.stringify(second.body)).toContain('already has a player profile')
	})

	// @e2e openspec/specs/portal-contribution/spec.md#lotte-creates-her-first-character
	test('a character the portal stamps with Lotte is played by Lotte', async () => {
		const created = await post('character', {
			name: fixtureName('Wren'),
			ownerRef: ids.lotte,
		})
		expect(created.status, JSON.stringify(created.body)).toBeLessThan(300)
		ids.wren = ledger.track(
			'character',
			String(created.body.id ?? created.body['@self']?.id),
		)

		expect((await read('character', ids.wren)).ocName).toBe(ids.lotte)
	})

	// @e2e openspec/specs/portal-contribution/spec.md#a-game-master-welcomes-lotte
	test('a game master marks Lotte reviewed on the New players page', async ({
		page,
	}) => {
		await page.goto(`${BASE_URL}/index.php/apps/larpinq/new-players`)
		const row = page.getByRole('row', {
			name: new RegExp(fixtureName('Lotte Bakker')),
		})
		await row.getByRole('checkbox').check()
		await page.getByRole('button', { name: 'Mark reviewed' }).click()
		await expect(row).toHaveCount(0)

		const lotte = await read('player', ids.lotte)
		expect(lotte.awaitingReview).toBe(false)
		expect(lotte.reviewedBy).toBe(process.env.NC_USER || 'admin')
		expect(Date.parse(lotte.reviewedAt)).not.toBeNaN()
	})
})
