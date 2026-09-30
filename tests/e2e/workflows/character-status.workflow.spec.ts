/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: character status and bulk edit (characters-status-and-bulk-edit).
 *
 * As a game master (a user in `gamemasters`), sets statuses, writes several
 * characters one PATCH each the way the Edit selected dialog does, and checks
 * that a dead character is refused a new event and a retired one is left out
 * of the participant filter. Objects are made by register and schema slug.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import { fixtureName, newApi, RUN_ID } from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const CHARACTERS = `${OBJECTS}/character`
const GM = `gm-${RUN_ID}`

let admin: APIRequestContext
let gm: APIRequestContext
const created: string[] = []
const ids: Record<string, string> = {}

/**
 * Create one object as the admin, tracked for clean-up.
 *
 * @param {string} slug The schema slug.
 * @param {object} data The object.
 * @return {Promise<string>} The id.
 */
async function make(slug: string, data: Record<string, unknown>): Promise<string> {
	const res = await admin.post(`${OBJECTS}/${slug}`, {
		headers: { 'OCS-APIRequest': 'true' },
		data,
	})
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	created.push(`${OBJECTS}/${slug}/${id}`)
	return id
}

/**
 * Patch one character as the game master.
 *
 * @param {string} id The character.
 * @param {object} data The fields.
 * @return {Promise<import('@playwright/test').APIResponse>} The response.
 */
async function patch(id: string, data: Record<string, unknown>) {
	return gm.patch(`${CHARACTERS}/${id}`, {
		headers: { 'OCS-APIRequest': 'true' },
		data,
	})
}

/**
 * The status of a character as the admin reads it.
 *
 * @param {string} id The character.
 * @return {Promise<string>} The status.
 */
async function statusOf(id: string): Promise<string> {
	const res = await admin.get(`${CHARACTERS}/${id}`, { headers: OCS })
	return (await res.json()).status
}

test.beforeAll(async () => {
	admin = await newApi()
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
		headers: OCS,
		form: { groupid: 'gamemasters' },
	})
	const made = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
		headers: OCS,
		form: { userid: GM, password: `${GM}-pw!9`, 'groups[]': 'gamemasters' },
	})
	expect(made.ok(), await made.text()).toBe(true)
	gm = await request.newContext({
		httpCredentials: { username: GM, password: `${GM}-pw!9` },
	})

	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	for (const [key, name] of [
		['harrow', 'Old Captain Harrow'],
		['venn', 'Lady Venn'],
		['tomas', 'Tomas'],
		['aldric', 'Brother Aldric'],
	]) {
		ids[key] = await make('character', {
			name: fixtureName(name),
			setting: ids.world,
			status: 'active',
		})
	}
	ids.winter = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
	})
})

test.afterAll(async () => {
	for (const url of created.reverse()) {
		await admin.delete(url, { headers: { 'OCS-APIRequest': 'true' } })
	}
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${GM}`, { headers: OCS })
	await gm?.dispose()
	await admin.dispose()
})

test.describe.serial('character status and bulk edit', () => {
	// @e2e openspec/specs/character-management/spec.md#a-game-master-marks-a-fallen-character-dead
	test('a game master marks Brother Aldric dead and the filter finds him', async () => {
		const res = await patch(ids.aldric, { status: 'dead' })
		expect(res.ok(), await res.text()).toBe(true)
		expect(await statusOf(ids.aldric)).toBe('dead')
		const list = await admin.get(
			`${CHARACTERS}?status=dead&setting=${ids.world}&_limit=50`,
			{
				headers: OCS,
			},
		)
		expect(
			(await list.json()).results.map((c: { id: string }) => c.id),
		).toContain(ids.aldric)
	})

	// @e2e openspec/specs/character-management/spec.md#end-of-season-clean-up
	test('three selected characters are retired, one write each', async () => {
		for (const key of ['harrow', 'venn', 'tomas']) {
			const res = await patch(ids[key], { status: 'retired' })
			expect(res.ok(), await res.text()).toBe(true)
		}
		for (const key of ['harrow', 'venn', 'tomas']) {
			expect(await statusOf(ids[key])).toBe('retired')
		}
	})

	// @e2e openspec/specs/character-management/spec.md#a-retired-captain-is-not-offered-for-the-next-event
	test('the participant filter leaves out the retired captain', async () => {
		// The event form asks for characters with the filter of
		// event.players: the event's world and status active.
		const options = await gm.get(
			`${CHARACTERS}?setting=${ids.world}&status=active&_limit=50`,
			{ headers: OCS },
		)
		const offered = (await options.json()).results.map(
			(c: { id: string }) => c.id,
		)
		expect(offered).not.toContain(ids.harrow)
	})

	// @e2e openspec/specs/character-management/spec.md#the-api-refuses-a-dead-character
	test('a dead character is refused a new event on events', async () => {
		const res = await patch(ids.aldric, { events: [ids.winter] })
		expect(res.ok()).toBe(false)
		expect(await res.text()).toContain('events')
	})

	// @e2e openspec/specs/character-management/spec.md#one-character-is-locked
	test('a locked character is refused and the other is still written', async () => {
		await patch(ids.harrow, { status: 'active' })
		await patch(ids.venn, { status: 'active' })
		const lock = await admin.post(`${CHARACTERS}/${ids.venn}/lock`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		expect(lock.ok(), await lock.text()).toBe(true)
		const harrow = await patch(ids.harrow, { status: 'retired' })
		const venn = await patch(ids.venn, { status: 'retired' })
		expect(harrow.ok()).toBe(true)
		expect(venn.ok()).toBe(false)
		expect(await statusOf(ids.harrow)).toBe('retired')
		expect(await statusOf(ids.venn)).toBe('active')
		await admin.post(`${CHARACTERS}/${ids.venn}/unlock`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
	})
})
