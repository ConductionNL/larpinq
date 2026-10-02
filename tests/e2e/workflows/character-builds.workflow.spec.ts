/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: builds of a character (characters-multiple-builds).
 *
 * As admin (a game master), sets up an XP ability, four skills costing 10,
 * 10, 10 and 35 XP, and Mirela the Wanderer (owned by player anna, in
 * `larpers`) with Swordsmanship and two XP awards of 20. Then works through
 * OpenRegister and the build report as anna, as karel (another player), and as
 * the game master who applies the build.
 *
 * Objects are made by register and schema slug, so the test needs no schema
 * id configuration.
 *
 * @spec openspec/specs/character-builds/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import { fixtureName, newApi, RUN_ID } from './fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const BUILDS = `${OBJECTS}/larping_character_build`
function reportUrl(id: string): string {
	return `${BASE_URL}/index.php/apps/larpinq/api/builds/${id}/report`
}

const players = ['anna', 'karel'] as const
type Player = (typeof players)[number]
const uid = (p: Player): string => `${p}-${RUN_ID}`

let admin: APIRequestContext
const as: Partial<Record<Player, APIRequestContext>> = {}
const created: string[] = []
const ids: Record<string, string> = {}

/**
 * Create a build through OpenRegister as one user, tracked for clean-up.
 *
 * @param {APIRequestContext} api The user.
 * @param {object} data The build.
 * @return {Promise<string>} The id.
 */
async function build(
	api: APIRequestContext,
	data: Record<string, unknown>,
): Promise<string> {
	const res = await api.post(BUILDS, {
		headers: { 'OCS-APIRequest': 'true' },
		data,
	})
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	created.push(`${BUILDS}/${id}`)
	return id
}

/**
 * The skill ids of a character as the admin reads it.
 *
 * @param {string} id The character.
 * @return {Promise<string[]>} The skill ids, sorted.
 */
async function skillsOf(id: string): Promise<string[]> {
	const res = await admin.get(`${OBJECTS}/character/${id}`, { headers: OCS })
	const character = await res.json()
	const skills = (character?.skills ?? []) as Array<string | { id: string }>
	return [...skills].map((s) => (typeof s === 'string' ? s : s.id)).sort()
}

test.beforeAll(async () => {
	admin = await newApi()
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
		headers: OCS,
		form: { groupid: 'larpers' },
	})
	for (const p of players) {
		const made = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
			headers: OCS,
			form: {
				userid: uid(p),
				password: `${uid(p)}-pw!9`,
				'groups[]': 'larpers',
			},
		})
		expect(made.ok(), await made.text()).toBe(true)
		as[p] = await request.newContext({
			httpCredentials: { username: uid(p), password: `${uid(p)}-pw!9` },
		})
	}

	const make = async (slug: string, body: object): Promise<string> => {
		const res = await admin.post(`${OBJECTS}/${slug}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: body,
		})
		expect(res.ok(), await res.text()).toBe(true)
		const json = await res.json()
		const id = json?.id ?? json?.['@self']?.id
		created.push(`${OBJECTS}/${slug}/${id}`)
		return id
	}
	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.xp = await make('ability', {
		name: fixtureName('experience points'),
		base: 0,
	})
	const cost = async (label: string, xp: number): Promise<string> =>
		make('effect', {
			name: fixtureName(label),
			modifier: xp,
			modification: 'negative',
			abilities: [ids.xp],
		})
	const skill = async (label: string, xp: number): Promise<string> =>
		make('larping_skill', {
			name: fixtureName(label),
			setting: ids.world,
			effects: [await cost(`${label} cost`, xp)],
		})
	ids.sword = await skill('Swordsmanship', 10)
	ids.herb = await skill('Herbalism', 10)
	ids.potion = await skill('Potion brewing', 10)
	ids.riding = await skill('Riding', 35)
	ids.mirela = await make('character', {
		name: fixtureName('Mirela the Wanderer'),
		setting: ids.world,
		ownerUid: uid('anna'),
		skills: [ids.sword],
	})
	for (const reason of ['Summer Siege', 'Winter Moot']) {
		await make('xpAward', { character: ids.mirela, amount: 20, reason })
	}
})

test.afterAll(async () => {
	for (const url of created.reverse()) {
		await admin.delete(url, { headers: { 'OCS-APIRequest': 'true' } })
	}
	for (const p of players) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid(p)}`, {
			headers: OCS,
		})
		await as[p]?.dispose()
	}
	await admin.dispose()
})

test.describe.serial('builds of a character', () => {
	// @e2e openspec/specs/character-builds/spec.md#anna-plans-the-alchemist-path
	test('anna plans the alchemist path without changing the sheet', async () => {
		ids.alchemist = await build(as.anna!, {
			character: ids.mirela,
			name: 'Alchemist path',
			purpose: 'plan',
			skills: [ids.herb, ids.potion],
		})
		const list = await as.anna!.get(
			`${BUILDS}?character=${ids.mirela}&_limit=50`,
			{
				headers: OCS,
			},
		)
		expect(
			(await list.json()).results.map((row: { name: string }) => row.name),
		).toContain('Alchemist path')
		expect(await skillsOf(ids.mirela)).toEqual([ids.sword])
	})

	// @e2e openspec/specs/character-builds/spec.md#a-build-that-costs-too-much
	test('the winter campaign build is 5 XP short and nothing changes', async () => {
		ids.winter = await build(as.anna!, {
			character: ids.mirela,
			name: 'Winter campaign',
			purpose: 'other-game',
			skills: [ids.sword, ids.riding],
		})
		const res = await as.anna!.get(reportUrl(ids.winter), { headers: OCS })
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.report.valid).toBe(false)
		expect(body.report.budget.shortfall).toBe(5)
		expect(await skillsOf(ids.mirela)).toEqual([ids.sword])
	})

	// @e2e openspec/specs/character-builds/spec.md#another-player-looks
	test('another player gets 404 and cannot make builds for mirela', async () => {
		const res = await as.karel!.get(reportUrl(ids.alchemist), { headers: OCS })
		expect(res.status()).toBe(404)
		const made = await as.karel!.post(BUILDS, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { character: ids.mirela, name: 'Not mine', purpose: 'test' },
		})
		expect(made.ok()).toBe(false)
	})

	// @e2e openspec/specs/character-builds/spec.md#the-choice-is-made
	test('a game master applies the alchemist path', async () => {
		const report = await (
			await admin.get(reportUrl(ids.alchemist), { headers: OCS })
		).json()
		expect(report.report.valid).toBe(true)
		expect(
			report.changes.skills.removed.map((s: { id: string }) => s.id),
		).toEqual([ids.sword])
		const patched = await admin.patch(`${OBJECTS}/character/${ids.mirela}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: report.lists,
		})
		expect(patched.ok(), await patched.text()).toBe(true)
		expect(await skillsOf(ids.mirela)).toEqual([ids.herb, ids.potion].sort())
	})
})
