// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V.
/**
 * Casting a larp event end to end (events-casting-and-crew-roles task 3.2,
 * REQ-ECC-001 to REQ-ECC-003), on the seed of design.md:
 *
 *   Winter Court: open characters The Chancellor, The Spy, The Heir, The Bard.
 *   Anna ranks The Heir 1, The Spy 2; Pieter ranks The Heir 1, The Bard 2;
 *   Sanne ranks The Spy 1. Proposal: Anna The Heir, Pieter The Bard,
 *   Sanne The Spy.
 *
 * The three players rank through their own registration page, each in a
 * context with an empty storage state (none of the admin's cookies come
 * along). The game master (the suite's admin session) opens the Casting tab
 * of the event, checks the proposal and confirms it. The result is read back
 * over the API: The Heir belongs to Anna and her registration names it.
 *
 * Fixtures carry RUN_ID and are removed in afterAll. The casting deadline is
 * set a month ahead, so ranking is open whatever day the suite runs.
 *
 * @spec openspec/changes/archive/2026-11-24-events-casting-and-crew-roles/tasks.md#task-3.2
 */
import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './_base-url.ts'
import { openSidebarTab } from './_nav.ts'
import { signedInPage } from './_sign-in.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	postInInitialState,
	resolveSchemaIds,
	RUN_ID,
} from './workflows/fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const REGISTRATIONS = `${OBJECTS}/larping_registration`
const CHARACTERS = `${OBJECTS}/character`

const players = { anna: 'Anna', pieter: 'Pieter', sanne: 'Sanne' } as const
type Player = keyof typeof players
const uid = (p: Player): string => `${p}-${RUN_ID}`
const password = (p: Player): string => `${uid(p)}-Pw!9`

/** Each player's wishes, by character key, as design.md seeds them. */
const RANKS: Record<Player, Record<string, number>> = {
	anna: { heir: 1, spy: 2 },
	pieter: { heir: 1, bard: 2 },
	sanne: { spy: 1 },
}

const CHARACTER_NAMES = { chancellor: 'The Chancellor', spy: 'The Spy', heir: 'The Heir', bard: 'The Bard' } as const

let admin: APIRequestContext
const ledger = new FixtureLedger()
const registrations: Partial<Record<Player, string>> = {}
const playerRecords: Partial<Record<Player, string>> = {}
const characters: Record<string, string> = {}
const extra: string[] = []
let eventId = ''

/**
 * The id of an object a POST created.
 *
 * @param res The response.
 * @return The id.
 */
async function idOf(res: Awaited<ReturnType<APIRequestContext['post']>>): Promise<string> {
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	return String(json?.id ?? json?.['@self']?.id)
}

/**
 * A browser page signed in as a player, with an empty storage state.
 *
 * @param browser The browser.
 * @param p The player.
 * @return The page.
 */
async function pageAs(browser: Browser, p: Player): Promise<Page> {
	return signedInPage(browser, uid(p), password(p))
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, { headers: OCS, form: { groupid: 'larpers' } })
	for (const p of Object.keys(players) as Player[]) {
		const made = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
			headers: OCS,
			form: { userid: uid(p), password: password(p), displayName: players[p], 'groups[]': 'larpers' },
		})
		expect(made.ok(), await made.text()).toBe(true)
		playerRecords[p] = ledger.track('player', await createObject(admin, 'player', { name: fixtureName(players[p]), userUid: uid(p) }))
	}

	const deadline = new Date(Date.now() + 30 * 24 * 3600 * 1000).toISOString()
	eventId = ledger.track('event', await createObject(admin, 'event', { name: fixtureName('Winter Court'), castingDeadline: deadline }))
	for (const [key, name] of Object.entries(CHARACTER_NAMES)) {
		characters[key] = ledger.track(
			'character',
			await createObject(admin, 'character', { name: fixtureName(name), castingEvent: eventId, approved: 'approved' }),
		)
	}

	for (const p of Object.keys(players) as Player[]) {
		const id = await idOf(await postInInitialState(
			admin,
			REGISTRATIONS,
			{ event: eventId, player: playerRecords[p], playerUid: uid(p), status: 'accepted' },
			{ ...OCS, 'Content-Type': 'application/json' },
		))
		registrations[p] = id
		extra.push(`${REGISTRATIONS}/${id}`)
	}
})

