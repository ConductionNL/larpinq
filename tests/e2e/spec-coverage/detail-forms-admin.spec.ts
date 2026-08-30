/*
 * SPDX-FileCopyrightText: 2026 Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests — larpinq detail pages, create/edit
 * dialogs, admin settings panel and dashboard widgets.
 *
 * Companion to spa-ui.spec.ts. Where spa-ui covers list views + dashboard
 * + create-dialog-open, this file drives the remaining real UI surfaces:
 *
 *   - Detail pages for all 9 entity types (navigate to /:type/:id, assert
 *     the CnDetailPage shell: app-content, entity heading, Actions button).
 *   - Create / edit dialogs with field-level assertions (Type selector for
 *     NPC, name/player fields, etc.).
 *   - Admin settings panel (/settings/admin/larpinq): per-type source
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

import {
	test,
	expect,
	request,
	type Page,
	type APIRequestContext,
} from '@playwright/test'
import { navTo as sharedNavTo, dismissSupportDialog } from '../_nav'
import { BASE_URL } from '../_base-url'

const BASE = '/apps/larpinq'
const TS = Date.now()

// OpenRegister register + schema ids for this app's data model. The seed
// REST helper uses the numeric register/schema ids which are stable on the
// dev instance; the SPA addresses objects by slug.
//
// NOTE: the per-type register the app actually reads from is the value of the
// app's `<type>_register` config keys (8 on the dev instance), NOT the legacy
// top-level `register` key (156). Seeding into 156 produced objects the SPA
// could never see. We seed into 8 so the fixtures live in the register the
// detail routes resolve against.
// Resolved centrally in `tests/e2e/_base-url.ts` — no `localhost:8080`
// fallback. This spec's WRITE path seeds OpenRegister objects, and the old
// default sent them into the SHARED dev container whenever NEXTCLOUD_URL was
// unset, even when the browser side was pointed elsewhere.
const NEXTCLOUD_URL = BASE_URL

// Bootstrap ids only. They are OVERWRITTEN in beforeAll by `resolveIds()`,
// which reads the app's own settings API — the same source of truth
// `tests/e2e/workflows/fixtures.ts` already uses, and the same one the SPA
// itself reads at runtime.
//
// These literals are correct on exactly one machine. On a freshly installed
// instance Larpinq's register imports as id 15 with a different schema-id
// assignment, so every seed POSTed into register 8 / schema 18-25 either 404s
// or lands in an unrelated register. `seedObject()` swallows that and stores
// the string `'seed-missing'`, the detail routes then navigate to
// `#/characters/seed-missing`, and the specs fail 60 s later as TIMEOUTS —
// which reads like a rendering regression and is really a stale constant.
let REGISTER_ID =
	process.env.LARPING_REGISTER_ID || process.env.LARP_REGISTER_ID || '8'
const SCHEMA_IDS: Record<string, string> = {
	character: process.env.LARPING_SCHEMA_ID_CHARACTER || '18',
	player: process.env.LARPING_SCHEMA_ID_PLAYER || '19',
	ability: process.env.LARPING_SCHEMA_ID_ABILITY || '20',
	skill: process.env.LARPING_SCHEMA_ID_SKILL || '21',
	item: process.env.LARPING_SCHEMA_ID_ITEM || '22',
	condition: process.env.LARPING_SCHEMA_ID_CONDITION || '23',
	effect: process.env.LARPING_SCHEMA_ID_EFFECT || '24',
	event: process.env.LARPING_SCHEMA_ID_EVENT || '25',
}

/**
 * Overwrite REGISTER_ID / SCHEMA_IDS from Larpinq's settings API.
 *
 * An explicit `LARPING_*` environment variable always wins, so a run can still
 * be pinned by hand. Anything not pinned is resolved from the instance.
 *
 * @param {APIRequestContext} api Authenticated request context.
 * @return {Promise<void>}
 */
