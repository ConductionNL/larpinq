/*
 * SPDX-FileCopyrightText: 2026 Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright globalSetup — logs into Nextcloud once and persists the
 * resulting cookie jar / localStorage to `tests/e2e/.auth/admin.json`.
 * Every spec then reuses that storage state via the `use.storageState`
 * setting in playwright.config.ts, so individual tests start from an
 * authenticated session without each one paying the login cost.
 *
 * Why a real browser login (instead of POSTing to /login directly):
 * Nextcloud's login form ships a CSRF token (`requesttoken`) plus a
 * `oc_session_passphrase` cookie that must be set in the same browser
 * context. Driving the form via Playwright sidesteps having to
 * reverse-engineer the token-rotation contract, which has shifted
 * across NC 28 / 29 / 30.
 *
 * Pattern reference: ADR-030 (hydra/openspec/architecture/), mirrored
 * from launchpad's journeydoc setup (the longest-running journeydoc
 * adopter).
 */

import { chromium, expect, request, type FullConfig } from '@playwright/test'
import { execSync } from 'child_process'
import * as path from 'path'
import * as fs from 'fs'
import { resolveBaseURL } from './_base-url'

const AUTH_DIR = path.resolve(__dirname, '.auth')
const STORAGE_STATE = path.join(AUTH_DIR, 'admin.json')
const APP_ROOT = path.resolve(__dirname, '..', '..')
const BUNDLE_PATH = path.join(APP_ROOT, 'js', 'larpinq-main.js')

/**
 * Ensure the webpack bundle exists before specs hit `/apps/larpinq/`.
 *
 * The shared `ConductionNL/.github/quality.yml` Playwright job runs
 * `npm ci` + `npx playwright install` before the spec run, but never
 * `npm run build`. On a fresh CI VM the `js/larpinq-main.js`
 * artefact doesn't exist, so the rendered page loads a 404 script tag
 * and the Vue app never mounts — every selector wait then times out.
 *
 * Skipping the build entirely on CI would require a cross-repo PR to
 * `ConductionNL/.github` adding a `npm run build` step to the shared
 * workflow; doing it here keeps the fix self-contained.
 *
 * Note: locally, the app running in the dev container is usually
 * mounted from a separate checkout, so this build only helps CI / a
 * checkout that serves its own `js/`.
 */
function ensureBundleBuilt(): void {
	if (fs.existsSync(BUNDLE_PATH)) {
		return
	}
	// eslint-disable-next-line no-console
	console.log(
		`[playwright globalSetup] bundle missing at ${BUNDLE_PATH}; running 'npm run build' once…`,
	)
	execSync('npm run build', { cwd: APP_ROOT, stdio: 'inherit' })
}

/**
 * Wait until Nextcloud is actually serving requests.
 *
 * A shared dev instance is routinely mid-flight: another deploy flips it into
 * maintenance mode, an app version bump sets needsDbUpgrade (which makes NC
 * answer 503 on every route), or the database is still finishing crash
 * recovery. All three are transient, but a single-shot check turns them into
 * a hard suite failure. Poll until the instance reports installed, out of
 * maintenance and not awaiting a DB upgrade. Tune with E2E_HEALTH_TIMEOUT_MS
 * (default 10 min). Pattern mirrored from docudesk's globalSetup.
 */
async function ensureNextcloudReachable(baseURL: string): Promise<void> {
	const deadline =
		Date.now() + Number(process.env.E2E_HEALTH_TIMEOUT_MS || 600_000)
	const ctx = await request.newContext()
	let last = 'no response yet'
	try {
		while (Date.now() < deadline) {
			try {
				const res = await ctx.get(`${baseURL}/status.php`, {
					failOnStatusCode: false,
				})
				if (res.ok()) {
					const body = await res.json().catch(() => ({}))
					if (
						body
						&& body.installed === true
						&& body.maintenance === false
						&& body.needsDbUpgrade === false
					) {
						return
					}
					last = `status.php = ${JSON.stringify(body)}`
				} else {
					// 503 while an app upgrade is pending, 500 while the DB recovers.
					last = `status.php returned ${res.status()}`
				}
			} catch (err) {
				last = `request failed: ${(err as Error).message}`
			}
			// eslint-disable-next-line no-await-in-loop
			await new Promise((resolve) => setTimeout(resolve, 5_000))
		}
		throw new Error(
			`Nextcloud at ${baseURL} did not become healthy in time — last seen: ${last}. `
				+ 'Check for a concurrent deploy (occ upgrade), maintenance mode, or a recovering database.',
		)
	} finally {
		await ctx.dispose()
	}
}

