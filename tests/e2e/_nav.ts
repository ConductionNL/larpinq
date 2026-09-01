import type { Page } from '@playwright/test'

/**
 * Shared larpinq sidebar-navigation helper.
 *
 * SPDX-License-Identifier: EUPL-1.2
 *
 * One source of truth for reaching an index page through the real sidebar.
 * Three spec files previously carried byte-identical copies of a helper that
 * could never work; fixing them independently is how this drifts back.
 *
 * Everything below is derived from the LIVE deployed bundle (nc-vue
 * beta.190), not from library source — the pinned version and the current
 * nextcloud-vue checkout disagree, and Playwright's accessibility snapshot
 * omits `data-testid` entirely, so both of those are misleading oracles.
 *
 * Verified DOM:
 *   <nav data-testid="cn-nav">
 *     <li class="app-navigation-entry-wrapper app-navigation-entry--collapsible"
 *         data-testid="cn-nav-entry-MechanicsGroup">
 *       <a aria-expanded="false" href="#" class="app-navigation-entry-link">…</a>
 *       <button aria-label="Open menu" class="icon-collapse …">
 *
 * Four traps this helper exists to encode:
 *  1. `.app-navigation a[href=…]` matches NOTHING — entries are
 *     NcAppNavigationItem `<li>`s, addressed by `data-testid`.
 *  2. A blanket `[aria-expanded="false"]` sweep is WRONG: every entry carries
 *     it (18/18 measured), so the sweep clicks Dashboard and navigates away.
 *  3. The `.icon-collapse` "Open menu" button is a NO-OP for disclosure
 *     (measured: aria stays false). The `<a href="#">` is the real control.
 *  4. `force: true` is required (NC nav controls are hover-revealed, so a
 *     plain click hangs on actionability) AND the click must be retried,
 *     because force cannot fire a handler Vue has not attached yet.
 */
import { expect } from '@playwright/test'

export const APP_BASE = '/apps/larpinq'
export const NAV = '[data-testid="cn-nav"]'
const NAV_ENTRY_ANY = '[data-testid^="cn-nav-entry-"]'

/** Manifest menu id that owns each entry (collapsible groups only). */
const NAV_GROUP_OF: Record<string, string> = {
	Characters: 'CharactersGroup',
	Players: 'CharactersGroup',
	Abilities: 'MechanicsGroup',
	Skills: 'MechanicsGroup',
	SkillTree: 'MechanicsGroup',
	Conditions: 'MechanicsGroup',
	Effects: 'MechanicsGroup',
	Items: 'WorldGroup',
	Events: 'WorldGroup',
	XpAwards: 'WorldGroup',
}

/** Route slug -> manifest menu id. */
const NAV_ID_OF: Record<string, string> = {
	characters: 'Characters',
	players: 'Players',
	abilities: 'Abilities',
	skills: 'Skills',
	'skill-tree': 'SkillTree',
	conditions: 'Conditions',
	effects: 'Effects',
	items: 'Items',
	events: 'Events',
	'xp-awards': 'XpAwards',
	settings: 'Settings',
	'game-settings': 'GameSettings',
}

export function navIdForSlug(slug: string): string {
	return NAV_ID_OF[slug] ?? slug.charAt(0).toUpperCase() + slug.slice(1)
}

/**
 * Dismiss every blocking first-load modal.
 *
 * READ THIS BEFORE NARROWING THE SELECTOR AGAIN.
 *
 * This used to match only `button[aria-label="Close"]`. The app's first load
 * opens a SIX-STEP onboarding tour ("Welcome to LARPing"), whose controls are
 * labelled **"Close tour"** and **"Skip"** — neither matches, so the tour stayed
 * open over the whole viewport. Measured:
 * `document.elementFromPoint(innerWidth/2, innerHeight/2)` returned the tour's
 * `<video>`, and two `[role="dialog"]` nodes were live with two modal masks.
 *
 * That is a nasty failure shape, because the page underneath is perfectly
 * rendered: `toBeVisible()` still passes on plenty of elements, but every
 * `locator.click()` hangs on actionability until the test times out. It
 * accounted for the bulk of a 58-failure run and reads exactly like a
 * rendering regression.
 *
 * So: close ANY visible dialog, by any of the labels the tour and the support
 * modal actually use, and keep going until none is left (the tour and the
 * step-controls modal are two separate dialogs). Escape is the fallback.
 *
 * @param {Page} page The page to clear.
 * @return {Promise<void>}
 */
