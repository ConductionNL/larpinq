/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A browser page signed in as one user through the Nextcloud login form.
 *
 * Why not `httpCredentials` on the browser context: Chromium sends HTTP basic
 * credentials on a page navigation only after a 401 challenge, and Nextcloud
 * answers an unauthenticated page with a 303 to /login instead. So a context
 * built with `httpCredentials: { send: 'always' }` lands every `page.goto` on
 * the login form (seen live on Nextcloud 34 with Playwright 1.62), and a test
 * that then looks for a tab or a testid times out with a misleading message.
 * API request contexts (`request.newContext`) do send the header up front and
 * keep using `httpCredentials`.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import type { Browser, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { BASE_URL } from './_base-url.ts'

const LOGIN_PATH = /\/login(\?|$|\/)/

/** The per-user preference src/manifest.json names as `walkthrough.completionConfigKey`. */
const WALKTHROUGH_KEY = 'walkthrough_completed_version'

/**
 * Open a new browser context with an empty storage state and sign it in.
 *
 * @param browser The browser.
 * @param username The account name.
 * @param password The account password.
 * @return A page of the signed-in context, on the page Nextcloud lands on.
 */
export async function signedInPage(browser: Browser, username: string, password: string): Promise<Page> {
	const context = await browser.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
	})
	await context.addInitScript(() => {
		try {
			window.localStorage.setItem('cn-support-dialog-shown:larpinq', '1')
		} catch {
			// No storage: the note then follows the server preference set below.
		}
	})
	const page = await context.newPage()
	await page.goto(`${BASE_URL}/index.php/login`)
	const user = page.locator('input#user, input[name="user"], input[autocomplete="username"]').first()
	const pass = page
		.locator('input#password, input[name="password"], input[autocomplete="current-password"]')
		.first()
	await user.waitFor({ state: 'visible', timeout: 30_000 })
	const submit = page.locator('button[type="submit"]').first()
	await expect(submit).toBeEnabled({ timeout: 30_000 })
	await user.fill(username)
	await pass.fill(password)
	await submit.click()
	await page.waitForURL((url) => LOGIN_PATH.test(url.pathname) === false, { timeout: 60_000 })
	await markFirstVisitSeen(page)
	return page
}

/**
 * Mark the first-visit overlays as seen for a user made for the run.
 *
 * A new account gets the "Support Larpinq" note (CnSupportDialog) and the
 * walkthrough on its first page, both modal over the page, so a test's clicks
 * land on them and time out. ci-seed.sh turns both off for the admin only. The
 * note reads its per-browser flag `cn-support-dialog-shown:<app>` first (set by
 * the init script above), then the per-user preference `support-dialog-seen`;
 * the walkthrough reads the preference named by the manifest's
 * `walkthrough.completionConfigKey`. No test here asserts either overlay.
 *
 * @param page The signed-in page.
 * @return Resolves when both preferences are written.
 */
async function markFirstVisitSeen(page: Page): Promise<void> {
	const headers = { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json', Accept: 'application/json' }
	const preferences: Record<string, string> = { [WALKTHROUGH_KEY]: '999.0.0', 'support-dialog-seen': '1' }
	for (const [key, value] of Object.entries(preferences)) {
		await page.request.put(`${BASE_URL}/index.php/apps/larpinq/api/preferences/${key}`, { headers, data: { value } })
	}
}
