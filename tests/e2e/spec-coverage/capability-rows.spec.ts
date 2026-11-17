/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The built behaviour of capability-rows-missing-requirements, task 1.1: the
 * world page shows its description, the player page its account and its
 * characters, and an option a player picks on a registration is counted on
 * the event's Choices tab.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/setting-management/spec.md
 * @spec openspec/changes/capability-rows-missing-requirements/specs/events-players/spec.md
 * @spec openspec/changes/capability-rows-missing-requirements/specs/event-registration/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { openSidebarTab } from '../_nav.ts'
import { FixtureLedger, fixtureName, newApi, OR_BASE } from '../workflows/fixtures.ts'

const BASE = '/apps/larpinq'
const JSON_HEADERS = { Accept: 'application/json', 'Content-Type': 'application/json', 'OCS-APIRequest': 'true' }
const DESCRIPTION = 'A rain-soaked river kingdom where two queens claim one crown'

let api: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}
const names = {
	mirela: fixtureName('Mirela the Wanderer'),
	harrow: fixtureName('Old Captain Harrow'),
	vegetarian: fixtureName('vegetarian'),
}

/**
 * Create one larpinq object by schema slug and track it for cleanup.
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<object>} The created object.
 */
async function make(slug: string, body: Record<string, unknown>): Promise<Record<string, any>> {
	const res = await api.post(`${OR_BASE}/larpinq/${slug}`, { headers: JSON_HEADERS, data: body })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const created = await res.json()
	ledger.track(slug, String(created.id))
	return created
}

test.beforeAll(async () => {
	api = await newApi()
	ids.world = (await make('setting', { name: fixtureName('Aldmoor'), description: DESCRIPTION })).id
	ids.anna = (await make('player', { name: fixtureName('Anna de Vries'), userUid: 'anna-e2e-account' })).id
	ids.mirela = (await make('character', { name: names.mirela, ocName: ids.anna, status: 'active', setting: ids.world })).id
	ids.harrow = (await make('character', { name: names.harrow, ocName: ids.anna, status: 'retired', setting: ids.world })).id
	ids.event = (await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		capacity: 10,
		approvalRequired: false,
	})).id
	ids.vegetarian = (await make('larping_registration_option', { event: ids.event, name: names.vegetarian, category: 'meal', amount: 0, currency: 'EUR' })).id
})

test.afterAll(async () => {
	for (const slug of ['larping_registration', 'larping_registration_option', 'larping_event', 'character', 'player', 'setting']) {
		for (const id of ledger.ids(slug)) {
			await api.delete(`${OR_BASE}/larpinq/${slug}/${id}`, { headers: JSON_HEADERS }).catch(() => undefined)
		}
	}
	await api.dispose()
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/setting-management/spec.md#a-player-reads-what-aldmoor-is-like
test('the world page shows the world\'s description', async ({ page }) => {
	await page.goto(`${BASE}/settings/${ids.world}`)
	await expect(page.locator('.app-content')).toContainText(DESCRIPTION, { timeout: 30_000 })
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/events-players/spec.md#a-game-master-links-annas-account
// @e2e openspec/changes/capability-rows-missing-requirements/specs/events-players/spec.md#annas-characters
test('the player page shows the account and the characters, and a character opens', async ({ page }) => {
	await page.goto(`${BASE}/players/${ids.anna}`)
	const content = page.locator('.app-content')
	// The data widget shows six fields until it is expanded.
	await content.getByRole('button', { name: /Show all \d+ fields/ }).first().click({ timeout: 30_000 })
	await expect(content).toContainText('anna-e2e-account')
	await expect(content).toContainText(names.mirela)
	await expect(content).toContainText(names.harrow)

	await content.getByText(names.mirela, { exact: true }).first().click()
	await expect(page).toHaveURL(new RegExp(`/characters/${ids.mirela}`), { timeout: 15_000 })
})

// @e2e openspec/changes/capability-rows-missing-requirements/specs/event-registration/spec.md#anna-eats-vegetarian
test('an option picked on a registration is shown on it and counted on the Choices tab', async ({ page }) => {
	const registration = await make('larping_registration', {
		event: ids.event,
		player: ids.anna,
		character: ids.mirela,
		options: [ids.vegetarian],
	})
	expect(registration.status).toBe('accepted')
	expect(registration.options).toContain(ids.vegetarian)

	await page.goto(`${BASE}/events/${ids.event}`)
	await openSidebarTab(page, 'Choices')
	const row = page.getByTestId('event-choice-count').filter({ hasText: names.vegetarian })
	await expect(row).toContainText('1', { timeout: 30_000 })
})
