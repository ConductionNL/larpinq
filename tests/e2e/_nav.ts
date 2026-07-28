/**
 * Shared larpingapp sidebar-navigation helper.
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
import { expect, type Page } from '@playwright/test'

export const APP_BASE = '/apps/larpingapp'
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

/** Dismiss the first-load support modal if it is blocking clicks. */
export async function dismissSupportDialog(page: Page): Promise<void> {
	const close = page
		.locator('[role="dialog"] button[aria-label="Close"], .cn-support-dialog button[aria-label="Close"]')
		.first()
	if (await close.isVisible({ timeout: 1500 }).catch(() => false)) {
		await close.click().catch(() => {})
		await page.waitForTimeout(200)
	}
}

/** Load the SPA root and wait for the navigation to actually render. */
export async function openApp(page: Page): Promise<void> {
	// `domcontentloaded`, NEVER `networkidle` — NC's notification poll means
	// networkidle is unreachable, so the wait burns its whole budget
	// (ADR-074 rule 4). The nav-entry assertion is the real readiness gate:
	// `domcontentloaded` fires before Vue mounts, so the sidebar is empty.
	await page.goto(`${APP_BASE}/`, { waitUntil: 'domcontentloaded' })
	await expect(page.locator(`${NAV} ${NAV_ENTRY_ANY}`).first()).toBeVisible({ timeout: 30_000 })
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
		if ((await anchor.getAttribute('aria-expanded').catch(() => null)) === 'true') break
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
	await expect(link, `sidebar entry "${navId}" must be reachable`).toBeVisible({ timeout: 10_000 })
	await link.click()
	await expect(page).toHaveURL(new RegExp(`#/${slug}(\\b|/|$|\\?)`))
	await expect(page.locator('.app-content')).toBeVisible({ timeout: 10_000 })
}
