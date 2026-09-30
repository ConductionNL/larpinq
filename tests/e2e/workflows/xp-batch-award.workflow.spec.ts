/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: a game master awards XP to a whole event in one save
 * (events-xp-batch-award).
 *
 * As admin (a game master), builds event "Summer Siege" with three
 * characters: Mirela and Bertram checked in, Harrow a no-show. Opens Award
 * XP on the event page, checks the default ticks, saves 5 XP, and re-opens.
 * Then checks the batch endpoint refuses a duplicate, the server stamps who
 * awarded, and player Anna (in `larpers` only) never sees the action.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
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
let eventId = ''
const ids: Record<string, string> = {}

/**
 * The awards for the event, through larpinq's award roster.
 *
 * @return {Promise<Array<Record<string, unknown>>>} The roster rows.
 */
async function awardRows(): Promise<Array<Record<string, unknown>>> {
	const res = await admin.get(
		`${BASE_URL}/index.php${BASE}/api/events/${eventId}/xp-award-roster`,
		{ headers: OCS },
	)
	expect(res.ok(), await res.text()).toBe(true)
	return (await res.json()).rows
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

	eventId = ledger.track(
		'event',
		await createObject(admin, 'event', {
			name: fixtureName('Summer Siege'),
			startDate: '2025-07-01T10:00:00+00:00',
			endDate: '2025-07-03T16:00:00+00:00',
		}),
	)
	for (const [key, name] of [
		['mirela', 'Mirela the Wanderer'],
		['bertram', 'Sir Bertram'],
		['harrow', 'Old Captain Harrow'],
	]) {
		ids[key] = ledger.track(
			'character',
			await createObject(admin, 'character', {
				name: fixtureName(name),
				events: [eventId],
			}),
		)
	}
	for (const [key, status] of [
		['mirela', 'checked-in'],
		['bertram', 'checked-in'],
		['harrow', 'no-show'],
	]) {
		const res = await admin.post(
			`${BASE_URL}/index.php${BASE}/api/events/${eventId}/attendance`,
			{ headers: OCS, data: { character: ids[key], status } },
		)
		expect(res.ok(), await res.text()).toBe(true)
	}
})

test.afterAll(async () => {
	for (const row of eventId ? await awardRows() : []) {
		for (const award of (row.awards as Array<{ id: string }>) ?? []) {
			ledger.track('xpAward', award.id)
		}
	}
	await cleanupLedger(admin, ledger)
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${annaUid}`, {
		headers: OCS,
	})
	await anna.dispose()
	await admin.dispose()
})

test.describe('award XP to a whole event', () => {
	// @e2e openspec/specs/event-xp-awards/spec.md#attendance-decides-the-default-ticks
	// @e2e openspec/specs/event-xp-awards/spec.md#batch-award-after-the-event
	// @e2e openspec/specs/event-xp-awards/spec.md#re-opening-does-not-double-award-by-default
	test('checked-in characters start ticked, and a save awards them', async ({
		page,
	}) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/events/${eventId}`)
		await page.getByRole('button', { name: 'Actions' }).click()
		await page.getByText('Award XP').click()
		const dialog = page.getByTestId('xp-award-dialog')
		const tick = (key: string) =>
			dialog.getByTestId(`xp-award-row-${ids[key]}`).getByRole('checkbox')
		await expect(tick('mirela')).toBeChecked()
		await expect(tick('bertram')).toBeChecked()
		await expect(tick('harrow')).not.toBeChecked()

		await dialog
			.getByTestId('xp-award-default-amount')
			.locator('input')
			.fill('5')
		await dialog.getByTestId('xp-award-save').click()
		await expect(dialog.getByTestId('xp-award-result')).toContainText('2')
		await expect(tick('mirela')).not.toBeChecked()
		await expect(tick('bertram')).not.toBeChecked()

		const rows = await awardRows()
		const mirela = rows.find((row) => row.character === ids.mirela)
		expect((mirela?.awards as Array<{ amount: number }>)[0].amount).toBe(5)
	})

	// @e2e openspec/specs/event-xp-awards/spec.md#one-duplicate-in-the-batch
	// @e2e openspec/specs/event-xp-awards/spec.md#a-client-sends-its-own-provenance
	test('a second award is refused, and the server stamps who awarded', async () => {
		const res = await admin.post(
			`${BASE_URL}/index.php${BASE}/api/events/${eventId}/xp-awards`,
			{
				headers: OCS,
				data: {
					rows: [
						{ character: ids.harrow, amount: 1 },
						{ character: ids.bertram, amount: 1 },
					],
				},
			},
		)
		expect(res.ok(), await res.text()).toBe(true)
		const body = await res.json()
		expect(body.created).toHaveLength(1)
		expect(body.refused).toEqual([
			{ character: ids.bertram, reason: 'duplicate' },
		])

		const forged = await admin.post(
			`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.xpAward ?? 'xpAward'}`,
			{
				headers: OCS,
				data: {
					event: eventId,
					character: ids.harrow,
					amount: 1,
					awardedBy: annaUid,
				},
			},
		)
		expect(forged.ok(), await forged.text()).toBe(true)
		expect((await forged.json()).awardedBy).not.toBe(annaUid)
	})

	// @e2e openspec/specs/event-xp-awards/spec.md#non-gm-does-not-see-the-awarding-surface
	test('a player is refused and never offered the action', async () => {
		const access = await anna.get(
			`${BASE_URL}/index.php${BASE}/api/xp-awards/access`,
			{ headers: OCS },
		)
		expect((await access.json()).allowed).toBe(false)
		const res = await anna.post(
			`${BASE_URL}/index.php${BASE}/api/events/${eventId}/xp-awards`,
			{ headers: OCS, data: { rows: [{ character: ids.mirela, amount: 5 }] } },
		)
		expect(res.status()).toBe(403)
	})
})
