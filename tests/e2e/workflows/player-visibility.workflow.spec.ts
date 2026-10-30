/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: a player sees their own sheet and other approved characters as cast entries.
 *
 * As admin, provisions player Anna (Nextcloud user in `larpers`), her player
 * object and character "Mirela the Wanderer", plus an approved "Sir Bertram"
 * and a draft "Nameless Stranger" owned by another player. Then reads and
 * writes as Anna through the OpenRegister object API. The rules live in
 * lib/Settings/register.d/characters-player-visibility.json; this is the live
 * proof that OpenRegister applies them.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
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
const JSON_HEADERS = { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' }

const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`

let admin: APIRequestContext
let anna: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
const names = {
	mirela: fixtureName('Mirela the Wanderer'),
	bertram: fixtureName('Sir Bertram'),
	stranger: fixtureName('Nameless Stranger'),
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

	const annaPlayer = ledger.track(
		'player',
		await createObject(admin, 'player', {
			name: fixtureName('Anna de Vries'),
			userUid: annaUid,
		}),
	)
	const otherPlayer = ledger.track(
		'player',
		await createObject(admin, 'player', { name: fixtureName('Other player') }),
	)

	ids.mirela = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: names.mirela,
			ocName: annaPlayer,
			approved: 'approved',
			background: 'Raised by river folk.',
			slNotesPrivate: 'Secretly the heir of Aldmoor',
			gold: 3,
		}),
	)
	ids.bertram = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: names.bertram,
			ocName: otherPlayer,
			approved: 'approved',
			type: 'player',
			description: 'A knight of the old order.',
			background: 'Lost his lands at Aldmoor.',
		}),
	)
	ids.stranger = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: names.stranger,
			ocName: otherPlayer,
			approved: 'no',
		}),
	)
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${annaUid}`, {
		headers: OCS,
	})
	await anna?.dispose()
	await admin.dispose()
})

test.describe('characters-player-visibility', () => {
	// @e2e openspec/specs/character-management/spec.md#a-player-edits-her-own-background
	test('a player edits her own background', async () => {
		const res = await anna.patch(characterUrl(ids.mirela), {
			headers: JSON_HEADERS,
			data: { background: 'Raised by river folk, far from court.' },
		})

		expect(res.ok(), await res.text()).toBe(true)
	})

	// @e2e openspec/specs/character-management/spec.md#a-player-cannot-edit-someone-elses-sheet
	test("a player cannot edit someone else's sheet", async () => {
		const res = await anna.patch(characterUrl(ids.bertram), {
			headers: JSON_HEADERS,
			data: { description: 'Changed by Anna' },
		})

		expect(res.ok()).toBe(false)
		const stored = await (
			await admin.get(characterUrl(ids.bertram), { headers: JSON_HEADERS })
		).json()
		expect(stored.description).toBe('A knight of the old order.')
	})

	// @e2e openspec/specs/character-management/spec.md#a-player-opens-another-approved-character
	test('a player opens another approved character as a cast entry', async () => {
		const res = await anna.get(characterUrl(ids.bertram), {
			headers: JSON_HEADERS,
		})

		expect(res.ok()).toBe(true)
		const body = await res.json()
		expect(body.name).toBe(names.bertram)
		expect(body.description).toBe('A knight of the old order.')
		expect(body.background).toBeUndefined()
		expect(body.skills).toBeUndefined()
	})

	// @e2e openspec/specs/character-management/spec.md#a-draft-character-stays-hidden
	test('a draft character stays hidden', async () => {
		const res = await anna.get(
			`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.character}?_search=${encodeURIComponent(names.stranger)}`,
			{ headers: JSON_HEADERS },
		)

		expect(res.ok()).toBe(true)
		const results = (await res.json()).results ?? []
		expect(results.map((row: { name?: string }) => row.name)).not.toContain(
			names.stranger,
		)
	})

	// @e2e openspec/specs/character-management/spec.md#a-secret-stays-secret-on-the-players-own-sheet
	test("a secret stays secret on the player's own sheet", async () => {
		const body = await (
			await anna.get(characterUrl(ids.mirela), { headers: JSON_HEADERS })
		).json()

		expect(body.name).toBe(names.mirela)
		expect(body.slNotesPrivate).toBeUndefined()
	})

	// @e2e openspec/specs/character-management/spec.md#a-game-master-still-sees-the-note
	test('a game master still sees the note', async () => {
		const body = await (
			await admin.get(characterUrl(ids.mirela), { headers: JSON_HEADERS })
		).json()

		expect(body.slNotesPrivate).toBe('Secretly the heir of Aldmoor')
	})

	// @e2e openspec/specs/character-management/spec.md#a-player-tries-to-give-herself-gold
	test('a player tries to give herself gold', async () => {
		const res = await anna.patch(characterUrl(ids.mirela), {
			headers: JSON_HEADERS,
			data: { gold: 300 },
		})

		expect(res.ok()).toBe(false)
		const stored = await (
			await admin.get(characterUrl(ids.mirela), { headers: JSON_HEADERS })
		).json()
		expect(stored.gold).toBe(3)
	})
	// @e2e openspec/specs/character-management/spec.md#a-player-studies-the-cast-before-an-event
	test('a player studies the cast before an event (the Cast page query)', async () => {
		const res = await anna.get(
			`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS.character}?approved=approved&_limit=500`,
			{ headers: JSON_HEADERS },
		)

		expect(res.ok()).toBe(true)
		const listed = ((await res.json()).results ?? []).map(
			(row: { name?: string }) => row.name,
		)
		expect(listed).toEqual(expect.arrayContaining([names.mirela, names.bertram]))
		expect(listed).not.toContain(names.stranger)
	})
})
