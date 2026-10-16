/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: game masters import and write the rules, players read them
 * (DECISIONS rows 25 and 27, data-portability REQ-AIE-002 and REQ-AIE-004),
 * and XP awards read by game masters only (DECISIONS row 30).
 *
 * As admin, provisions a game master (in `gamemasters`, not an admin) and a
 * player (in `larpers`), then works through OpenRegister as each of them.
 *
 * @spec openspec/specs/data-portability/spec.md
 * @spec openspec/specs/event-xp-awards/spec.md
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
	resolveSchemaIds,
	RUN_ID,
} from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const OR_API = `${BASE_URL}/index.php/apps/openregister/api`
const SKILLS = `${OR_API}/objects/larpinq/larping_skill`
const OBJECTS = `${OR_API}/objects/larpinq`

const people = { gm: 'gamemasters', player: 'larpers' } as const
type Who = keyof typeof people
const uid = (who: Who): string => `${who}-${RUN_ID}`

let admin: APIRequestContext
const as: Partial<Record<Who, APIRequestContext>> = {}
const ledger = new FixtureLedger()
let worldId = ''
let skillId = ''

/**
 * Import a two-row players CSV through the register import as one user.
 *
 * @param {APIRequestContext} api The user.
 * @return {Promise<import('@playwright/test').APIResponse>} The response.
 */
async function importPlayers(api: APIRequestContext) {
	const csv = `name,description\n${fixtureName('Anna')},Imported\n${fixtureName('Bram')},Imported\n`
	return api.post(`${OR_API}/registers/larpinq/import?schema=player`, {
		headers: { 'OCS-APIRequest': 'true' },
		multipart: {
			schema: 'player',
			file: {
				name: 'players.csv',
				mimeType: 'text/csv',
				buffer: Buffer.from(csv),
			},
		},
	})
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	for (const [who, group] of Object.entries(people) as Array<[Who, string]>) {
		await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
			headers: OCS,
			form: { groupid: group },
		})
		const made = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
			headers: OCS,
			form: {
				userid: uid(who),
				password: `${uid(who)}-pw!9`,
				'groups[]': group,
			},
		})
		expect(made.ok(), await made.text()).toBe(true)
		as[who] = await request.newContext({
			httpCredentials: { username: uid(who), password: `${uid(who)}-pw!9` },
		})
	}
	worldId = ledger.track(
		'setting',
		await createObject(admin, 'setting', { name: fixtureName('Aldmoor') }),
	)
	skillId = ledger.track(
		'skill',
		await createObject(admin, 'skill', {
			name: fixtureName('Swordsmanship'),
			setting: worldId,
		}),
	)
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	for (const who of Object.keys(people) as Who[]) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid(who)}`, {
			headers: OCS,
		})
		await as[who]?.dispose()
	}
	await admin.dispose()
})

test.describe('game masters import and write the rules', () => {
	// @e2e openspec/specs/data-portability/spec.md#a-game-master-imports
	test('a game master imports, a player is refused', async () => {
		const res = await importPlayers(as.gm!)
		expect(res.status(), await res.text()).toBe(200)
		const refused = await importPlayers(as.player!)
		expect(refused.ok()).toBe(false)
	})

	// @e2e openspec/specs/data-portability/spec.md#a-player-reads-the-rules-but-cannot-change-them
	test('a player reads the skills and cannot write them', async () => {
		const read = await as.player!.get(`${SKILLS}/${skillId}`, { headers: OCS })
		expect(read.ok(), await read.text()).toBe(true)
		const change = await as.player!.patch(`${SKILLS}/${skillId}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { description: 'Changed by a player' },
		})
		expect(change.ok()).toBe(false)
		const add = await as.player!.post(SKILLS, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { name: fixtureName('Lockpicking'), setting: worldId },
		})
		expect(add.ok()).toBe(false)
	})

	// @e2e openspec/specs/data-portability/spec.md#a-game-master-changes-a-rule
	test('a game master changes a skill', async () => {
		const change = await as.gm!.patch(`${SKILLS}/${skillId}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { description: 'Changed by the game master' },
		})
		expect(change.ok(), await change.text()).toBe(true)
	})
})

test.describe('game masters read XP awards, other players do not', () => {
	// @e2e openspec/specs/event-xp-awards/spec.md#a-player-cannot-read-other-awards-through-the-objects-api
	test('a player is refused an award, a game master reads it', async () => {
		const made: Array<[string, string]> = []
		const post = async (
			slug: string,
			data: Record<string, unknown>,
		): Promise<string> => {
			const res = await admin.post(`${OBJECTS}/${slug}`, {
				headers: { 'OCS-APIRequest': 'true' },
				data,
			})
			expect(res.ok(), await res.text()).toBe(true)
			const id = String((await res.json()).id)
			made.push([slug, id])
			return id
		}
		try {
			const event = await post('larping_event', {
				name: fixtureName('Harvest moot'),
			})
			const character = await post('character', {
				name: fixtureName('Mirela'),
				ocName: crypto.randomUUID(),
			})
			const award = await post('xpAward', {
				event,
				character,
				amount: 10,
				reason: 'Attended',
			})

			const refused = await as.player!.get(`${OBJECTS}/xpAward/${award}`, {
				headers: OCS,
			})
			expect(refused.ok()).toBe(false)
			const read = await as.gm!.get(`${OBJECTS}/xpAward/${award}`, {
				headers: OCS,
			})
			expect(read.ok(), await read.text()).toBe(true)
		} finally {
			for (const [slug, id] of made.reverse()) {
				await admin.delete(`${OBJECTS}/${slug}/${id}`, {
					headers: { 'OCS-APIRequest': 'true' },
				})
			}
		}
	})
})
