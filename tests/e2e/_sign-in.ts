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
	return page
}
