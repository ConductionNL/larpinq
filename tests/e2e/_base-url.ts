/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * THE single source of truth for the Nextcloud instance the e2e suite targets.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * Every route to `http://localhost:8080` used to be spelled out separately —
 * `playwright.config.ts`, `global-setup.ts`, `workflows/fixtures.ts` and
 * `spec-coverage/detail-forms-admin.spec.ts` each computed their own default.
 * Two failure modes follow from that, and both have been observed on sibling
 * apps in this fleet:
 *
 *   1. `:8080` is the SHARED dev container. It bind-mounts real host checkouts,
 *      so a suite that writes fixtures there creates registers, schemas and
 *      objects in other people's environment — and a suite that reads from
 *      there reports on code that is not the code under test. One app's login
 *      specs fired failed logins into the shared instance until it
 *      brute-force-locked.
 *   2. When the resolution rule differs between files, one spec can create a
 *      fixture on the isolated instance and then open it on the shared one, in
 *      the same test, with no error.
 *
 * So: there is NO `?? 'http://localhost:8080'` fallback anywhere. An unset
 * base URL is a loud configuration error, not a silent redirect onto somebody
 * else's Nextcloud.
 *
 * `PLAYWRIGHT_BASE_URL` is the authoritative variable; `NEXTCLOUD_URL` and
 * `NC_BASE_URL` are accepted for compatibility with the shared CI workflow and
 * the older spec spelling.
 */

/** Environment variables consulted, in precedence order. */
const BASE_URL_VARS = ['PLAYWRIGHT_BASE_URL', 'NEXTCLOUD_URL', 'NC_BASE_URL'] as const

/**
 * Resolve the target Nextcloud base URL, or throw.
 *
 * @return {string} The base URL with any trailing slash removed.
 * @throws {Error} When none of the accepted variables is set.
 */
export function resolveBaseURL(): string {
	for (const name of BASE_URL_VARS) {
		const value = process.env[name]
		if (value && value.trim().length > 0) {
			return value.trim().replace(/\/+$/, '')
		}
	}
	throw new Error(
		'No Nextcloud base URL configured for the e2e suite. Set PLAYWRIGHT_BASE_URL '
		+ `(or ${BASE_URL_VARS.slice(1).join(' / ')}) to the isolated instance, e.g. `
		+ 'PLAYWRIGHT_BASE_URL=http://localhost:8094. There is deliberately no '
		+ 'localhost:8080 default — that is the SHARED dev container.',
	)
}

/** The resolved base URL for this run. */
export const BASE_URL = resolveBaseURL()

/** OpenRegister object API root on the target instance. */
export const OR_OBJECTS_API = `${BASE_URL}/index.php/apps/openregister/api/objects`

/** LarpingApp settings API on the target instance. */
export const LARPINGAPP_SETTINGS_API = `${BASE_URL}/index.php/apps/larpingapp/api/settings`