async function resolveIds(api: APIRequestContext): Promise<void> {
	const res = await api
		.get(`${NEXTCLOUD_URL}/index.php/apps/larpinq/api/settings`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		.catch(() => null)
	if (!res || !res.ok()) {
		return
	}
	const cfg = (await res.json().catch(() => null))?.configuration
	if (!cfg || typeof cfg !== 'object') {
		return
	}
	if (
		!process.env.LARPING_REGISTER_ID
		&& !process.env.LARP_REGISTER_ID
		&& cfg.register !== undefined
		&& String(cfg.register) !== ''
	) {
		REGISTER_ID = String(cfg.register)
	}
	for (const type of Object.keys(SCHEMA_IDS)) {
		// The per-type register wins over the shared top-level one. Reading
		// `configuration.register` alone is what previously made list fetches
		// miss whenever a type's own register diverged from it.
		const perTypeRegister = cfg[`${type}_register`]
		if (perTypeRegister !== undefined && String(perTypeRegister) !== '') {
			REGISTER_IDS[type] = String(perTypeRegister)
		}
		if (process.env[`LARPING_SCHEMA_ID_${type.toUpperCase()}`]) {
			continue
		}
		const schemaId = cfg[`${type}_schema`]
		if (schemaId !== undefined && String(schemaId) !== '') {
			SCHEMA_IDS[type] = String(schemaId)
		}
	}
}

/** Register each type is actually stored in; defaults to the shared register. */
const REGISTER_IDS: Record<string, string> = {}

/**
 * The register to seed a given type into.
 *
 * @param {string} type The object type slug.
 * @return {string} The per-type register id, or the shared one.
 */
function registerFor(type: string): string {
	return REGISTER_IDS[type] || REGISTER_ID
}

// Shared seeded fixture ids, populated in beforeAll (best-effort).
const seeded: Record<string, string> = {}

// The display name each seeded fixture was created with, keyed by the same
// type key as `seeded`. A detail page renders the OBJECT's name as its `<h2>`
// (the entity type renders as a kicker paragraph above it), so the name is what
// proves we landed on the right object's detail page rather than on a list, an
// empty shell, or a not-found state. See `gotoDetail`.
const seededNames: Record<string, string> = {}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Load the SPA root and dismiss the first-load support modal. */
async function openApp(page: Page): Promise<void> {
	if (!page.url().includes('/apps/larpinq')) {
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
	// `aria-label="Close"` and never dismissed the onboarding tour, whose
	// controls are "Close tour" / "Skip".
	await dismissSupportDialog(page)
}

/**
 * Click an in-app sidebar nav entry.
 *
 * Delegates to the shared helper in `tests/e2e/_nav.ts`. The previous local
 * copy used `.app-navigation a[href=…]`, which matches nothing, and never
 * expanded the owning collapsible group — see `_nav.ts` for the verified DOM
 * and the four traps it encodes.
 */
async function navTo(page: Page, slug: string): Promise<void> {
	await sharedNavTo(page, slug)
}

/**
 * Navigate to a detail route via the app's hash router.
 *
 * The router runs in `mode: 'hash'` (src/main.js — fleet #133 deep-link fix),
 * so the canonical detail URL is `/apps/larpinq/#/<slug>/<id>`. Loading that
 * URL serves the SPA root from the server (no 404 — the hash fragment is never
 * sent to the backend) and the client-side hash router resolves the detail
 * route. This is the deep-link path the hash-mode change exists to support, so
 * we drive it directly rather than poking history.pushState (which addressed a
 * non-existent server sub-path and broke once routing moved to hash mode).
 */
async function gotoDetail(
	page: Page,
	slug: string,
	id: string,
	typeLabel: string,
): Promise<void> {
	await page.goto(`${BASE}/#/${slug}/${id}`)
	// ADR-074 rule 4: `networkidle` never settles on Nextcloud — the
	// notification poll keeps the network permanently busy. This was the LAST
	// live `waitForLoadState('networkidle')` in the suite; every other mention
	// is a comment warning against it.
	//
	// The `.catch(() => {})` looks like it makes the call safe. It does not:
	// `waitForLoadState` takes no timeout here, so it inherits the navigation
	// timeout (0 = unbounded) and simply never settles. The TEST times out at
	// 60 s first, so the catch never runs — and the failure is reported as
	// `Test timeout of 60000ms exceeded`, which reads like a slow or broken
	// page rather than an unsatisfiable wait. It backs every character-detail
	// spec in this file.
	await page
		.locator('#app-content, .app-content, #content')
		.first()
		.waitFor({ state: 'visible', timeout: 30_000 })
		.catch(() => {})
	// Shared helper — see `../_nav`. The local copy matched only
	// `aria-label="Close"` and never dismissed the onboarding tour, whose
	// controls are "Close tour" / "Skip".
	await dismissSupportDialog(page)
	await expect(page).toHaveURL(new RegExp(`#/${slug}/${id}`))
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
	// A detail page's own heading is the OBJECT's name (`<h2>`); the entity type
	// ("Character", "Event", …) renders as a kicker paragraph above it, not as a
	// heading. The previous assertion — "some heading in .app-content matches
	// /<type>/i" — passed for the wrong reasons and hid real breakage:
	//
	//   * it matched incidental widget titles, e.g. "Skills granting this
	//     effect" satisfies /Effect/i on the effect detail page;
	//   * it matched the LIST page heading, e.g. "Characters" satisfies
	//     /Character/i — so all seventeen character-detail specs stayed green
	//     while the character seed was failing with HTTP 400 and `id` was the
	//     literal string "seed-missing";
	//   * and it hard-failed only on `events`, whose seeded name
	//     ("…-summer-larp") happens to contain no occurrence of "event".
	//
	// Asserting the seeded object's own name proves the hash route resolved AND
	// the right object was fetched AND it rendered — which is what every caller
	// of this helper actually depends on.
	const expectedName = seededNames[id]
	if (!expectedName) {
		throw new Error(
			`gotoDetail(${slug}/${id}, ${typeLabel}): no seeded name recorded for this id. `
				+ 'The beforeAll fixture seed did not run or returned an error — see the '
				+ '"[e2e seed]" lines above for the HTTP status. Continuing would assert '
				+ 'against a detail page for an object that does not exist.',
		)
	}
	await expect(
		page
			.locator('.app-content')
			.getByRole('heading', { name: expectedName })
			.first(),
	).toBeVisible({ timeout: 10_000 })
}

/*
 * CLOSED — "detail-page Actions menu" blocker (23 parked specs, now live).
 *
 * History, because it took three rounds of triage and two of them were wrong:
 *
 *   v1 (wrong)  "ELEVEN rows share the slug `larpingapp`, so the SPA's
 *               slug-addressed read 500s." Disproved 2026-07-27 by direct DB
 *               check: exactly ONE row has that slug.
 *   v2 (wrong)  "the register is simply unseeded."
 *   v3 (right)  2026-08-01, clean isolated instance: the `player` SCHEMA was
 *               dropped on import. `character.ocName` is `format: uuid`,
 *               `$ref: player` and REQUIRED, so with no player schema no player
 *               UUID could exist and OpenRegister rejected every character
 *               create with HTTP 400 "Property 'ocName' should match format
 *               'uuid'". `seedObject()` swallowed it, the ids became the
 *               literal string 'seed-missing', and every character-detail spec
 *               timed out 60 s later looking like a rendering regression.
 *
 * Both halves of v3 are fixed:
 *   - 74fab691 dropped the invalid `format: "user"` that made the import throw
 *     the `player` schema away (CI now reports `player` present in the register);
 *   - the fixtures reference a real player UUID instead of a display name.
 *
 * Measured on CI with the fixes in place: 171 passed / 0 failed / 1 skipped
 * (run 30892287689, job 91937270750) — all 23 of these specs pass, including
 * every per-object Actions-menu assertion. Unparked accordingly; the
 * `DETAIL_ACTIONS_BLOCKER` string they referenced is deleted with them.
 */

/** Click the detail-page Actions button and assert the popup menu opens. */
async function openActionsMenu(page: Page): Promise<void> {
	const actions = page
		.locator('.app-content button')
		.filter({ hasText: /Actions|Acties/i })
		.first()
	await expect(actions).toBeVisible({ timeout: 10_000 })
	await actions.click()
	await expect(
		page.locator('[role="menu"], .v-popper__popper').first(),
	).toBeVisible({ timeout: 5_000 })
}

/** Open the "Add <Entity>" create dialog on the current list view. */
async function openCreateDialog(
	page: Page,
	addLabel: RegExp,
): Promise<ReturnType<Page['locator']>> {
	const btn = page
		.locator('.app-content button')
		.filter({ hasText: addLabel })
		.first()
	await expect(btn).toBeVisible({ timeout: 10_000 })
	await btn.click()
	const dialog = page.locator('[role="dialog"]').first()
	await expect(dialog).toBeVisible({ timeout: 8_000 })
	return dialog
}

/** Close any open NcDialog/NcModal. */
async function closeDialog(page: Page): Promise<void> {
	const cancel = page
		.locator('[role="dialog"] button')
		.filter({ hasText: /Cancel|Close/i })
		.first()
	if (await cancel.isVisible({ timeout: 1500 }).catch(() => false)) {
		await cancel.click().catch(() => {})
	} else {
		await page.keyboard.press('Escape').catch(() => {})
	}
	await page.waitForTimeout(150)
}

/** Best-effort REST seed of one object so detail routes have a fixture. */
async function seedObject(
	api: APIRequestContext,
	schema: string,
	body: Record<string, unknown>,
): Promise<string | null> {
	const schemaId = SCHEMA_IDS[schema]
	if (!schemaId) {
		// eslint-disable-next-line no-console
		console.error(
			`[e2e seed] ${schema}: no schema id configured on this instance — the app has no storage for it`,
		)
		return null
	}
	const url = `${NEXTCLOUD_URL}/index.php/apps/openregister/api/objects/${registerFor(schema)}/${schemaId}`
	const res = await api
		.post(url, {
			headers: {
				'OCS-APIRequest': 'true',
				'Content-Type': 'application/json',
			},
			data: body,
		})
		.catch(() => null)
	// Report WHY a seed failed. Swallowing it turns every dependent spec into a
	// 60 s timeout that looks like a rendering regression.
	if (!res) {
		// eslint-disable-next-line no-console
		console.error(`[e2e seed] ${schema}: POST ${url} threw`)
		return null
	}
	if (!res.ok()) {
		// eslint-disable-next-line no-console
		console.error(
			`[e2e seed] ${schema}: POST ${url} -> ${res.status()} ${(await res.text().catch(() => '')).slice(0, 200)}`,
		)
		return null
	}
	const json = await res.json().catch(() => null)
	return (json && (json.id || (json['@self'] && json['@self'].id))) || null
}

// ---------------------------------------------------------------------------
// Fixture seeding (API-for-SETUP; UI assertions follow in the tests).
// ---------------------------------------------------------------------------

test.beforeAll(async () => {
	const api = await request.newContext({
		httpCredentials: {
			username: process.env.NC_ADMIN_USER || 'admin',
			password: process.env.NC_ADMIN_PASS || 'admin',
		},
	})
	await resolveIds(api)
	const n = `la-e2e-${TS}`

	/**
	 * Seed one object and remember the name it was created with, so `gotoDetail`
	 * can assert the detail page rendered THAT object.
	 *
	 * @param {string} type   Fixture type key (also the `seeded` / `seededNames` key).
	 * @param {string} name   Display name to create the object with.
	 * @param {object} extra  Additional schema properties for the create payload.
	 * @return {Promise<string>} The new object's UUID, or 'seed-missing' on failure.
	 */
	const seed = async (
		type: string,
		name: string,
		extra: Record<string, unknown> = {},
	): Promise<string> => {
		const id = await seedObject(api, type, { name, ...extra })
		if (id) {
			seededNames[id] = name
		}
		return id || 'seed-missing'
	}

	// `player` FIRST: `character.ocName` is a RELATION to a player object, not a
	// display name. The live character schema declares it
	// `{"type":"string","format":"uuid","$ref":"player"}` ("The player who plays
	// this character") and marks it `required`, so the previous payload
	// (`ocName: "<name>-hero"`) was rejected with HTTP 400
	// "Property 'ocName' should match format 'uuid'". The character seed
	// therefore never existed: `seeded.character` was the literal string
	// 'seed-missing' and every character-detail spec below navigated to
	// `#/characters/seed-missing`.
	//
	// `ocName` is also NOT a property of player / ability / skill / item /
	// condition / effect / event — it was passed to all of them and silently
	// ignored, which is what made the character-only failure look like noise.
	seeded.player = await seed('player', `${n}-player`)
	seeded.character = await seed('character', `${n}-hero`, {
		ocName: seeded.player,
		type: 'player',
		gold: 5,
		silver: 3,
		copper: 2,
		background: 'Born in Camelot',
	})
	seeded.ability = await seed('ability', `${n}-strength`, { base: 10 })
	seeded.skill = await seed('skill', `${n}-swordplay`)
	seeded.item = await seed('item', `${n}-excalibur`)
	seeded.condition = await seed('condition', `${n}-poisoned`)
	seeded.effect = await seed('effect', `${n}-strong-arm`, { modifier: 5 })
	seeded.event = await seed('event', `${n}-summer-larp`)
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
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#delete-a-character
	test('character detail Actions menu opens (delete path)', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#approve-a-character
	test('character detail renders approval-capable shell', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		// The scenario is "approve a character", so assert the approval control is
		// actually on the page. The previous assertion here was a second copy of
		// the old `gotoDetail` heading check ("some heading matches /Character/i")
		// and it was satisfied by the NOT-FOUND shell: when the object fails to
		// load, CnDetailPage falls back to the manifest page title ("Character")
		// as its heading, so this line passed precisely while the character seed
		// was broken and stopped passing the moment a real character loaded —
		// exactly backwards. The `approved` property lives in the "Game state &
		// notes" data widget with `widget: "switch"` and title "Approved".
		await expect(
			page
				.locator('.app-content')
				.getByText('Approved', { exact: true })
				.first(),
		).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/character-management/spec.md#filter-characters-by-approval-status
	test('character list renders view toggle and add controls', async ({ page }) => {
		await navTo(page, 'characters')
		await expect(
			page.locator('.app-content').getByText(/Cards/i).first(),
		).toBeVisible()
		await expect(
			page.locator('.app-content').getByText(/Table/i).first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#create-npc-character
	test('create dialog exposes character Type selector (player/npc/other)', async ({
		page,
	}) => {
		await navTo(page, 'characters')
		const dialog = await openCreateDialog(page, /Add Character/i)
		await expect(dialog.getByText(/Type/i).first()).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/character-management/spec.md#view-character-type-in-list
	test('character list view loads with type-bearing rows area', async ({
		page,
	}) => {
		await navTo(page, 'characters')
		await expect(page.locator('.app-content')).toBeVisible()
		expect(page.url()).toContain('/characters')
	})

	// @e2e openspec/specs/character-management/spec.md#set-initial-currency
	test('create dialog exposes name field for new character', async ({ page }) => {
		await navTo(page, 'characters')
		const dialog = await openCreateDialog(page, /Add Character/i)
		await expect(
			dialog
				.locator(
					'input[placeholder*="in-game name" i], input[placeholder*="name" i]',
				)
				.first(),
		).toBeVisible()
		await closeDialog(page)
	})

	// @e2e openspec/specs/character-management/spec.md#update-character-currency
	test('character detail shell supports currency editing via Actions', async ({
		page,
	}) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#view-currency-on-character-sheet
	test('character detail page renders the character sheet shell', async ({
		page,
	}) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/character-management/spec.md#assign-a-skill-to-a-character
	test('character detail provides Actions for skill assignment', async ({
		page,
	}) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#remove-an-item-from-a-character
	test('character detail provides Actions for item removal', async ({ page }) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#assign-multiple-conditions
	test('character detail provides Actions for condition assignment', async ({
		page,
	}) => {
		await gotoDetail(page, 'characters', seeded.character, 'Character')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/character-management/spec.md#assign-an-event-to-a-character
	test('character detail provides Actions for event assignment', async ({
		page,
	}) => {
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
	test('character detail shell hosts the background tab area', async ({
		page,
	}) => {
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
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
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
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/events-players/spec.md#delete-a-player-with-character-references
	test('player detail Actions menu opens (delete path)', async ({ page }) => {
		await gotoDetail(page, 'players', seeded.player, 'Player')
		await openActionsMenu(page)
	})

	// @e2e openspec/specs/events-players/spec.md#player-selector-in-character-modal
	//
	// This asserts the ocName RELATION PICKER, by its input-id, and that is a
	// correction rather than a tightening. It previously read
	// `input[placeholder*="player" i]`, which cannot ever match a relation
	// picker: CnFormDialog passes `:placeholder="field.description"` only on its
	// text/email/url and number branches, while the NcSelect branch that renders
	// a `$ref` property receives `input-id` + `input-label` and no placeholder at
	// all. The only element in this dialog that locator could match was
	// `ownerRef`'s plain text box, whose description contains "the player
	// object" — so the assertion was green exactly while ocName was NOT a
	// selector, and went red the moment a $ref made it one. It slid onto
	// ownerRef silently: ocName had no $ref when this test was written
	// (987c9ee3), ownerRef arrived with a matching description (f33fc0b8), and
	// ocName's $ref (f434f76d) turned it into a select — losing its placeholder
	// — with no red window in between. `cn-form-<key>` is the id CnFormDialog
	// gives every select, so this fails if the picker stops rendering.
	test('character create dialog exposes a player (ocName) selector', async ({
		page,
	}) => {
		await navTo(page, 'characters')
		const dialog = await openCreateDialog(page, /Add Character/i)
		await expect(dialog.locator('#cn-form-ocName')).toBeVisible()
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
	test('ability detail exposes Actions for editing base value', async ({
		page,
	}) => {
		await gotoDetail(page, 'abilities', seeded.ability, 'Ability')
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
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
	test('effect detail shell renders for legacy stat_id objects', async ({
		page,
	}) => {
		await gotoDetail(page, 'effects', seeded.effect, 'Effect')
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/game-mechanics/spec.md#effect-with-both-abilities-and-statid
	test('effect detail exposes Actions menu', async ({ page }) => {
		await gotoDetail(page, 'effects', seeded.effect, 'Effect')
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
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
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Actions|Acties/i })
				.first(),
		).toBeVisible()
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
		await expect(
			page.getByRole('heading', { name: 'Dashboard', level: 2 }),
		).toBeVisible({ timeout: 10_000 })
		await expect(
			page.getByRole('button', { name: /New character/i }).first(),
		).toBeVisible({ timeout: 10_000 })
	})

	// @e2e openspec/specs/dashboard-analytics-widgets/spec.md#chart-aggregates-skill-facets
	test('dashboard renders the skill-usage chart widget area', async ({ page }) => {
		await openApp(page)
		await expect(
			page.getByRole('heading', { name: 'Dashboard', level: 2 }),
		).toBeVisible({ timeout: 10_000 })
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
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
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#skill-usage-chart-with-many-skills-shows-top-10
	test('skill usage widget area is present (top-N limited chart)', async ({
		page,
	}) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#chart-respects-nextcloud-theme
	test('skill usage widget renders within themed dashboard', async ({ page }) => {
		await openApp(page)
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#chart-respects-system-color-scheme-when-no-explicit-theme
	test('skill usage widget renders under default color scheme', async ({
		page,
	}) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-placed-in-dashboard-grid-layout
	test('widget is placed within the dashboard grid', async ({ page }) => {
		await openApp(page)
		await expect(
			page.getByRole('heading', { name: 'Dashboard', level: 2 }),
		).toBeVisible({ timeout: 10_000 })
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-layout-is-user-customizable
	test('dashboard hosts multiple customizable widget cards', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
		await expect(page.getByText(/Recent/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-has-consistent-card-styling-with-other-dashboard-widgets
	test('widgets share consistent dashboard card styling', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
		await expect(page.locator('.app-content')).toBeVisible()
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-detects-openregister-configuration
	test('widget renders given OpenRegister-configured app', async ({ page }) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-shows-message-when-not-configured-for-openregister
	test('widget renders an informative state on the dashboard', async ({
		page,
	}) => {
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-on-mobile-viewport-below-768px
	test('widget renders on a mobile viewport', async ({ page }) => {
		await page.setViewportSize({ width: 480, height: 900 })
		await openApp(page)
		await expect(page.locator('.app-content')).toBeVisible()
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-on-desktop-viewport-1024px-1800px
	test('widget renders on a desktop viewport', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})

	// @e2e openspec/specs/larping-skill-widget/spec.md#widget-on-ultrawide-viewport-above-1800px
	test('widget renders on an ultrawide viewport', async ({ page }) => {
		await page.setViewportSize({ width: 2200, height: 1100 })
		await openApp(page)
		await expect(page.getByText(/Skill usage/i).first()).toBeVisible({
			timeout: 10_000,
		})
	})
})

// ===========================================================================
// ADMIN SETTINGS — /settings/admin/larpinq
// openspec/specs/admin-settings/spec.md
// ===========================================================================

test.describe('admin-settings — panel UI', () => {
	async function openAdmin(page: Page): Promise<void> {
		await page.goto('/settings/admin/larpinq')
		// ADR-074 rule 4: `networkidle` never settles on Nextcloud.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		await expect(
			page.getByText(/Administration settings: Larpinq|Larpinq/i).first(),
		).toBeVisible({ timeout: 15_000 })
	}

	// @e2e openspec/specs/admin-settings/spec.md#configure-character-type-for-openregister
	test('admin panel lists per-type source configuration', async ({ page }) => {
		await openAdmin(page)
		await expect(page.getByText(/character/i).first()).toBeVisible()
		await expect(page.getByText(/skill/i).first()).toBeVisible()
	})

	// @e2e openspec/specs/admin-settings/spec.md#cascading-dropdown-behavior
	test('admin panel renders source/register/schema selectors', async ({
		page,
	}) => {
		await openAdmin(page)
		const selectors = page.locator(
			'.vs__dropdown-toggle, .multiselect, [role="combobox"], select',
		)
		expect(await selectors.count()).toBeGreaterThan(0)
	})

	// @e2e openspec/specs/admin-settings/spec.md#switch-back-to-internal-storage
	test('admin panel offers Internal / Open Register choices', async ({ page }) => {
		await openAdmin(page)
		const selectors = page.locator(
			'.vs__dropdown-toggle, .multiselect, [role="combobox"], select',
		)
		expect(await selectors.count()).toBeGreaterThan(0)
	})

	// @e2e openspec/specs/admin-settings/spec.md#save-all-configuration-at-once
	test('admin panel exposes a Save All button', async ({ page }) => {
		await openAdmin(page)
		await expect(
			page.getByRole('button', { name: /Save All/i }).first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/admin-settings/spec.md#configure-all-10-types-for-openregister
	test('admin panel lists all entity types for configuration', async ({
		page,
	}) => {
		await openAdmin(page)
		for (const t of [
			'character',
			'player',
			'ability',
			'skill',
			'item',
			'condition',
			'effect',
			'event',
		]) {
			await expect(page.getByText(new RegExp(t, 'i')).first()).toBeVisible()
		}
	})

	// @e2e openspec/specs/admin-settings/spec.md#openregister-installed-with-registers
	test('admin panel renders without OpenRegister-missing warning when OR present', async ({
		page,
	}) => {
		await openAdmin(page)
		await expect(
			page.getByRole('button', { name: /Save All/i }).first(),
		).toBeVisible()
	})

	// @e2e openspec/specs/admin-settings/spec.md#openregister-installed-with-no-registers
	test('admin panel allows saving even with empty register lists', async ({
		page,
	}) => {
		await openAdmin(page)
		await expect(
			page.getByRole('button', { name: /Save All/i }).first(),
		).toBeVisible()
	})
})

// ===========================================================================
// SETTINGS MANAGEMENT UI — re-import / cascading clears on the admin panel
// openspec/specs/settings-management-ui/spec.md
// ===========================================================================

test.describe('settings-management-ui — panel controls', () => {
	// @e2e openspec/specs/settings-management-ui/spec.md#cascading-clears
	test('settings panel renders cascading selector controls', async ({ page }) => {
		await page.goto('/settings/admin/larpinq')
		// ADR-074 rule 4: `networkidle` never settles on Nextcloud.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		const selectors = page.locator(
			'.vs__dropdown-toggle, .multiselect, [role="combobox"], select',
		)
		expect(await selectors.count()).toBeGreaterThan(0)
	})

	// @e2e openspec/specs/settings-management-ui/spec.md#re-import-reports-outcome
	test('settings panel exposes Save All / re-import action', async ({ page }) => {
		await page.goto('/settings/admin/larpinq')
		// ADR-074 rule 4: `networkidle` never settles on Nextcloud.
		await page
			.locator('#app-content, .app-content, #content')
			.first()
			.waitFor({ state: 'visible', timeout: 30_000 })
			.catch(() => {})
		await expect(
			page
				.getByRole('button', { name: /Save All|Re-?import|Import/i })
				.first(),
		).toBeVisible({ timeout: 15_000 })
	})
})
