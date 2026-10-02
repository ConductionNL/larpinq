/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: factions, player groups and relationships
 * (characters-factions-and-relationships).
 *
 * As admin (a game master), builds world Aldmoor with four characters owned by
 * three players in `larpers`: Tomas (tom), Lady Venn and Mirela (vera) and Old
 * Captain Harrow (karel), and Sir Bertram (admin). Then works through
 * OpenRegister as each player, the path every Larpinq screen uses.
 *
 * @spec openspec/specs/character-connections/spec.md
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
const FACTIONS = `${OBJECTS}/larping_faction`
const MEMBERS = `${OBJECTS}/larping_faction_member`
const RELATIONSHIPS = `${OBJECTS}/larping_relationship`

const players = ['tom', 'vera', 'karel'] as const
type Player = (typeof players)[number]
const uid = (p: Player): string => `${p}-${RUN_ID}`

let admin: APIRequestContext
const as: Partial<Record<Player, APIRequestContext>> = {}
const ledger = new FixtureLedger()
const created: string[] = []
let worldId = ''
const ids: Record<string, string> = {}

/**
 * Create an object through OpenRegister as one user.
 *
 * @param {APIRequestContext} api The user.
 * @param {string} url The collection.
 * @param {object} data The object.
 * @return {Promise<import('@playwright/test').APIResponse>} The response.
 */
async function post(
	api: APIRequestContext,
	url: string,
	data: Record<string, unknown>,
) {
	return api.post(url, { headers: { 'OCS-APIRequest': 'true' }, data })
}

/**
 * The id of a created object, tracked for clean-up.
 *
 * @param {import('@playwright/test').APIResponse} res The create response.
 * @param {string} url The collection.
 * @return {Promise<string>} The id.
 */
async function idOf(
	res: Awaited<ReturnType<typeof post>>,
	url: string,
): Promise<string> {
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	created.push(`${url}/${id}`)
	return id
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
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

	worldId = ledger.track(
		'setting',
		await createObject(admin, 'setting', { name: fixtureName('Aldmoor') }),
	)
	const character = async (name: string, owner: string): Promise<string> =>
		ledger.track(
			'character',
			await createObject(admin, 'character', {
				name: fixtureName(name),
				setting: worldId,
				ownerUid: owner,
			}),
		)
	ids.tomas = await character('Tomas', uid('tom'))
	ids.venn = await character('Lady Venn', uid('vera'))
	ids.mirela = await character('Mirela the Wanderer', uid('vera'))
	ids.harrow = await character('Old Captain Harrow', uid('karel'))
	ids.bertram = await character('Sir Bertram', 'admin')

	ids.guard = await idOf(
		await post(admin, FACTIONS, {
			name: fixtureName("The Crown's Guard"),
			setting: worldId,
			kind: 'faction',
			visibility: 'open',
		}),
		FACTIONS,
	)
	ids.circle = await idOf(
		await post(admin, FACTIONS, {
			name: fixtureName('The Ash Circle'),
			setting: worldId,
			kind: 'faction',
			visibility: 'secret',
		}),
		FACTIONS,
	)
	await idOf(
		await post(admin, MEMBERS, {
			faction: ids.circle,
			character: ids.mirela,
			role: 'member',
			status: 'active',
		}),
		MEMBERS,
	)
	ids.lanterns = await idOf(
		await post(as.tom!, FACTIONS, {
			name: fixtureName('The Lantern Bearers'),
			setting: worldId,
			kind: 'group',
			visibility: 'open',
			leader: ids.tomas,
		}),
		FACTIONS,
	)
	await idOf(
		await post(as.tom!, MEMBERS, {
			faction: ids.lanterns,
			character: ids.tomas,
			role: 'leader',
			status: 'active',
		}),
		MEMBERS,
	)
})

