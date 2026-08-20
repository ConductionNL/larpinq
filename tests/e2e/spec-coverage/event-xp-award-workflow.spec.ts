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
 * @spec openspec/specs/event-xp-awards/spec.md
 */

import { test, expect } from '@playwright/test'

const BASE = '/apps/larpingapp'

test.describe('event-xp-award-workflow', () => {
	test('XP Awards index page renders its own surface', async ({ page }) => {
		const pageErrors: string[] = []
		page.on('pageerror', (e) => pageErrors.push(e.message))

		// Never `networkidle` — unreachable on Nextcloud (ADR-074 rule 4).
		await page.goto(`${BASE}/#/xp-awards`, { waitUntil: 'domcontentloaded' })
		await expect(page.locator('.app-content')).toBeVisible({ timeout: 30_000 })

		// Assert a page-SPECIFIC affordance inside the content area. The old
		// `getByText(/XP Award/i)` was tautological — it matched the sidebar's
		// own "XP Awards" nav label and so passed on any route.
		// `.first()` goes on the COMBINED locator, not on each side. Written as
		// `A.first().or(B.first())` this asserted a UNION of two nodes, so the
		// moment the page rendered both the create button AND "No items found" —
		// which is precisely how a correct empty index looks — strict mode threw:
		//
		//   strict mode violation: … resolved to 2 elements
		//     1) <button data-testid="cn-cta-primary" …>
		//     2) <div class="empty-content__name">No items found</div>
		//
		// So the assertion was only green while exactly ONE of the two states was
		// observable, which made it a race on render order rather than a check of
		// the page. `A.or(B).first()` takes the first match of the union and holds
		// whether one or both are present.
		await expect(
			page
				.locator('.app-content button')
				.filter({ hasText: /Add XP Award|New XP Award/i })
				.or(
					page
						.locator('.app-content')
						.getByText(/No items found|Showing \d+ of \d+/i),
				)
				.first(),
			'XP Awards index must render its create action or an explicit empty state',
		).toBeVisible({ timeout: 15_000 })

		// No larpingapp JS pageerror while rendering the new page.
		expect(pageErrors).toEqual([])
	})
})
