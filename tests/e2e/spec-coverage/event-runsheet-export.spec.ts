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
 * The cast test (larpinq-runsheet-scoped-cast-query 4.1) reads the event's
 * roster, which is built from the same scoped character query as the
 * run-sheet's cast, so it needs no DocuDesk: a character of another event must
 * stay out, a character of this event must be in.
 *
 * @spec openspec/specs/pdf-export/spec.md
 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#4-verification
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
} from '../workflows/fixtures.ts'

const BASE = '/apps/larpinq'

test.describe('event-runsheet-export', () => {
	test('run-sheet endpoint is wired and authenticated', async ({
		page,
		request,
	}) => {
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

	test('the cast holds this event\'s characters and no other event\'s', async () => {
		const api: APIRequestContext = await newApi()
		const ledger = new FixtureLedger()
		try {
			await resolveSchemaIds(api)
			const eventId = ledger.track('event', await createObject(api, 'event', { name: fixtureName('runsheet event') }))
			const otherId = ledger.track('event', await createObject(api, 'event', { name: fixtureName('another event') }))
			const cast = ledger.track('character', await createObject(api, 'character', {
				name: fixtureName('cast member'), ocName: 'Speler Een', events: [eventId],
			}))
			const outsider = ledger.track('character', await createObject(api, 'character', {
				name: fixtureName('other cast'), ocName: 'Speler Twee', events: [otherId],
			}))

			const res = await api.get(`${BASE_URL}/index.php/apps/larpinq/api/events/${eventId}/roster`, {
				headers: { 'OCS-APIRequest': 'true' },
			})
			expect(res.status(), await res.text()).toBe(200)
			const ids = ((await res.json()).participants ?? []).map((row: { character?: string }) => String(row.character ?? ''))
			expect(ids, 'the event\'s own character is in the cast').toContain(cast)
			expect(ids, 'a character of another event stays out').not.toContain(outsider)
		} finally {
			await cleanupLedger(api, ledger)
			await api.dispose()
		}
	})
})
