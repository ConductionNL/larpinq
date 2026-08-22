/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright regression — setting-management.
 *
 * The setting (LARP world/campaign) is now a first-class entity: the vestigial
 * key-value `setting` schema is repurposed to {name, description, status} v2,
 * the game entities gain an optional `setting` scoping UUID, and the manifest
 * ships a "Settings" index + detail page (schema:setting) rendered by
 * @conduction/nextcloud-vue. Schema shape is covered by PHPUnit
 * (SettingSchemaTest). The active-setting switcher + server-side list lens are
 * deferred (custom app-nav + useObjectStore plumbing; nc-vue follow-up). This
 * file proves the Settings index page renders its own schema surface.
 *
 * @spec openspec/specs/setting-management/spec.md
 */

import { test, expect } from '@playwright/test'

const BASE = '/apps/larpinq'

test.describe('setting-management', () => {
	test('Settings (worlds) index page renders its own surface', async ({
		page,
	}) => {
		const pageErrors: string[] = []
		page.on('pageerror', (e) => pageErrors.push(e.message))

		// Never `networkidle` — Nextcloud's notification poll means that state
		// is never reached, so the wait always burns its full budget (ADR-074
		// rule 4). Wait for the rendered page surface instead.
		await page.goto(`${BASE}/#/settings`, { waitUntil: 'domcontentloaded' })
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 30_000 })

		// Assert a page-SPECIFIC affordance inside the content area. The old
		// `getByText(/Setting/i)` was tautological: it matched the sidebar's
		// own "Settings" nav label, so it passed on every route including the
		// dashboard fallback.
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Add Setting|New Setting/i })
				.first()
				.or(
					page
						.locator('.app-content')
						.getByText(/No items found|Showing \d+ of \d+/i)
						.first(),
				),
			'Settings (worlds) index must render its create action or an explicit empty state',
		).toBeVisible({ timeout: 15_000 })

		expect(pageErrors).toEqual([])
	})
})