export default async function globalSetup(config: FullConfig): Promise<void> {
	// No `?? 'http://localhost:8080'` fallback — see `_base-url.ts`. The config's
	// own baseURL comes from the same resolver, so the browser side and the API
	// side of the suite can never disagree about which instance they are on.
	const baseURL =
		(config.projects[0]?.use?.baseURL as string | undefined) ?? resolveBaseURL()
	const username = process.env.NC_ADMIN_USER ?? 'admin'
	const password = process.env.NC_ADMIN_PASS ?? 'admin'

	ensureBundleBuilt()
	await ensureNextcloudReachable(baseURL)
	fs.mkdirSync(AUTH_DIR, { recursive: true })

	const browser = await chromium.launch()
	const context = await browser.newContext({ baseURL })
	const page = await context.newPage()

	// The instance can flip back into maintenance between the health check and
	// this navigation; re-check health and retry rather than failing the suite.
	for (let attempt = 1; ; attempt++) {
		try {
			await page.goto('/index.php/login')
			break
		} catch (err) {
			if (attempt >= 3) {
				throw err
			}
			await ensureNextcloudReachable(baseURL)
		}
	}
	// Nextcloud's login form is client-rendered and its markup has drifted
	// between releases: on NC 34 the fields carry `id="user"` / `id="password"`
	// but no `name` attribute, so a `input[name="user"]` selector never resolves
	// and globalSetup times out. Match either shape (plus the autocomplete
	// attributes NC 34 sets), and wait for the field to be visible first.
	const userField = page
		.locator('input#user, input[name="user"], input[autocomplete="username"]')
		.first()
	const passwordField = page
		.locator(
			'input#password, input[name="password"], input[autocomplete="current-password"]',
		)
		.first()
	await userField.waitFor({ state: 'visible', timeout: 30_000 })
	// The login form is a Vue app: the markup exists before its submit handler
	// is attached, so clicking too early silently does nothing and the page
	// simply stays on /login.
	//
	// Do NOT wait for `networkidle` here — Nextcloud's notification/poll
	// traffic means that state is never reached, so the wait burns its whole
	// budget and (with no default timeout inside globalSetup) can hang the
	// suite before login is even attempted. Wait for the submit control to be
	// enabled instead: that is the real "Vue has mounted and wired up" signal.
	const submit = page.locator('button[type="submit"]').first()
	await submit.waitFor({ state: 'visible', timeout: 30_000 })
	await expect(submit).toBeEnabled({ timeout: 30_000 })
	await userField.fill(username)
	await passwordField.fill(password)
	// Bind the navigation wait BEFORE clicking, so a fast redirect cannot be
	// missed between the click returning and the wait starting.
	await Promise.all([
		page
			.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60_000 })
			.catch(() => {}),
		submit.click(),
	])
	// Nextcloud bounces to /apps/dashboard/ (or another default app) on
	// success. Wait for navigation away from the login page.
	await page.waitForURL((url) => /\/login(\?|$|\/)/.test(url.pathname) === false, {
		timeout: 60_000,
	})
	// Wait for the authenticated shell. NC 34 no longer guarantees the legacy
	// `#header` / `header.header` markup, so accept any banner-role header.
	await page.waitForSelector('#header, header.header, header, [role="banner"]', {
		timeout: 60_000,
	})
	// Catch wrong-credentials early so the failure message is clear.
	const currentUrl = page.url()
	if (/\/login(\?|$|\/)/.test(currentUrl)) {
		throw new Error(
			`Login appears to have failed — still on ${currentUrl}. `
				+ `Check NC_ADMIN_USER / NC_ADMIN_PASS (defaults admin/admin).`,
		)
	}

	// Persist the storage state so individual specs reuse the session.
	await context.storageState({ path: STORAGE_STATE })
	await browser.close()
}