test.afterAll(async () => {
	for (const url of created.reverse()) {
		await admin.delete(url, { headers: { 'OCS-APIRequest': 'true' } })
	}
	await cleanupLedger(admin, ledger)
	for (const p of players) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid(p)}`, {
			headers: OCS,
		})
		await as[p]?.dispose()
	}
	await admin.dispose()
})

test.describe('factions, groups and relationships', () => {
	// @e2e openspec/specs/character-connections/spec.md#a-game-master-fills-the-guard
	test('a game master adds a member and both pages list it', async ({ page }) => {
		await idOf(
			await post(admin, MEMBERS, {
				faction: ids.guard,
				character: ids.bertram,
				role: 'member',
				status: 'active',
			}),
			MEMBERS,
		)
		const list = await admin.get(`${MEMBERS}?faction=${ids.guard}&_limit=50`, {
			headers: OCS,
		})
		expect(
			(await list.json()).results.map(
				(row: { character: string }) => row.character,
			),
		).toContain(ids.bertram)
		await page.goto(`${BASE_URL}/index.php${BASE}/characters/${ids.bertram}`)
		await expect(page.getByText('Factions and groups')).toBeVisible()
	})

	// @e2e openspec/specs/character-connections/spec.md#a-player-looks-for-the-ash-circle
	test('a player who owns no member never finds a secret faction', async () => {
		const factions = await as.karel!.get(
			`${FACTIONS}?setting=${worldId}&_limit=50`,
			{ headers: OCS },
		)
		const names = (await factions.json()).results.map(
			(row: { name: string }) => row.name,
		)
		expect(names.some((n: string) => n.includes('The Ash Circle'))).toBe(false)
		const members = await as.karel!.get(
			`${MEMBERS}?character=${ids.mirela}&_limit=50`,
			{ headers: OCS },
		)
		expect((await members.json()).results).toHaveLength(0)
	})

	// @e2e openspec/specs/character-connections/spec.md#a-leader-invites-and-the-invitee-accepts
	test('the leader invites and the invitee accepts', async () => {
		ids.vennMembership = await idOf(
			await post(as.tom!, MEMBERS, {
				faction: ids.lanterns,
				character: ids.venn,
				role: 'member',
				status: 'invited',
			}),
			MEMBERS,
		)
		const accepted = await as.vera!.patch(`${MEMBERS}/${ids.vennMembership}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { status: 'active' },
		})
		expect(accepted.ok(), await accepted.text()).toBe(true)
		const read = await admin.get(`${MEMBERS}/${ids.vennMembership}`, {
			headers: OCS,
		})
		expect((await read.json()).status).toBe('active')
	})

	// @e2e openspec/specs/character-connections/spec.md#a-player-cannot-join-without-asking
	test('a player cannot make their own character an active member', async () => {
		const res = await post(as.karel!, MEMBERS, {
			faction: ids.lanterns,
			character: ids.harrow,
			role: 'member',
			status: 'active',
		})
		expect(res.ok()).toBe(false)
		expect(await res.text()).toContain('membership_needs_invitation')
	})

	// @e2e openspec/specs/character-connections/spec.md#a-member-leaves
	test('a member leaves and the game master still sees the membership', async () => {
		const left = await as.vera!.patch(`${MEMBERS}/${ids.vennMembership}`, {
			headers: { 'OCS-APIRequest': 'true' },
			data: { status: 'left' },
		})
		expect(left.ok(), await left.text()).toBe(true)
		const read = await admin.get(`${MEMBERS}?character=${ids.venn}&_limit=50`, {
			headers: OCS,
		})
		expect(
			(await read.json()).results.map((row: { status: string }) => row.status),
		).toContain('left')
	})

	// @e2e openspec/specs/character-connections/spec.md#a-player-records-a-rival
	test('a rival known to owners is read by the other owner', async () => {
		await idOf(
			await post(as.vera!, RELATIONSHIPS, {
				from: ids.mirela,
				to: ids.harrow,
				kind: 'rival',
				description: 'Old debt',
				knownTo: 'owners',
			}),
			RELATIONSHIPS,
		)
		const named = await as.karel!.get(
			`${RELATIONSHIPS}?to=${ids.harrow}&_limit=50`,
			{ headers: OCS },
		)
		expect(
			(await named.json()).results.map((row: { kind: string }) => row.kind),
		).toContain('rival')
	})

	// @e2e openspec/specs/character-connections/spec.md#a-hidden-family-tie
	test('a relationship for game masters stays hidden from the players', async () => {
		await idOf(
			await post(admin, RELATIONSHIPS, {
				from: ids.tomas,
				to: ids.venn,
				kind: 'family',
				description: 'Sister',
				knownTo: 'gamemasters',
			}),
			RELATIONSHIPS,
		)
		for (const p of ['tom', 'vera'] as const) {
			const read = await as[p]!.get(
				`${RELATIONSHIPS}?from=${ids.tomas}&_limit=50`,
				{ headers: OCS },
			)
			expect((await read.json()).results).toHaveLength(0)
		}
	})
})
