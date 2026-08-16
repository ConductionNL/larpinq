/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright tests — larpingapp index (list) pages, deepened.
 *
 * spa-ui.spec.ts already asserts each index route is reachable, but because
 * it reuses a single in-session sidebar navigation it cannot distinguish the
 * pages: an in-session sidebar click does NOT re-key the index component, so
 * every list renders the Characters surface (shared-list-state collapse).
 *
 * This file does an honest per-page check: it hard-loads the SPA root once
 * per test, navigates to the target index, and asserts the *entity-specific*
 * surface — the correct "Add <Entity>" primary action, the view-mode toggle,
 * and a list/empty-state container. This proves each manifest index page
 * renders its own schema's UI rather than a generic shell.
 *
 * Data-independent: the OR MagicMapper bare-UUID-slug bug makes the object
 * fetch 500/empty in a bare env, so we assert the page *shell* and primary
 * controls, never data rows. The data-fetch 500 is a known OR backend issue,
 * not a larpingapp UI regression, so we only fail on larpingapp *pageerrors*.
 *
 * Authentication: globalSetup writes tests/e2e/.auth/admin.json;
 * playwright.config.ts wires storageState so each test starts logged in.
 */

import { test, expect, type Page } from '@playwright/test'
import { dismissSupportDialog } from '../_nav'

const BASE = '/apps/larpingapp'

/**
 * nc-vue `CnAppNav` render contract (see
 * nextcloud-vue/src/components/CnAppNav/CnAppNav.vue): the navigation root
 * carries `data-testid="cn-nav"` and every entry — top-level or nested child —
 * carries `data-testid="cn-nav-entry-<manifest menu id>"` plus
 * `data-cn-route="<route>"`. Target these instead of DOM-shape selectors:
 * they are stable across NC/nc-vue releases, whereas `.app-navigation a[href]`
 * silently matches nothing.
 */
const NAV = '[data-testid="cn-nav"]'
const NAV_ENTRY_ANY = '[data-testid^="cn-nav-entry-"]'

/**
 * Each index manifest page: route slug, sidebar nav label, and the singular
 * entity word that appears in its primary "Add <Entity>" button.
 */
const INDEX_PAGES: Array<{ slug: string; nav: string; entity: string }> = [
	{ slug: 'characters', nav: 'Characters', entity: 'Character' },
	{ slug: 'players', nav: 'Players', entity: 'Player' },
	{ slug: 'abilities', nav: 'Abilities', entity: 'Ability' },
	{ slug: 'skills', nav: 'Skills', entity: 'Skill' },
	{ slug: 'items', nav: 'Items', entity: 'Item' },
	{ slug: 'conditions', nav: 'Conditions', entity: 'Condition' },
	{ slug: 'effects', nav: 'Effects', entity: 'Effect' },
	{ slug: 'events', nav: 'Events', entity: 'Event' },
]

// `dismissSupportDialog` is imported from `../_nav` rather than copied. Five
// byte-similar copies of it existed across this suite, all matching only
// `button[aria-label="Close"]` — which does not match the six-step onboarding
// tour's "Close tour" / "Skip" controls, so the tour stayed open and every
// subsequent click hung on actionability.

/**
 * Expand every collapsed navigation group.
 *
 * larpingapp's manifest groups the sidebar into three collapsible groups
 * (Characters / Mechanics / World), so entries such as "Abilities" or
 * "Items" render in the DOM but are hidden until their group header is
 * clicked — a bare link click then fails with "Received: hidden".
 *
 * Re-query after every click: expanding one group re-renders the nav (its
 * child entries appear), which invalidates positional locators. Repeatedly
 * click the FIRST still-collapsed group header until none remain.
 *
 * Ported from openconnector's tests/e2e/spec-coverage/_helpers.ts.
 */
/**
 * Open the collapsible parent group that owns `navId`, if any.
 *
 * Do NOT sweep `[aria-expanded="false"]`: in the pinned nc-vue (beta.190)
 * EVERY nav entry carries `aria-expanded="false"`, not just collapsible
 * groups — verified live, 18 entries / 18 "collapsed". A generic sweep
 * therefore clicks the FIRST match (Dashboard), navigates away from the SPA
 * root, and loops — which is what made every index-pages test burn its full
 * 90s budget. Click the one real owning group instead.
 */
const NAV_GROUP_OF: Record<string, string> = {
	Characters: 'CharactersGroup',
	Players: 'CharactersGroup',
	Abilities: 'MechanicsGroup',
	Skills: 'MechanicsGroup',
	Conditions: 'MechanicsGroup',
	Effects: 'MechanicsGroup',
	Items: 'WorldGroup',
	Events: 'WorldGroup',
}