export async function dismissSupportDialog(page: Page): Promise<void> {
	const DISMISS = /^(close tour|skip|close|dismiss|got it|finish|no thanks)$/i
	for (let pass = 0; pass < 4; pass++) {
		const dialog = page.locator('[role="dialog"]:visible').first()
		if (
			!(await dialog
				.isVisible({ timeout: pass === 0 ? 2500 : 800 })
				.catch(() => false))
		) {
			return
		}
		const buttons = await dialog
			.locator('button')
			.all()
			.catch(() => [])
		let clicked = false
		for (const btn of buttons) {
			const label = (
				(await btn.getAttribute('aria-label').catch(() => ''))
				|| (await btn.innerText().catch(() => ''))
				|| ''
			).trim()
			if (DISMISS.test(label)) {
				await btn.click({ timeout: 3000 }).catch(() => {})
				clicked = true
				break
			}
		}
		if (!clicked) {
			await page.keyboard.press('Escape').catch(() => {})
		}
		await page.waitForTimeout(350)
	}
	// Verify the POSTCONDITION, don't just assume the clicks worked.
	//
	// Every `.click()` above is `.catch(() => {})`, so this function used to
	// return "successfully" while a dialog was still on screen. The onboarding
	// tour's dim layer (`.cn-walkthrough__dim--full`) covers the whole viewport
	// and intercepts pointer events, so the caller's very next click then hangs
	// on actionability for the full 60 s test timeout and is reported against
	// whatever element it was aiming at — a sidebar link that the log shows was
	// "visible, enabled and stable" the entire time. Measured on E2E job
	// 91942310154.
	//
	// This is a diagnosis, not a wait: `ci-seed.sh` marks the walkthrough seen
	// so it should never mount at all, and this line makes the case where that
	// failed name itself instead of surfacing 60 s later somewhere unrelated.
	// Scoped deliberately to the ONE overlay measured to do this, rather than to
	// every `[role="dialog"][aria-modal="true"]` — a broader net would start
	// failing on modals that are merely open and not blocking.
	const blocker = page.locator('.cn-walkthrough__dim').first()
	if (await blocker.isVisible({ timeout: 500 }).catch(() => false)) {
		throw new Error(
			'The first-visit walkthrough dim layer (.cn-walkthrough__dim) is still on screen after '
				+ 'dismissSupportDialog(). It covers the viewport and intercepts pointer events, so the next '
				+ 'click would hang on actionability for the full 60 s test timeout and be reported against an '
				+ "unrelated element. ci-seed.sh's walkthrough-suppression step did not take effect.",
		)
	}
}

/** Load the SPA root and wait for the navigation to actually render. */
export async function openApp(page: Page): Promise<void> {
	// `domcontentloaded`, NEVER `networkidle` — NC's notification poll means
	// networkidle is unreachable, so the wait burns its whole budget
	// (ADR-074 rule 4). The nav-entry assertion is the real readiness gate:
	// `domcontentloaded` fires before Vue mounts, so the sidebar is empty.
	await page.goto(`${APP_BASE}/`, { waitUntil: 'domcontentloaded' })
	await expect(page.locator(`${NAV} ${NAV_ENTRY_ANY}`).first()).toBeVisible({
		timeout: 30_000,
	})
	await dismissSupportDialog(page)
}

/** Expand the collapsible group owning `navId`, if the entry is hidden. */
export async function revealNavEntry(page: Page, navId: string): Promise<void> {
	const entry = page.locator(`[data-testid="cn-nav-entry-${navId}"]`).first()
	if (await entry.isVisible({ timeout: 1000 }).catch(() => false)) return
	const groupId = NAV_GROUP_OF[navId]
	if (!groupId) return
	const anchor = page
		.locator(`[data-testid="cn-nav-entry-${groupId}"]`)
		.first()
		.locator('a.app-navigation-entry-link')
		.first()
	for (let attempt = 0; attempt < 4; attempt++) {
		await anchor.click({ force: true, timeout: 10_000 }).catch(() => {})
		if (await entry.isVisible({ timeout: 2_500 }).catch(() => false)) return
		if (
			(await anchor.getAttribute('aria-expanded').catch(() => null)) === 'true'
		)
			break
		await page.waitForTimeout(750)
	}
	await entry.waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {})
}

/** Load the SPA root, then reach `slug`'s index page via the real sidebar. */
export async function navTo(page: Page, slug: string): Promise<void> {
	await openApp(page)
	const navId = navIdForSlug(slug)
	await revealNavEntry(page, navId)
	const link = page.locator(`${NAV} [data-testid="cn-nav-entry-${navId}"]`).first()
	await expect(link, `sidebar entry "${navId}" must be reachable`).toBeVisible({
		timeout: 10_000,
	})
	await link.click()
	// larpinq moved off hash routing (#651), so the URL is /apps/larpinq/<slug>
	// with no "#". The old pattern waited 15s on a hash that is never produced.
	await expect(page).toHaveURL(new RegExp(`/${slug}(\\b|/|$|\\?)`))
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
}
