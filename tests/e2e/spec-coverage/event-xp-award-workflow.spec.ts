/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright regression — event-xp-award-workflow.
 *
 * The xpAward record is a plain OpenRegister object: creation/edit/delete is GM
 * scoped via OR schema-level RBAC (no app controller), and the stat-engine
 * fifth stage that consumes awards is covered by PHPUnit
 * (CharacterServiceXpAwardTest). The user-facing surface this app ships is the
 * declarative manifest "XP Awards" index page (type:index, schema:xpAward),
 * rendered by @conduction/nextcloud-vue. The batch "Award XP" modal on the event
 * detail page is deferred (no bespoke event-detail component in this app's src/;
 * nc-vue follow-up). This file proves the XP Awards index page renders its own
 * schema surface.
 *
 * @spec openspec/changes/event-xp-award-workflow/specs/event-xp-awards/spec.md
 */

import { test, expect } from '@playwright/test'

const BASE = '/apps/larpingapp'

test.describe('event-xp-award-workflow', () => {
	test('XP Awards index page renders its own surface', async ({ page }) => {
		const pageErrors: string[] = []
		page.on('pageerror', (e) => pageErrors.push(e.message))

		await page.goto(`${BASE}/#/xp-awards`)
		// Wait for the SPA shell + an index primary action to render. The page
		// shell (not data rows) is what we assert — data fetch depends on a
		// seeded register, which a bare env does not have.
		await page.waitForLoadState('networkidle')

		// The index page should expose an "Add"/"XP Award" affordance somewhere
		// in the rendered surface (the manifest title is "XP Awards").
		const hasSurface = await page.getByText(/XP Award/i).first().isVisible().catch(() => false)
		expect(hasSurface).toBeTruthy()

		// No larpingapp JS pageerror while rendering the new page.
		expect(pageErrors).toEqual([])
	})
})
