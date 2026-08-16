/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DEEP CRUD-with-persistence workflows for larpingapp — Character and Skill.
 *
 * Goal: prove the create / read / update / delete round-trip actually
 * PERSISTS, not just that a form renders. Each test creates a row, reads it
 * back and asserts the values survived, edits a field and asserts the change
 * survived, deletes it, and asserts it is gone.
 *
 * Where the persistence is asserted
 * ---------------------------------
 * The CRUD round-trips are asserted against OpenRegister's object store — the
 * authoritative backing store every larpingapp surface reads from. This is a
 * real end-to-end persistence check: each write goes through OR's saveObject
 * path and each read goes through OR's find()/findAll() path, exercising the
 * same storage the app uses.
 *
 * Why not the SPA forms?  (DEPLOY/ENV BLOCKERS, not larpingapp source bugs)
 * ------------------------------------------------------------------------
 * On this dev instance the SPA's data layer is degraded in two independent
 * ways, both of which prevent UI-driven CRUD assertions:
 *
 *   (1) LIST_EMPTY_BLOCKER — the index/list views fire NO object fetch at
 *       all (verified: navigating to /abilities issues only the OpenRegister
 *       integration JS request, never a GraphQL/objects query), so seeded
 *       rows never appear in the list and the "Add" button's submit path is
 *       not reliably reachable. Root cause is the register-config the store
 *       reads (`config.register`) resolving empty in the deployed bundle —
 *       the per-type register/schema live under the settings endpoint's
 *       nested `configuration` object (configuration.register=156,
 *       configuration.ability_register=8), and the deployed store does not
 *       pick them up. This is a registry-config / deploy-mismatch defect.
 *
 *   (2) DETAIL_SLUG_500_BLOCKER — detail pages fetch the object by the
 *       register *slug* (`/api/objects/larpingapp/<schema>/<id>`), which
 *       returns HTTP 500 because eleven `oc_openregister_registers` rows all
 *       share the slug "larpingapp" (env churn). The detail page therefore
 *       renders only its shell; object data + the per-object Actions menu
 *       (the UI edit/delete entry point) never render.
 *
 * Both are OpenRegister-side / environment-data defects, not larpingapp
 * source bugs and not test defects, and cannot be worked around from the
 * test layer. The UI-driven variants of each round-trip are kept as
 * `test.fixme` referencing the blocker so the intent is documented and the
 * tests light up automatically once the env is repaired (dedupe the
 * duplicate `larpingapp` registers + fix the store's config read).
 *
 * The SPA *shell* assertions (list view loads, Add control present, detail
 * heading renders) remain active because they render data-independently.
 */

import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { navTo as sharedNavTo, dismissSupportDialog } from '../_nav'
import {
	BASE,
	RUN_ID,
	fixtureName,
	newApi,
	FixtureLedger,
	createObject,
	getObject,
	updateObject,
	deleteObject,
	cleanupLedger,
	resolveSchemaIds,
} from './fixtures'

// Documented blocker reasons; each is annotated onto its test.fixme below via
// test.info().annotations so the reason travels with the parked test.
let api: APIRequestContext
const ledger = new FixtureLedger()

/**
 * A real player object id, for `character.ocName`.
 *
 * `ocName` is NOT a display name — the live `character` schema declares it
 * `{"type":"string","format":"uuid","$ref":19,"x-allow-create":true}`, i.e. a
 * RELATION to a `player` object ("The player who plays this character"). It is
 * also `required`. Passing the character's own name string yields
 * HTTP 400 "Property 'ocName' should match format 'uuid'".
 * The schema grew this relation and the specs were never updated — invisible
 * until now, because this suite could not run at all.
 */
let playerRef: string

async function ensurePlayerRef(): Promise<string> {
	if (!playerRef) {
		playerRef = await createObject(api, 'player', {
			name: fixtureName('crud-owner-player'),
		})
		ledger.track('player', playerRef)
	}
	return playerRef
}

test.beforeAll(async () => {
	api = await newApi()
	// MUST resolve schema ids from the app's own settings before any create.
	// The bootstrap literals in fixtures.ts are a fallback, not truth: schema
	// slugs collide across the fleet, so a hardcoded numeric id silently binds
	// to ANOTHER app's schema. Measured on this instance — id 21 ("skill")
	// requires `title`, and id 22 ("item") requires
	// title/interactionType/qtiBody/maxScore/tenant_id, i.e. scholiq's QTI
	// item — so every create here failed with HTTP 400 "required property
	// (title) is missing". `resolveSchemaIds` already existed for exactly this
	// reason; this spec simply never called it.
	await resolveSchemaIds(api)
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

// ---------------------------------------------------------------------------
// Small UI helpers (shell-level; data-independent so they survive the
// LIST_EMPTY / DETAIL_500 blockers).
// ---------------------------------------------------------------------------

async function openApp(page: Page): Promise<void> {
	if (!page.url().includes('/apps/larpingapp')) {
		await page.goto(`${BASE}/`)
		// ADR-074 rule 4: `networkidle` never settles on Nextcloud.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
	}
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 15_000 })
	// Shared helper — see `../_nav`. The local copy matched only
	// `aria-label="Close"` and never dismissed the onboarding tour.
	await dismissSupportDialog(page)
}

/**
 * Reach `slug`'s index page through the real sidebar.
 *
 * Delegates to the shared helper in `tests/e2e/_nav.ts`. The previous local
 * copy used `.app-navigation a[href=…]`, which matches nothing (entries are
 * NcAppNavigationItem `<li>`s addressed by `data-testid`) and never expanded
 * the collapsible group — see `_nav.ts` for the four verified traps.
 */
async function navTo(page: Page, slug: string): Promise<void> {
	await sharedNavTo(page, slug)
}

/**
 * Assert a freshly-created row surfaces in the current index list.
 *
 * The index lists are server-paginated (default 20 rows/page). On the shared
 * dev instance the registers hold dozens of accumulated objects, so a newly
 * created row lands on a later page rather than page 1. We therefore page
 * forward (clicking the list's "Next" control) until the row is visible or the
 * pages are exhausted — this honestly verifies the created object surfaces in
 * the list UI without assuming it lands on the first page (which only held on a
 * near-empty register).
 */
async function expectRowInList(
	page: Page,
	text: string,
	maxPages = 12,
): Promise<void> {
	const target = page.locator('.app-content').getByText(text).first()
	for (let i = 0; i < maxPages; i++) {
		if (
			await target
				.isVisible({ timeout: i === 0 ? 8_000 : 2_000 })
				.catch(() => false)
		) {
			await expect(target).toBeVisible()
			return
		}
		const next = page
			.locator('.app-content')
			.getByRole('button', { name: /^\s*Next\s*$/i })
			.first()
		const canPage = await next.isEnabled({ timeout: 1_000 }).catch(() => false)
		if (!canPage) break
		await next.click().catch(() => {})
		await page.waitForTimeout(800)
	}
	// Final assertion surfaces a clear failure if the row never appeared.
	await expect(
		target,
		`Created row "${text}" must surface in the list (paged ${maxPages})`,
	).toBeVisible({ timeout: 5_000 })
}

// ===========================================================================
// CHARACTER — full CRUD-with-persistence (store-authoritative round-trip)
// ===========================================================================

test.describe('character — CRUD persistence (store round-trip)', () => {
	test('create → read-back persists the exact field values', async () => {
		const name = fixtureName('crud-character')
		const id = ledger.track(
			'character',
			await createObject(api, 'character', {
				name,
				ocName: await ensurePlayerRef(),
				type: 'player',
				gold: 7,
				silver: 4,
				copper: 1,
				background: 'Born under a blood moon',
			}),
		)

		const read = await getObject(api, 'character', id)
		expect(read, 'created character must be readable back').not.toBeNull()
		expect(read!.name).toBe(name)
		expect(Number(read!.gold)).toBe(7)
		expect(Number(read!.silver)).toBe(4)
		expect(read!.background).toBe('Born under a blood moon')
		expect(read!.type).toBe('player')
	})

	test('edit → read-back persists the updated values', async () => {
		const name = fixtureName('crud-character-edit')
		const id = ledger.track(
			'character',
			await createObject(api, 'character', {
				name,
				ocName: await ensurePlayerRef(),
				type: 'player',
				gold: 1,
				background: 'Apprentice',
			}),
		)

		await updateObject(api, 'character', id, {
			name,
			ocName: await ensurePlayerRef(),
			type: 'npc',
			gold: 99,
			background: 'Risen to power',
		})

		const read = await getObject(api, 'character', id)
		expect(read, 'edited character must be readable back').not.toBeNull()
		expect(Number(read!.gold)).toBe(99)
		expect(read!.background).toBe('Risen to power')
		expect(read!.type).toBe('npc')
	})

	test('delete → read-back returns gone (404/null)', async () => {
		const name = fixtureName('crud-character-del')
		const id = await createObject(api, 'character', {
			name,
			ocName: await ensurePlayerRef(),
			type: 'player',
		})

		// Sanity: present before delete.
		expect(await getObject(api, 'character', id)).not.toBeNull()

		const deleted = await deleteObject(api, 'character', id)
		expect(deleted, 'delete must succeed').toBe(true)

		const afterDelete = await getObject(api, 'character', id)
		expect(afterDelete, 'deleted character must be gone').toBeNull()
	})

	// --- UI-driven variants (data surfacing) — blocked on this instance ---

	// @e2e openspec/specs/character-management/spec.md#create-a-character
	// FIXED (2026-06-10, wave-3): OR-core dedup + store now registers the
	// per-type register/schema (config.<type>_register), so the Characters list
	// fires its object fetch and the seeded row surfaces.
	test('UI: created character row appears in the Characters list', async ({
		page,
	}) => {
		const name = fixtureName('ui-character')
		ledger.track(
			'character',
			await createObject(api, 'character', {
				name,
				ocName: await ensurePlayerRef(),
				type: 'player',
			}),
		)
		await navTo(page, 'characters')
		await expectRowInList(page, name)
	})

	// @e2e openspec/specs/character-management/spec.md#view-currency-on-character-sheet
	// FIXED (2026-06-10, wave-3): OR-core dedup resolves the larpingapp slug, so
	// the slug-based detail fetch returns 200 (was 500) and the persisted object
	// data renders on the detail page.
	test('UI: character detail renders the persisted currency values', async ({
		page,
	}) => {
		const name = fixtureName('ui-character-detail')
		const id = ledger.track(
			'character',
			await createObject(api, 'character', {
				name,
				ocName: await ensurePlayerRef(),
				type: 'player',
				gold: 42,
			}),
		)
		// Hash-mode deep link (src/main.js — fleet #133): the detail route is
		// addressed via the URL hash, served from the SPA root and resolved
		// client-side.
		await page.goto(`${BASE}/#/characters/${id}`)
		// ADR-074 rule 4: `networkidle` never settles on Nextcloud.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		await expect(
			page.locator('.app-content').getByText('42').first(),
		).toBeVisible({ timeout: 10_000 })
	})

	test('UI shell: Characters list view + Add control render', async ({ page }) => {
		await navTo(page, 'characters')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Add Character/i })
				.first(),
		).toBeVisible({ timeout: 10_000 })
	})
})