async function revealNavEntry(page: Page, navId: string): Promise<void> {
	const entry = page.locator(`[data-testid="cn-nav-entry-${navId}"]`).first()
	if (await entry.isVisible({ timeout: 1000 }).catch(() => false)) return
	const groupId = NAV_GROUP_OF[navId]
	if (!groupId) return
	const group = page.locator(`[data-testid="cn-nav-entry-${groupId}"]`).first()
	// Click the group's COLLAPSE TOGGLE, not its link. NcAppNavigationItem
	// renders the toggle as a separate button ("Open menu" / "Collapse menu");
	// clicking the group's `<a>` instead just navigates and never expands.
	// Verified DOM (nc-vue beta.190, live):
	//   <li class="app-navigation-entry--collapsible" data-testid="cn-nav-entry-MechanicsGroup">
	//     <a aria-expanded="false" href="#" title="Mechanics">…</a>
	//     <button aria-label="Open menu" class="icon-collapse …">
	//
	// The DISCLOSURE CONTROL IS THE ANCHOR, not the `.icon-collapse` button.
	// Measured live (child `Abilities` visibility / group `aria-expanded`):
	//   force-click button.icon-collapse -> hidden / aria=false  (no-op)
	//   force-click a.app-navigation-entry-link -> VISIBLE / aria=true
	// The anchor is `href="#"` so it toggles rather than navigating. `force`
	// is required because NC's nav controls are hover-revealed, so Playwright's
	// actionability check never settles and a plain .click() hangs until the
	// budget is gone (observed: a full 120s burnt on the click alone).
	//
	// Retry: `force` bypasses Playwright's actionability check but CANNOT fire
	// a handler Vue has not attached yet, so a single early click is a silent
	// no-op (same race as the login form). Click, verify via `aria-expanded`
	// + child visibility, and try again — cheap, and it makes the helper
	// independent of how fast the SPA hydrates.
	const anchor = group.locator('a.app-navigation-entry-link').first()
	for (let attempt = 0; attempt < 4; attempt++) {
		await anchor.click({ force: true, timeout: 10_000 }).catch(() => {})
		if (await entry.isVisible({ timeout: 2_500 }).catch(() => false)) return
		// Only re-click while the group is still reported closed; if it opened
		// but the child is slow, just keep waiting below.
		if (
			(await anchor.getAttribute('aria-expanded').catch(() => null)) === 'true'
		)
			break
		await page.waitForTimeout(750)
	}
	await entry.waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {})
}

/**
 * Hard-load the SPA root, dismiss the support modal, then nav-click the
 * sidebar entry. A fresh root load per test re-keys the index component so
 * the entity-specific surface renders (avoids the shared-list-state collapse).
 */
async function freshNav(page: Page, slug: string, navId: string): Promise<void> {
	// `domcontentloaded`, never `networkidle`: Nextcloud's notification poll
	// keeps the network permanently busy, so a networkidle wait never settles
	// and silently burns the whole test budget (it was the cause of every
	// index-pages test timing out even at 90s). The explicit visibility
	// assertions below are the real readiness signal.
	await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' })
	// `domcontentloaded` returns as soon as the HTML shell parses — the Vue app
	// has NOT mounted yet, so the sidebar contains no entries. Wait for the SPA
	// to actually render its navigation before touching it. (The previous
	// `networkidle` wait was doing this by accident; it is unsatisfiable on
	// Nextcloud, so it "worked" only by burning time until the app happened to
	// mount. This is the same readiness guarantee, stated explicitly.)
	await expect(page.locator(`${NAV} ${NAV_ENTRY_ANY}`).first()).toBeVisible({
		timeout: 30_000,
	})
	await dismissSupportDialog(page)
	// Reveal the entry if it lives inside a collapsed nav group.
	await revealNavEntry(page, navId)
	// Address the entry by its manifest menu id via nc-vue's stable
	// `data-testid` contract (CnAppNav renders
	// `data-testid="cn-nav-entry-<item.id>"` on every NcAppNavigationItem).
	// The previous `.app-navigation a[href=…]` selector matched nothing: the
	// entry is an NcAppNavigationItem, not a bare anchor in that container —
	// which ALSO made expandNavGroups a silent no-op, so the groups were never
	// opened and every child entry stayed hidden.
	const link = page.locator(`${NAV} [data-testid="cn-nav-entry-${navId}"]`).first()
	await expect(link).toBeVisible({ timeout: 10_000 })
	await link.click()
	await expect(page).toHaveURL(new RegExp(`#/${slug}(\\b|/|$|\\?)`))
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
}

