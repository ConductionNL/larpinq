/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: the talk, polls and deck leaves on events and settings
 * (leaf-integrations).
 *
 * Reads the imported event and setting schemas from OpenRegister, then opens
 * a provisioned event and setting and checks the sidebar: a leaf tab is there
 * exactly when its Nextcloud app is enabled, and opening the page writes
 * nothing to the event.
 *
 * @spec openspec/specs/leaf-integrations/spec.md
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
	getObject,
	newApi,
	resolveSchemaIds,
	SCHEMA_IDS,
} from './fixtures.ts'

/** Leaf id -> the Nextcloud app that provides it. */
const LEAF_APPS: Record<string, string> = {
	calendar: 'calendar',
	maps: 'maps',
	forms: 'forms',
	talk: 'spreed',
	polls: 'polls',
	deck: 'deck',
}

let api: APIRequestContext
const ledger = new FixtureLedger()
let enabledApps: string[] = []
let eventId = ''
let settingId = ''

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)
	const apps = await api.get(
		`${BASE_URL}/ocs/v2.php/cloud/apps?filter=enabled&format=json`,
		{ headers: { 'OCS-APIRequest': 'true' } },
	)
	enabledApps = (await apps.json())?.ocs?.data?.apps ?? []
	settingId = ledger.track(
		'setting',
		await createObject(api, 'setting', {
			name: fixtureName('Winterfell Chronicles'),
		}),
	)
	eventId = ledger.track(
		'event',
		await createObject(api, 'event', {
			name: fixtureName('Summer siege'),
			startDate: '2026-06-14T10:00:00+00:00',
			endDate: '2026-06-15T16:00:00+00:00',
		}),
	)
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

/**
 * The linked types of one imported schema.
 *
 * @param {string} type The schema key.
 * @return {Promise<string[]>} The linked types.
 */
async function linkedTypes(type: string): Promise<string[]> {
	const res = await api.get(
		`${BASE_URL}/index.php/apps/openregister/api/schemas/${SCHEMA_IDS[type]}`,
		{ headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } },
	)
	expect(res.ok(), await res.text()).toBe(true)
	return (await res.json())?.configuration?.linkedTypes ?? []
}

/**
 * Open a detail page and wait for its sidebar.
 *
 * @param {Page} page The page.
 * @param {string} path The route.
 * @return {Promise<void>}
 */
async function openDetail(page: Page, path: string): Promise<void> {
	await page.goto(`${BASE}${path}`)
	await page
		.getByTestId('cn-object-sidebar')
		.waitFor({ state: 'visible', timeout: 30_000 })
}

/**
 * Expect a leaf tab exactly when its app is enabled.
 *
 * @param {Page} page The page.
 * @param {string} leaf The leaf id.
 * @return {Promise<void>}
 */
async function expectLeafFollowsApp(page: Page, leaf: string): Promise<void> {
	const tab = page.getByTestId(`cn-object-sidebar-tab-${leaf}`)
	await expect(tab).toHaveCount(enabledApps.includes(LEAF_APPS[leaf]) ? 1 : 0)
}

test.describe('leaf-integrations', () => {
	// @e2e openspec/specs/leaf-integrations/spec.md#the-merge-preserves-the-other-fragments-leaves
	test('the imported event schema links all six leaves', async () => {
		const types = await linkedTypes('event')
		for (const leaf of Object.keys(LEAF_APPS)) {
			expect(types).toContain(leaf)
		}
	})

	// @e2e openspec/specs/leaf-integrations/spec.md#event-detail-renders-the-three-new-leaf-tabs
	test('an event shows each leaf whose app is enabled', async ({ page }) => {
		await openDetail(page, `/events/${eventId}`)
		for (const leaf of Object.keys(LEAF_APPS)) {
			await expectLeafFollowsApp(page, leaf)
		}
	})

	// @e2e openspec/specs/leaf-integrations/spec.md#campaign-room-and-rules-vote-hang-off-the-setting
	test('a setting shows talk and polls, never deck', async ({ page }) => {
		expect(await linkedTypes('setting')).toEqual(
			expect.arrayContaining(['talk', 'polls']),
		)
		await openDetail(page, `/settings/${settingId}`)
		await expectLeafFollowsApp(page, 'talk')
		await expectLeafFollowsApp(page, 'polls')
		await expect(page.getByTestId('cn-object-sidebar-tab-deck')).toHaveCount(0)
	})

	// @e2e openspec/specs/leaf-integrations/spec.md#no-deck-installed-no-deck-tab-no-error
	test('without Deck the event page has no deck tab and no error', async ({
		page,
	}) => {
		test.skip(
			enabledApps.includes('deck'),
			'Deck is enabled on this instance; the event test above covers the tab',
		)
		const errors: string[] = []
		page.on('pageerror', (error) => errors.push(error.message))
		await openDetail(page, `/events/${eventId}`)

		await expect(page.getByTestId('cn-object-sidebar-tab-deck')).toHaveCount(0)
		expect(errors).toEqual([])
	})

	// @e2e openspec/specs/leaf-integrations/spec.md#a-closed-scheduling-poll-does-not-move-the-event-date
	test('opening every leaf leaves the event dates as they were', async ({
		page,
	}) => {
		const before = await getObject(api, 'event', eventId)
		await openDetail(page, `/events/${eventId}`)
		for (const leaf of ['talk', 'polls', 'deck']) {
			const tab = page.getByTestId(`cn-object-sidebar-tab-${leaf}`)
			if ((await tab.count()) === 1) {
				await tab.click()
			}
		}
		const after = await getObject(api, 'event', eventId)

		expect(before).not.toBeNull()
		expect([after?.startDate, after?.endDate]).toEqual([
			before?.startDate,
			before?.endDate,
		])
	})
})
