/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: plots through several characters, with private texts kept from
 * players, and writing progress (characters-plot-threads-and-writing).
 *
 * As admin, provisions player Anna (a Nextcloud user in `larpers`) who owns
 * "Mirela the Wanderer", and "Sir Bertram" owned by nobody. A plot gets a part
 * for each. Anna then reads through OpenRegister, the path every Larpinq
 * screen uses.
 *
 * @spec openspec/specs/story-writing/spec.md
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
const OR = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`

const annaUid = `anna-${RUN_ID}`
const annaPass = `Anna-${RUN_ID}-pw!9`

let admin: APIRequestContext
let anna: APIRequestContext
const ledger = new FixtureLedger()
const made: string[] = []
const ids: Record<string, string> = {}

/**
 * Create one object by schema slug as admin.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<string>} Its id.
 */
async function make(slug: string, body: Record<string, unknown>): Promise<string> {
	const res = await admin.post(`${OR}/${slug}`, {
		headers: { 'OCS-APIRequest': 'true' },
		data: body,
	})
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = json?.id ?? json?.['@self']?.id
	made.push(`${slug}/${id}`)
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

	ids.mirela = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: fixtureName('Mirela the Wanderer'),
			ocName: 'Mirela',
			ownerUid: annaUid,
			writingStep: 'approved',
			writer: 'joris',
		}),
	)
	ids.bertram = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: fixtureName('Sir Bertram'),
			ocName: 'Bertram',
		}),
	)
	ids.tomas = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: fixtureName('Tomas'),
			ocName: 'Tomas',
			writingStep: 'draft',
			writer: 'sanne',
		}),
	)
	ids.plot = await make('larping_plot', {
		title: fixtureName('The heir of Aldmoor'),
		writer: 'joris',
	})
	ids.mirelaPart = await make('larping_plot_part', {
		plot: ids.plot,
		character: ids.mirela,
		privateText: 'She is the lost heir.',
		playerText: 'You dream of a crown you never wore.',
	})
	ids.bertramPart = await make('larping_plot_part', {
		plot: ids.plot,
		character: ids.bertram,
		privateText: 'Sworn to find the heir.',
	})
})

test.afterAll(async () => {
	for (const path of made.reverse()) {
		await admin.delete(`${OR}/${path}`, {
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

test.describe('plots and writing', () => {
	// @e2e openspec/specs/story-writing/spec.md#a-game-master-writes-the-heir-plot
	test('the plot lists both characters and the character lists the plot', async ({
		page,
	}) => {
		const res = await admin.get(`${OR}/larping_plot_part?plot=${ids.plot}`, {
			headers: OCS,
		})
		const characters = ((await res.json())?.results ?? []).map(
			(row: { character: string }) => row.character,
		)
		expect(characters.sort()).toEqual([ids.mirela, ids.bertram].sort())

		await page.goto(`${BASE_URL}/index.php${BASE}/characters/${ids.mirela}`)
		await expect(
			page.getByText('You dream of a crown you never wore.'),
		).toBeVisible()
	})

	// @e2e openspec/specs/story-writing/spec.md#anna-reads-her-dream-not-her-secret
	test('a player reads her own player text and nothing else', async () => {
		const own = await anna.get(`${OR}/larping_plot_part/${ids.mirelaPart}`, {
			headers: OCS,
		})
		expect(own.ok()).toBe(true)
		const body = await own.json()
		expect(body.playerText).toBe('You dream of a crown you never wore.')
		expect(body.privateText ?? null).toBeNull()
		expect(body.ownerUid).toBe(annaUid)

		expect(
			(
				await anna.get(`${OR}/larping_plot_part/${ids.bertramPart}`, {
					headers: OCS,
				})
			).ok(),
		).toBe(false)
		expect(
			(
				await anna.get(`${OR}/larping_plot/${ids.plot}`, { headers: OCS })
			).ok(),
		).toBe(false)
	})

	// @e2e openspec/specs/story-writing/spec.md#a-plot-is-marked-ready
	test('a plot moves from draft to ready', async ({ page }) => {
		await page.goto(`${BASE_URL}/index.php${BASE}/plots/${ids.plot}`)
		await page.getByRole('button', { name: /ready/i }).first().click()
		await expect
			.poll(
				async () =>
					(
						await (
							await admin.get(`${OR}/larping_plot/${ids.plot}`, {
								headers: OCS,
							})
						).json()
					).writingStep,
			)
			.toBe('ready')
	})

	// @e2e openspec/specs/story-writing/spec.md#two-weeks-before-the-event
	test('the roster report lists the characters still in writing', async ({
		page,
	}) => {
		const res = await admin.get(
			`${OR}/character?writingStep[]=draft&writingStep[]=ready&_limit=500`,
			{ headers: OCS },
		)
		const names = ((await res.json())?.results ?? []).map(
			(row: { name: string }) => row.name,
		)
		expect(names).toContain(fixtureName('Tomas'))
		expect(names).not.toContain(fixtureName('Mirela the Wanderer'))

		await page.goto(`${BASE_URL}/index.php${BASE}/reports/characters`)
		await expect(page.getByText('Not yet approved in writing')).toBeVisible()
	})
})