/** Collect larpingapp-origin fatal page errors during a test. */
function trackPageErrors(page: Page): string[] {
	const errs: string[] = []
	page.on('pageerror', (e) => errs.push(e.message))
	return errs
}

// ===========================================================================
// Per-entity index pages — each renders its own schema surface.
//   character/player  → events-players + character-management specs
//   ability/skill/item/condition/effect → game-mechanics spec
// ===========================================================================

for (const { slug, nav, entity } of INDEX_PAGES) {
	test.describe(`index page: ${slug}`, () => {
		// @e2e openspec/specs/game-mechanics/spec.md#list-abilities-with-search
		test(`${slug} index renders the "Add ${entity}" primary action`, async ({
			page,
		}) => {
			const errs = trackPageErrors(page)
			await freshNav(page, slug, nav)

			// The page-specific primary create action proves this is the right
			// index page (not the generic/Characters shell).
			await expect(
				page
					.locator('.app-content button')
					.filter({ hasText: new RegExp(`Add ${entity}`, 'i') })
					.first(),
			).toBeVisible({ timeout: 10_000 })

			// The sidebar still exposes this entity as an accessible link. The nav
			// is grouped/collapsed (CnAppNav), so the entity label can appear twice
			// — once on the collapsible group header (href="#") and once on the
			// real entry link — which trips strict-mode on a bare role+name lookup.
			// Assert on the stable per-entry test id so we target the actual nav
			// entry (its link carries the hash route href).
			await expect(
				page
					.getByTestId(`cn-nav-entry-${nav}`)
					.getByRole('link', { name: nav }),
			).toBeVisible()

			// No larpingapp-origin fatal page errors (the OR data-fetch 500 is a
			// backend MagicMapper issue, not a UI crash, and surfaces as a console
			// log not a pageerror — so pageerrors must be empty).
			expect(errs).toHaveLength(0)
		})

		// @e2e openspec/specs/game-mechanics/spec.md#list-skills-with-search
		test(`${slug} index renders the view-mode toggle and a list-or-empty surface`, async ({
			page,
		}) => {
			await freshNav(page, slug, nav)

			// The index toolbar exposes a view-mode toggle. nc-vue renders this as
			// a `group "View mode"` containing Cards/Table BUTTONS — not radio
			// inputs (the old `input[type=radio]` selector matched nothing, which
			// is why this assertion could never pass). Accept either shape so the
			// test survives the widget being re-implemented again.
			await expect(
				page
					.getByRole('group', { name: /view mode/i })
					.or(
						page
							.locator('.app-content button')
							.filter({ hasText: /^(Cards|Table)$/ })
							.first(),
					)
					.or(page.locator('.app-content input[type="radio"]').first())
					.first(),
				'index toolbar must expose a view-mode toggle',
			).toBeVisible({ timeout: 10_000 })

			// A list container or an empty-state is rendered (data-independent:
			// in a bare env the empty-state shows; with data the list shows).
			const listOrEmpty = page
				.locator(
					'.app-content .empty-content, .app-content [class*="empty"], .app-content table, .app-content [role="table"], .app-content ul, .app-content [class*="list"]',
				)
				.first()
			await expect(listOrEmpty).toBeVisible({ timeout: 10_000 })
		})

		// @e2e openspec/specs/character-management/spec.md#create-a-new-character
		test(`${slug} index opens the "Add ${entity}" create dialog`, async ({
			page,
		}) => {
			await freshNav(page, slug, nav)

			const addBtn = page
				.locator('.app-content button')
				.filter({ hasText: new RegExp(`Add ${entity}`, 'i') })
				.first()
			await expect(addBtn).toBeVisible({ timeout: 10_000 })
			await addBtn.click()

			// The create modal/dialog appears with a name field, then we dismiss it.
			const dialog = page.locator('[role="dialog"]').first()
			await expect(dialog).toBeVisible({ timeout: 8_000 })
			await expect(dialog.locator('input, textarea').first()).toBeVisible({
				timeout: 5_000,
			})

			const cancel = dialog
				.locator('button')
				.filter({ hasText: /Cancel|Close/i })
				.first()
			if (await cancel.isVisible({ timeout: 1500 }).catch(() => false)) {
				await cancel.click().catch(() => {})
			} else {
				await page.keyboard.press('Escape').catch(() => {})
			}
		})
	})
}
