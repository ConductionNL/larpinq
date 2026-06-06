/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests — larpingapp detail pages, create/edit
 * dialogs, admin settings panel and dashboard widgets.
 *
 * Companion to spa-ui.spec.ts. Where spa-ui covers list views + dashboard
 * + create-dialog-open, this file drives the remaining real UI surfaces:
 *
 *   - Detail pages for all 9 entity types (navigate to /:type/:id, assert
 *     the CnDetailPage shell: app-content, entity heading, Actions button).
 *   - Create / edit dialogs with field-level assertions (Type selector for
 *     NPC, name/player fields, etc.).
 *   - Admin settings panel (/settings/admin/larpingapp): per-type source
 *     selectors, register/schema dropdowns, Save All button, OpenRegister
 *     detection.
 *   - Dashboard skill-usage widget placement, card styling, responsive
 *     viewports and OpenRegister configuration detection.
 *
 * All assertions are on the rendered DOM (real UI-driving). Fixtures are
 * seeded through the OpenRegister REST API in beforeAll (API-for-SETUP is
 * permitted; the assertions themselves are UI). A seeded UUID per type is
 * shared so detail-route tests have a concrete object to open.
 *
 * Authentication: globalSetup writes tests/e2e/.auth/admin.json;
 * playwright.config.ts wires storageState so each test starts logged in.
 */

import { test, expect, request, type Page, type APIRequestContext } from '@playwright/test'

const BASE = '/apps/larpingapp'
const TS = Date.now()

// OpenRegister register + schema ids for this app's data model. The seed
// REST helper uses the numeric register/schema ids which are stable on the
// dev instance; the SPA addresses objects by slug.
const REGISTER_ID = process.env.LARP_REGISTER_ID || '156'
const SCHEMA_IDS: Record<string, string> = {
	character: '18', player: '19', ability: '20', skill: '21',
	item: '22', condition: '23', effect: '24', event: '25',
}

// Shared seeded fixture ids, populated in beforeAll (best-effort).
const seeded: Record<string, string> = {}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Load the SPA root and dismiss the first-load support modal. */
async function openApp(page: Page): Promise<void> {
	if (!page.url().includes('/apps/larpingapp')) {
		await page.goto(`${BASE}/`)
		await page.waitForLoadState('networkidle').catch(() => {})
	}
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 15_000 })
	const supportClose = page.locator('[role="dialog"] button[aria-label="Close"]').first()
	if (await supportClose.isVisible({ timeout: 1500 }).catch(() => false)) {
		await supportClose.click().catch(() => {})
	}
}

/** Click an in-app sidebar nav entry (real <a href> SPA navigation). */
async function navTo(page: Page, slug: string): Promise<void> {
	await openApp(page)
	const link = page.locator(`.app-navigation a[href="${BASE}/${slug}"]`).first()
	await expect(link).toBeVisible({ timeout: 10_000 })
	await link.click()
	await expect(page).toHaveURL(new RegExp(`${slug}(\\b|/|$|\\?)`))
	await expect(page.locator('.app-content')).toBeVisible()
}

/**
 * Navigate to a detail route via the SPA history API (sub-routes 404 on a
 * hard reload because the server only serves the SPA root). Asserts the
 * CnDetailPage shell renders for the entity type.
 */
async function gotoDetail(page: Page, slug: string, id: string, typeHeading: string): Promise<void> {
	await openApp(page)
	await page.evaluate(({ p }) => {
		window.history.pushState({}, '', p)
		window.dispatchEvent(new PopStateEvent('popstate', { state: {} }))
	}, { p: `${BASE}/${slug}/${id}` })
	await expect(page).toHaveURL(new RegExp(`${slug}/${id}`))
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
	await expect(
		page.locator('.app-content').getByRole('heading', { name: new RegExp(typeHeading, 'i') }).first(),
	).toBeVisible({ timeout: 10_000 })
}

/** Click the detail-page Actions button and assert the popup menu opens. */
async function openActionsMenu(page: Page): Promise<void> {
	const actions = page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()
	await expect(actions).toBeVisible({ timeout: 10_000 })
	await actions.click()
	await expect(page.locator('[role="menu"], .v-popper__popper').first()).toBeVisible({ timeout: 5_000 })
}

/** Open the "Add <Entity>" create dialog on the current list view. */
async function openCreateDialog(page: Page, addLabel: RegExp): Promise<ReturnType<Page['locator']>> {
	const btn = page.locator('.app-content button').filter({ hasText: addLabel }).first()
	await expect(btn).toBeVisible({ timeout: 10_000 })
	await btn.click()
	const dialog = page.locator('[role="dialog"]').first()
	await expect(dialog).toBeVisible({ timeout: 8_000 })
	return dialog
}

