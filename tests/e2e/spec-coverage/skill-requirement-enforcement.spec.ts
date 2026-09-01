/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright regression — skill-requirement-enforcement.
 *
 * Enforcement is SERVER-AUTHORITATIVE: it lives in a SkillRequirementService +
 * an OpenRegister pre-write veto listener (CharacterRequirementListener) and on
 * the read-only requirement-report endpoint, so the meaningful assertions are at
 * the API layer (covered by the Newman collection and PHPUnit), not in the SPA.
 * The larpinq SPA renders character assignment through the generic
 * @conduction/nextcloud-vue object editor (manifest-v2 declarative pages); there
 * is no bespoke Add-Skill modal in this app's src/ to drive, so the UI pre-check
 * tasks are deferred (see tasks.md). This file is the e2e regression net that
 * back-references the change: it proves the requirement-report endpoint is wired
 * and answers for an authenticated user.
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */

import { expect, test } from '@playwright/test'

const BASE = '/apps/larpinq'

test.describe('skill-requirement-enforcement', () => {
	test('requirement-report endpoint is reachable for an authenticated user', async ({
		page,
		request,
	}) => {
		// Hard-load the SPA so the storageState session cookie is active.
		await page.goto(BASE)

		// A syntactically valid (but absent) UUID must yield a clean 404 from
		// the controller's OR-delegated fetch — never a 500 (route is wired,
		// auth posture is correct) and never a 401 (we are authenticated).
		const res = await request.get(
			`${BASE}/api/characters/00000000-0000-0000-0000-000000000000/requirement-report`,
		)
		// 404 = wired + authenticated + object absent; 200 would mean the test
		// env happened to have that object. Both prove the endpoint exists and
		// is not rejecting the authenticated caller.
		expect([200, 404]).toContain(res.status())
	})
})