// ===========================================================================
// SKILL — full CRUD-with-persistence (store-authoritative round-trip)
// ===========================================================================

test.describe('skill — CRUD persistence (store round-trip)', () => {
	test('create → read-back persists name + description', async () => {
		const name = fixtureName('crud-skill')
		const id = ledger.track(
			'skill',
			await createObject(api, 'skill', {
				name,
				description: 'Cleave through two foes',
			}),
		)

		const read = await getObject(api, 'skill', id)
		expect(read, 'created skill must be readable back').not.toBeNull()
		expect(read!.name).toBe(name)
		expect(read!.description).toBe('Cleave through two foes')
	})

	test('edit → read-back persists the updated description and effect link', async () => {
		const name = fixtureName('crud-skill-edit')
		// An effect to link, so we can assert relation persistence too.
		const effectId = ledger.track(
			'effect',
			await createObject(api, 'effect', {
				name: fixtureName('crud-skill-edit-effect'),
				modifier: 2,
				modification: 'positive',
			}),
		)
		const id = ledger.track(
			'skill',
			await createObject(api, 'skill', {
				name,
				description: 'Basic strike',
			}),
		)

		await updateObject(api, 'skill', id, {
			name,
			description: 'Master strike',
			effects: [effectId],
		})

		const read = await getObject(api, 'skill', id)
		expect(read, 'edited skill must be readable back').not.toBeNull()
		expect(read!.description).toBe('Master strike')
		expect(Array.isArray(read!.effects) ? read!.effects : []).toContain(effectId)
	})

	test('delete → read-back returns gone (404/null)', async () => {
		const name = fixtureName('crud-skill-del')
		const id = await createObject(api, 'skill', {
			name,
			description: 'Temporary',
		})

		expect(await getObject(api, 'skill', id)).not.toBeNull()

		const deleted = await deleteObject(api, 'skill', id)
		expect(deleted, 'delete must succeed').toBe(true)

		expect(
			await getObject(api, 'skill', id),
			'deleted skill must be gone',
		).toBeNull()
	})

	// @e2e openspec/specs/skill-management/spec.md#create-a-skill
	// FIXED (2026-06-10, wave-3): OR-core dedup + the per-type register/schema
	// store registration make the Skills list fire its fetch; the seeded row
	// surfaces.
	test('UI: created skill row appears in the Skills list', async ({ page }) => {
		const name = fixtureName('ui-skill')
		ledger.track(
			'skill',
			await createObject(api, 'skill', { name, description: 'UI skill' }),
		)
		await navTo(page, 'skills')
		await expectRowInList(page, name)
	})

	test('UI shell: Skills list view + Add control render', async ({ page }) => {
		await navTo(page, 'skills')
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Add Skill/i })
				.first(),
		).toBeVisible({ timeout: 10_000 })
	})
})

test.afterAll(() => {
	// eslint-disable-next-line no-console
	console.log(
		`[crud-persistence] RUN_ID=${RUN_ID} — fixtures cleaned up via ledger.`,
	)
})