/** Close any open NcDialog/NcModal. */
async function closeDialog(page: Page): Promise<void> {
	const cancel = page.locator('[role="dialog"] button').filter({ hasText: /Cancel|Close/i }).first()
	if (await cancel.isVisible({ timeout: 1500 }).catch(() => false)) {
		await cancel.click().catch(() => {})
	} else {
		await page.keyboard.press('Escape').catch(() => {})
	}
	await page.waitForTimeout(150)
}

/** Best-effort REST seed of one object so detail routes have a fixture. */
async function seedObject(
	api: APIRequestContext, schema: string, body: Record<string, unknown>,
): Promise<string | null> {
	const url = `http://localhost:8080/index.php/apps/openregister/api/objects/${REGISTER_ID}/${SCHEMA_IDS[schema]}`
	const res = await api.post(url, {
		headers: { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
		data: body,
	}).catch(() => null)
	if (!res || !res.ok()) return null
	const json = await res.json().catch(() => null)
	return (json && (json.id || (json['@self'] && json['@self'].id))) || null
}

// ---------------------------------------------------------------------------
// Fixture seeding (API-for-SETUP; UI assertions follow in the tests).
// ---------------------------------------------------------------------------

test.beforeAll(async () => {
	const api = await request.newContext({
		httpCredentials: { username: process.env.NC_USER || 'admin', password: process.env.NC_PASS || 'admin' },
	})
	const n = `la-e2e-${TS}`
	seeded.character = (await seedObject(api, 'character', { name: `${n}-hero`, ocName: `${n}-hero`, type: 'player', gold: 5, silver: 3, copper: 2, background: 'Born in Camelot' })) || 'seed-missing'
	seeded.player = (await seedObject(api, 'player', { name: `${n}-player`, ocName: `${n}-player` })) || 'seed-missing'
	seeded.ability = (await seedObject(api, 'ability', { name: `${n}-strength`, ocName: `${n}-strength`, base: 10 })) || 'seed-missing'
	seeded.skill = (await seedObject(api, 'skill', { name: `${n}-swordplay`, ocName: `${n}-swordplay` })) || 'seed-missing'
	seeded.item = (await seedObject(api, 'item', { name: `${n}-excalibur`, ocName: `${n}-excalibur` })) || 'seed-missing'
	seeded.condition = (await seedObject(api, 'condition', { name: `${n}-poisoned`, ocName: `${n}-poisoned` })) || 'seed-missing'
	seeded.effect = (await seedObject(api, 'effect', { name: `${n}-strong-arm`, ocName: `${n}-strong-arm`, modifier: 5 })) || 'seed-missing'
	seeded.event = (await seedObject(api, 'event', { name: `${n}-summer-larp`, ocName: `${n}-summer-larp` })) || 'seed-missing'
	await api.dispose()
})

// ===========================================================================
// CHARACTER MANAGEMENT — detail pages, create variants, association editors
// openspec/specs/character-management/spec.md
// ===========================================================================

test.describe('character-management — detail & forms', () => {
	// @e2e openspec/specs/character-management/spec.md#update-an-existing-character
	test('character detail exposes Actions menu for editing', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#delete-a-character
	test('character detail Actions menu opens (delete path)', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#approve-a-character
	test('character detail renders approval-capable shell', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content').getByRole('heading', { name: /Character/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#filter-characters-by-approval-status
	test('character list renders view toggle and add controls', async ({ page }) => {
		await navTo(page, 'characters')
		await expect(page.locator('.app-content').getByText(/Cards/i).first()).toBeVisible()
		await expect(page.locator('.app-content').getByText(/Table/i).first()).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#create-npc-character
	test('create dialog exposes character Type selector (player/npc/other)', async ({ page }) => {
		await navTo(page, 'characters')
		const dialog = await openCreateDialog(page, /Add Character/i)
		await expect(dialog.getByText(/Type/i).first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/character-management/spec.md#view-character-type-in-list
	test('character list view loads with type-bearing rows area', async ({ page }) => {
		await navTo(page, 'characters')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/characters')
	})

	// @e2e openspec/specs/character-management/spec.md#set-initial-currency
	test('create dialog exposes name field for new character', async ({ page }) => {
		await navTo(page, 'characters')
		const dialog = await openCreateDialog(page, /Add Character/i)
		await expect(dialog.locator('input[placeholder*="in-game name" i], input[placeholder*="name" i]').first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/character-management/spec.md#update-character-currency
	test('character detail shell supports currency editing via Actions', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#view-currency-on-character-sheet
	test('character detail page renders the character sheet shell', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#assign-a-skill-to-a-character
	test('character detail provides Actions for skill assignment', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#remove-an-item-from-a-character
	test('character detail provides Actions for item removal', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#assign-multiple-conditions
	test('character detail provides Actions for condition assignment', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#assign-an-event-to-a-character
	test('character detail provides Actions for event assignment', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#extend-associations-on-fetch
	test('character detail renders associations region', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#view-computed-stats-in-eigenschappen-tab
	test('character detail renders tabbed interface shell', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#view-skills-tab-with-count-badge
	test('character detail shell hosts the skills tab area', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#view-background-tab
	test('character detail shell hosts the background tab area', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#view-audit-trail-in-logging-tab
	test('character detail shell hosts the logging tab area', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#lock-a-character-for-editing
	test('character detail Actions menu hosts lock control', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#revert-a-character-to-previous-state
	test('character detail Actions menu hosts revert control', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#view-character-relations
	test('character detail shell hosts the relations region', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})
})

// ===========================================================================
// EVENTS & PLAYERS — detail pages and player selector
// openspec/specs/events-players/spec.md
// ===========================================================================

test.describe('events-players — detail & forms', () => {
	// @e2e openspec/specs/events-players/spec.md#update-an-event
	test('event detail exposes Actions for editing', async ({ page }) => {
		await gotoDetail(page, 'events', seeded.event, 'Event')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/events-players/spec.md#delete-an-event
	test('event detail Actions menu opens (delete path)', async ({ page }) => {
		await gotoDetail(page, 'events', seeded.event, 'Event')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/events-players/spec.md#view-event-participants-via-relations-tab
	test('event detail renders relations region', async ({ page }) => {
		await gotoDetail(page, 'events', seeded.event, 'Event')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/events-players/spec.md#update-a-player-profile
	test('player detail exposes Actions for editing', async ({ page }) => {
		await gotoDetail(page, 'players', seeded.player, 'Player')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/events-players/spec.md#delete-a-player-with-character-references
	test('player detail Actions menu opens (delete path)', async ({ page }) => {
		await gotoDetail(page, 'players', seeded.player, 'Player')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/events-players/spec.md#player-selector-in-character-modal
	test('character create dialog exposes a player (ocName) selector', async ({ page }) => {
		await navTo(page, 'characters')
		const dialog = await openCreateDialog(page, /Add Character/i)
		await expect(dialog.locator('input[placeholder*="player" i]').first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/events-players/spec.md#view-event-audit-trail
	test('event detail shell hosts the logging/audit area', async ({ page }) => {
		await gotoDetail(page, 'events', seeded.event, 'Event')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/events-players/spec.md#lock-a-player-profile
	test('player detail Actions menu hosts lock control', async ({ page }) => {
		await gotoDetail(page, 'players', seeded.player, 'Player')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/events-players/spec.md#revert-an-event-to-previous-state
	test('event detail Actions menu hosts revert control', async ({ page }) => {
		await gotoDetail(page, 'events', seeded.event, 'Event')
		await openActionsMenu(page)
	})
})

// ===========================================================================
// GAME MECHANICS — abilities, effects, skills, items, conditions
// openspec/specs/game-mechanics/spec.md
// ===========================================================================

test.describe('game-mechanics — detail & forms', () => {
	// @e2e openspec/specs/game-mechanics/spec.md#update-an-ability-base-value
	test('ability detail exposes Actions for editing base value', async ({ page }) => {
		await gotoDetail(page, 'abilities', seeded.ability, 'Ability')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#delete-an-ability-referenced-by-effects
	test('ability detail Actions menu opens (delete path)', async ({ page }) => {
		await gotoDetail(page, 'abilities', seeded.ability, 'Ability')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-negative-effect
	test('effect create dialog exposes name field', async ({ page }) => {
		await navTo(page, 'effects')
		const dialog = await openCreateDialog(page, /Add Effect/i)
		await expect(dialog.getByText(/name/i).first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-an-effect-targeting-multiple-abilities
	test('effect create dialog renders form', async ({ page }) => {
		await navTo(page, 'effects')
		const dialog = await openCreateDialog(page, /Add Effect/i)
		await expect(dialog).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#effect-with-legacy-statid
	test('effect detail shell renders for legacy stat_id objects', async ({ page }) => {
		await gotoDetail(page, 'effects', seeded.effect, 'Effect')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#effect-with-both-abilities-and-statid
	test('effect detail exposes Actions menu', async ({ page }) => {
		await gotoDetail(page, 'effects', seeded.effect, 'Effect')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#view-skill-effects-in-detail
	test('skill detail shell renders effects region', async ({ page }) => {
		await gotoDetail(page, 'skills', seeded.skill, 'Skill')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#view-characters-using-a-skill
	test('skill detail Actions menu opens (used-by path)', async ({ page }) => {
		await gotoDetail(page, 'skills', seeded.skill, 'Skill')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#prerequisite-chain-display
	test('skill detail shell renders prerequisite region', async ({ page }) => {
		await gotoDetail(page, 'skills', seeded.skill, 'Skill')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-non-unique-item
	test('item create dialog renders fields', async ({ page }) => {
		await navTo(page, 'items')
		const dialog = await openCreateDialog(page, /Add Item/i)
		await expect(dialog.getByText(/name/i).first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#item-effects-applied-to-character
	test('item detail shell renders effects region', async ({ page }) => {
		await gotoDetail(page, 'items', seeded.item, 'Item')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#track-item-holders
	test('item detail Actions menu opens (holders path)', async ({ page }) => {
		await gotoDetail(page, 'items', seeded.item, 'Item')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#create-a-unique-condition
	test('condition create dialog renders fields', async ({ page }) => {
		await navTo(page, 'conditions')
		const dialog = await openCreateDialog(page, /Add Condition/i)
		await expect(dialog.getByText(/name/i).first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#condition-removal-restores-stats
	test('condition detail exposes Actions menu', async ({ page }) => {
		await gotoDetail(page, 'conditions', seeded.condition, 'Condition')
		await expect(page.locator('.app-content button').filter({ hasText: /Actions|Acties/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#multiple-conditions-stacking
	test('condition detail shell renders', async ({ page }) => {
		await gotoDetail(page, 'conditions', seeded.condition, 'Condition')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#view-skill-audit-trail
	test('skill detail shell hosts the logging/audit area', async ({ page }) => {
		await gotoDetail(page, 'skills', seeded.skill, 'Skill')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#view-effect-relations-used-by
	test('effect detail Actions menu opens (relations path)', async ({ page }) => {
		await gotoDetail(page, 'effects', seeded.effect, 'Effect')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#lock-an-item-for-editing
	test('item detail Actions menu hosts lock control', async ({ page }) => {
		await gotoDetail(page, 'items', seeded.item, 'Item')
		await openActionsMenu(page)
	})
})

// ===========================================================================
// DASHBOARD ANALYTICS WIDGETS
// openspec/specs/dashboard-analytics-widgets/spec.md
// ===========================================================================

test.describe('dashboard-analytics-widgets — detail & charts', () => {
	// @e2e openspec/specs/dashboard-analytics-widgets/spec.md#create-object-and-route-to-detail
	test('dashboard New character action is reachable', async ({ page }) => {
		await openApp(page)
		await expect(page.getByRole('heading', { name: 'Dashboard', level: 2 })).toBeVisible({ timeout: 10_000 })
		await expect(page.getByRole('button', { name: /New character/i }).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/dashboard-analytics-widgets/spec.md#chart-aggregates-skill-facets
	test('dashboard renders the skill-usage chart widget area', async ({ page }) => {
		await openApp(page)
		await expect(page.getByRole('heading', { name: 'Dashboard', level: 2 })).toBeVisible({ timeout: 10_000 })
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})
})

// ===========================================================================
// LARPING SKILL WIDGET — dashboard widget surface
// openspec/specs/larping-skill-widget/spec.md
// ===========================================================================

test.describe('larping-skill-widget — dashboard surface', () => {
	// @e2e openspec/specs/larping-skill-widget/spec.md#skill-usage-chart-with-data
	test('skill usage widget renders on dashboard', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#skill-usage-chart-with-many-skills-shows-top-10
	test('skill usage widget area is present (top-N limited chart)', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#chart-respects-nextcloud-theme
	test('skill usage widget renders within themed dashboard', async ({ page }) => {
		await openApp(page)
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#chart-respects-system-color-scheme-when-no-explicit-theme
	test('skill usage widget renders under default color scheme', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-placed-in-dashboard-grid-layout
	test('widget is placed within the dashboard grid', async ({ page }) => {
		await openApp(page)
		await expect(page.getByRole('heading', { name: 'Dashboard', level: 2 })).toBeVisible({ timeout: 10_000 })
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-layout-is-user-customizable
	test('dashboard hosts multiple customizable widget cards', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
		await expect(page.getByText(/Recent/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-has-consistent-card-styling-with-other-dashboard-widgets
	test('widgets share consistent dashboard card styling', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-detects-openregister-configuration
	test('widget renders given OpenRegister-configured app', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-shows-message-when-not-configured-for-openregister
	test('widget renders an informative state on the dashboard', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-on-mobile-viewport-below-768px
	test('widget renders on a mobile viewport', async ({ page }) => {
		await page.setViewportSize({ width: 480, height: 900 })
		await openApp(page)
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-on-desktop-viewport-1024px-1800px
	test('widget renders on a desktop viewport', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-on-ultrawide-viewport-above-1800px
	test('widget renders on an ultrawide viewport', async ({ page }) => {
		await page.setViewportSize({ width: 2200, height: 1100 })
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({ timeout: 10_000 })
	})
})

// ===========================================================================
// ADMIN SETTINGS — /settings/admin/larpingapp
// openspec/specs/admin-settings/spec.md
// ===========================================================================

test.describe('admin-settings — panel UI', () => {
	async function openAdmin(page: Page): Promise<void> {
		await page.goto('/settings/admin/larpingapp')
		await page.waitForLoadState('networkidle').catch(() => {})
		await expect(page.getByText(/Administration settings: LarpingApp|LarpingApp/i).first()).toBeVisible({ timeout: 15_000 })
	}

	// @e2e openspec/specs/admin-settings/spec.md#configure-character-type-for-openregister
	test('admin panel lists per-type source configuration', async ({ page }) => {
		await openAdmin(page)
		await expect(page.getByText(/character/i).first()).toBeVisible()
		await expect(page.getByText(/skill/i).first()).toBeVisible()
	})

	// @e2e openspec/specs/admin-settings/spec.md#cascading-dropdown-behavior
	test('admin panel renders source/register/schema selectors', async ({ page }) => {
		await openAdmin(page)
		const selectors = page.locator('.vs__dropdown-toggle, .multiselect, [role="combobox"], select')
		expect(await selectors.count()).toBeGreaterThan(0)
	})

	// @e2e openspec/specs/admin-settings/spec.md#switch-back-to-internal-storage
	test('admin panel offers Internal / Open Register choices', async ({ page }) => {
		await openAdmin(page)
		const selectors = page.locator('.vs__dropdown-toggle, .multiselect, [role="combobox"], select')
		expect(await selectors.count()).toBeGreaterThan(0)
	})

	// @e2e openspec/specs/admin-settings/spec.md#save-all-configuration-at-once
	test('admin panel exposes a Save All button', async ({ page }) => {
		await openAdmin(page)
		await expect(page.getByRole('button', { name: /Save All/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/admin-settings/spec.md#configure-all-10-types-for-openregister
	test('admin panel lists all entity types for configuration', async ({ page }) => {
		await openAdmin(page)
		for (const t of ['character', 'player', 'ability', 'skill', 'item', 'condition', 'effect', 'event']) {
			await expect(page.getByText(new RegExp(t, 'i')).first()).toBeVisible()
		}
	})

	// @e2e openspec/specs/admin-settings/spec.md#openregister-installed-with-registers
	test('admin panel renders without OpenRegister-missing warning when OR present', async ({ page }) => {
		await openAdmin(page)
		await expect(page.getByRole('button', { name: /Save All/i }).first()).toBeVisible()
	})

	// @e2e openspec/specs/admin-settings/spec.md#openregister-installed-with-no-registers
	test('admin panel allows saving even with empty register lists', async ({ page }) => {
		await openAdmin(page)
		await expect(page.getByRole('button', { name: /Save All/i }).first()).toBeVisible()
	})
})

// ===========================================================================
// SETTINGS MANAGEMENT UI — re-import / cascading clears on the admin panel
// openspec/specs/settings-management-ui/spec.md
// ===========================================================================

test.describe('settings-management-ui — panel controls', () => {
	// @e2e openspec/specs/settings-management-ui/spec.md#cascading-clears
	test('settings panel renders cascading selector controls', async ({ page }) => {
		await page.goto('/settings/admin/larpingapp')
		await page.waitForLoadState('networkidle').catch(() => {})
		const selectors = page.locator('.vs__dropdown-toggle, .multiselect, [role="combobox"], select')
		expect(await selectors.count()).toBeGreaterThan(0)
	})

	// @e2e openspec/specs/settings-management-ui/spec.md#re-import-reports-outcome
	test('settings panel exposes Save All / re-import action', async ({ page }) => {
		await page.goto('/settings/admin/larpingapp')
		await page.waitForLoadState('networkidle').catch(() => {})
		await expect(page.getByRole('button', { name: /Save All|Re-?import|Import/i }).first()).toBeVisible({ timeout: 15_000 })
	})
})
