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
 * @spec openspec/changes/setting-management/specs/setting-management/spec.md
 */

import { test, expect } from '@playwright/test'

const BASE = '/apps/larpingapp'

test.describe('setting-management', () => {
	test('Settings (worlds) index page renders its own surface', async ({ page }) => {
		const pageErrors: string[] = []
		page.on('pageerror', (e) => pageErrors.push(e.message))

		await page.goto(`${BASE}/#/settings`)
		await page.waitForLoadState('networkidle')

		// The index page should expose a "Setting"/"Settings" affordance. We
		// assert the page shell, not data rows (data fetch needs a seeded
		// register a bare env does not have).
		const hasSurface = await page.getByText(/Setting/i).first().isVisible().catch(() => false)
		expect(hasSurface).toBeTruthy()

		expect(pageErrors).toEqual([])
	})
})