test.afterAll(async () => {
	if (!admin) return
	const preferences = await admin.get(`${OBJECTS}/larping_casting_preference?event=${eventId}&_limit=100`, { headers: OCS })
	if (preferences.ok()) {
		for (const row of ((await preferences.json())?.results ?? [])) {
			extra.push(`${OBJECTS}/larping_casting_preference/${row.id ?? row['@self']?.id}`)
		}
	}

	for (const url of extra) {
		await admin.delete(url, { headers: OCS }).catch(() => undefined)
	}

	await cleanupLedger(admin, ledger)
	for (const p of Object.keys(players) as Player[]) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid(p)}`, { headers: OCS }).catch(() => undefined)
	}

	await admin.dispose()
})

test.describe('events-casting-and-crew-roles', () => {
	/**
	 * @e2e casting::players-rank-the-characters-they-want
	 */
	test('three players rank the characters on their registration', async ({ browser }) => {
		for (const p of Object.keys(players) as Player[]) {
			const page = await pageAs(browser, p)
			try {
				await page.goto(`${BASE}/registrations/${registrations[p]}`, { waitUntil: 'domcontentloaded' })
				await openSidebarTab(page, 'Casting')
				const casting = page.getByTestId('registration-casting')
				await expect(casting).toBeVisible({ timeout: 30_000 })
				await expect(page.getByTestId('registration-casting-closed')).toHaveCount(0)

				for (const [key, rank] of Object.entries(RANKS[p])) {
					await page.getByTestId(`registration-casting-rank-${characters[key]}`).click()
					await page.getByRole('option', { name: `Rank ${rank}`, exact: true }).click()
				}

				await page.getByTestId('registration-casting-save').click()
				await expect(page.getByTestId('registration-casting-saved')).toBeVisible({ timeout: 15_000 })
			} finally {
				await page.context().close()
			}
		}
	})

	/**
	 * @e2e casting::only-a-game-master-sees-the-proposal
	 */
	test('a player cannot read the casting proposal', async () => {
		const anna = await request.newContext({
			baseURL: BASE_URL,
			storageState: { cookies: [], origins: [] },
			httpCredentials: { username: uid('anna'), password: password('anna'), send: 'always' },
			extraHTTPHeaders: OCS,
		})
		try {
			const whoami = await anna.get('/ocs/v2.php/cloud/user?format=json')
			expect((await whoami.json()).ocs.data.id, 'the context is Anna').toBe(uid('anna'))
			expect((await anna.get(`/index.php/apps/larpinq/api/events/${eventId}/casting/proposal`)).status()).toBe(403)
		} finally {
			await anna.dispose()
		}
	})

	/**
	 * @e2e casting::the-game-master-confirms-the-proposed-casting
	 */
	test('the game master confirms the proposal and Anna owns The Heir', async ({ page }) => {
		await page.goto(`${BASE}/events/${eventId}`, { waitUntil: 'domcontentloaded' })
		await openSidebarTab(page, 'Casting')

		const proposal = page.getByTestId('event-casting-proposal')
		await expect(proposal).toBeVisible({ timeout: 30_000 })
		const expected: Array<[Player, keyof typeof CHARACTER_NAMES]> = [['anna', 'heir'], ['pieter', 'bard'], ['sanne', 'spy']]
		for (const [p, key] of expected) {
			const row = proposal.locator('tr', { hasText: players[p] }).first()
			await expect(row).toContainText(fixtureName(CHARACTER_NAMES[key]))
		}

		await expect(page.getByTestId('event-casting-unmatched')).toHaveCount(0)
		await page.getByTestId('event-casting-confirm').click()
		await expect(page.getByTestId('event-casting-confirmed')).toBeVisible({ timeout: 15_000 })
		await expect(page.getByTestId('event-casting-refused')).toHaveCount(0)

		const heir = await (await admin.get(`${CHARACTERS}/${characters.heir}`, { headers: OCS })).json()
		expect(heir.ocName, 'The Heir is cast to Anna').toBe(playerRecords.anna)
		const registration = await (await admin.get(`${REGISTRATIONS}/${registrations.anna}`, { headers: OCS })).json()
		expect(registration.character, "Anna's registration names The Heir").toBe(characters.heir)
	})
})
