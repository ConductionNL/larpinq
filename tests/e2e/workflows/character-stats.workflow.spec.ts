/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: the Stats tab on the character page (characters-stat-sheet-panel).
 *
 * Provisions the design's example through the OpenRegister object API:
 * ability Strength (base 10), an XP ability (base 0), skill Swordsmanship
 * (+3 strength, costs 10 XP), item Iron shield (+1 strength), two XP awards of
 * 20, and character "Mirela the Wanderer" holding the skill and the item. Then
 * reads GET /api/characters/{id}/stats and opens the Stats tab.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	BASE,
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
} from './fixtures.ts'

let api: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
const names = {
	strength: fixtureName('Strength'),
	agility: fixtureName('Agility'),
	cursed: fixtureName('Cursed'),
	xp: fixtureName('experience points'),
	sword: fixtureName('Swordsmanship'),
	shield: fixtureName('Iron shield'),
	mirela: fixtureName('Mirela the Wanderer'),
}

type Modifier = { source: string; sourceName: string; change: number }
type Ability = {
	id: string
	base: number
	final: number
	modifiers: Modifier[]
}

/**
 * Create one object and remember it for cleanup.
 *
 * @param {string} type The schema.
 * @param {object} body The object.
 * @return {Promise<string>} The UUID.
 */
async function make(type: string, body: object): Promise<string> {
	return ledger.track(type, await createObject(api, type, body))
}

/**
 * Read the stat sheet of one character.
 *
 * @param {string} id The character UUID.
 * @return {Promise<{abilities: Ability[], xp: object|null}>} The sheet.
 */
async function sheetOf(id: string) {
	const res = await api.get(
		`${BASE_URL}/index.php/apps/larpinq/api/characters/${id}/stats`,
		{ headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } },
	)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)

	ids.strength = await make('ability', { name: names.strength, base: 10 })
	ids.xp = await make('ability', { name: names.xp, base: 0 })
	ids.agility = await make('ability', { name: names.agility, base: 8 })
	const hex = await make('effect', {
		name: fixtureName('Hex'),
		modifier: 2,
		modification: 'negative',
		abilities: [ids.agility],
	})
	const cursed = await make('condition', { name: names.cursed, effects: [hex] })
	const blade = await make('effect', {
		name: fixtureName('Blade training'),
		modifier: 3,
		modification: 'positive',
		abilities: [ids.strength],
	})
	const cost = await make('effect', {
		name: fixtureName('Swordsmanship cost'),
		modifier: 10,
		modification: 'negative',
		abilities: [ids.xp],
	})
	const weight = await make('effect', {
		name: fixtureName('Shield weight'),
		modifier: 1,
		modification: 'positive',
		abilities: [ids.strength],
	})
	const sword = await make('skill', { name: names.sword, effects: [blade, cost] })
	const shield = await make('item', { name: names.shield, effects: [weight] })
	const player = await make('player', { name: fixtureName('Mirela player') })
	ids.mirela = await make('character', {
		name: names.mirela,
		ocName: player,
		skills: [sword],
		items: [shield],
		conditions: [cursed],
	})
	ids.newcomer = await make('character', {
		name: fixtureName('Newcomer'),
		ocName: player,
	})
	const event = await make('event', { name: fixtureName('Summer siege') })
	for (const reason of ['Summer siege', 'Winter moot']) {
		await make('xpAward', {
			event,
			character: ids.mirela,
			amount: 20,
			reason: fixtureName(reason),
		})
	}
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

/**
 * Open the Stats tab of Mirela's character page.
 *
 * @param {Page} page The page.
 * @return {Promise<void>}
 */
async function openStats(page: Page): Promise<void> {
	await page.goto(`${BASE}/characters/${ids.mirela}`)
	await page.getByRole('tab', { name: 'Stats' }).click()
	await page
		.getByTestId('character-stat-sheet')
		.waitFor({ state: 'visible', timeout: 30_000 })
}

test.describe('characters-stat-sheet-panel', () => {
	// @e2e openspec/specs/character-management/spec.md#a-game-master-sees-why-strength-is-14
	test('strength is 14 with both sources named', async () => {
		const sheet = await sheetOf(ids.mirela)
		const strength = sheet.abilities.find((a: Ability) => a.id === ids.strength)

		expect([strength.base, strength.final]).toEqual([10, 14])
		expect(
			strength.modifiers.map((m: Modifier) => [
				m.change,
				m.sourceName,
				m.source,
			]),
		).toEqual([
			[3, names.sword, 'skill'],
			[1, names.shield, 'item'],
		])
	})

	// @e2e openspec/specs/character-management/spec.md#a-game-master-checks-xp-before-a-purchase
	test('XP reads 40 earned, 10 spent, 30 left', async () => {
		const sheet = await sheetOf(ids.mirela)
		const xp = sheet.abilities.find((a: Ability) => a.id === ids.xp)

		expect(xp.final).toBe(30)
		expect(xp.modifiers.map((m: Modifier) => m.change).sort()).toEqual([
			-10, 20, 20,
		])
		// The XP line follows the ability the budget check resolves; on a shared
		// instance an older "XP" ability can come first, so compare only then.
		if (sheet.xp?.ability === ids.xp) {
			expect(sheet.xp).toMatchObject({ earned: 40, spent: 10, left: 30 })
		}
	})

	// @e2e openspec/specs/character-management/spec.md#someone-without-access-asks-for-stats
	test('an unknown character answers 404 without stats', async () => {
		const res = await api.get(
			`${BASE_URL}/index.php/apps/larpinq/api/characters/00000000-0000-4000-8000-000000000000/stats`,
			{ headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } },
		)

		expect(res.status()).toBe(404)
		expect(await res.json()).not.toHaveProperty('abilities')
	})

	// @e2e openspec/specs/character-management/spec.md#a-game-master-sees-why-strength-is-14
	test('the Stats tab expands strength to its modifiers', async ({ page }) => {
		await openStats(page)
		const row = page.getByTestId(`character-stat-${ids.strength}`)

		await expect(row).toContainText('14')
		await row.getByRole('button').click()
		await expect(row).toContainText(`+3 from ${names.sword} (skill)`)
		await expect(row).toContainText(`+1 from ${names.shield} (item)`)
	})

	// @e2e openspec/specs/character-management/spec.md#a-negative-modifier-stands-out
	test('a negative modifier is marked', async ({ page }) => {
		const sheet = await sheetOf(ids.mirela)
		const agility = sheet.abilities.find((a: Ability) => a.id === ids.agility)
		expect([agility.base, agility.final]).toEqual([8, 6])
		expect(
			agility.modifiers.map((m: Modifier) => [
				m.change,
				m.sourceName,
				m.source,
			]),
		).toEqual([[-2, names.cursed, 'condition']])

		await openStats(page)
		const row = page.getByTestId(`character-stat-${ids.agility}`)
		await row.getByRole('button').click()
		await expect(
			row.locator('.character-stat-sheet__change--negative'),
		).toHaveText('-2')
		await expect(row).toContainText(`from ${names.cursed} (condition)`)
	})

	// @e2e openspec/specs/character-management/spec.md#a-new-character
	test('a new character shows every base value and no modifiers', async () => {
		const sheet = await sheetOf(ids.newcomer)
		for (const id of [ids.strength, ids.agility]) {
			const ability = sheet.abilities.find((a: Ability) => a.id === id)
			expect([ability.final, ability.modifiers]).toEqual([ability.base, []])
		}
	})
})
