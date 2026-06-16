/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright regression — event-runsheet-export.
 *
 * The GM run-sheet export is a backend endpoint
 * (EventsController::downloadRunsheet) that renders a per-event cast-list PDF
 * via DocuDesk, GM-group restricted, with graceful 424 when DocuDesk is absent.
 * The meaningful assertions (GM 200 vs non-GM 403 vs DocuDesk-absent 424, cast
 * derivation + sorting + unique-items rollup, filename fallback) are at the API
 * layer and are covered by PHPUnit (EventsControllerTest, 10 tests). The app's
 * UI is the declarative manifest renderer; the "Download run-sheet" action +
 * template-picker modal on the event detail page are deferred (no bespoke
 * event-detail component in this app's src/; nc-vue follow-up).
 *
 * This file is the gate-19 back-reference: it confirms the run-sheet route is
 * wired and answers for an authenticated user.
 *
 * @spec openspec/changes/event-runsheet-export/specs/pdf-export/spec.md
 */

import { test, expect } from '@playwright/test'

const BASE = '/apps/larpingapp'

test.describe('event-runsheet-export', () => {
	test('run-sheet endpoint is wired and authenticated', async ({ page, request }) => {
		await page.goto(BASE)

		// A valid-UUID but absent event with a valid-UUID template: depending on
		// the env's group membership and DocuDesk presence this is 403 (non-GM),
		// 424 (no DocuDesk), or 404 (event absent) — all prove the route exists
		// and is not a 401 (authenticated) nor a 500 (wired correctly).
		const res = await request.get(
			`${BASE}/events/00000000-0000-0000-0000-000000000000/runsheet/00000000-0000-0000-0000-000000000001`,
		)
		expect([403, 404, 424]).toContain(res.status())
	})
})
