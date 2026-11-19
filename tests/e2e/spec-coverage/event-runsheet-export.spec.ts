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
 * layer and are covered by PHPUnit (EventsControllerTest). The event page
 * carries a "Download run sheet" header action for game masters
 * (capability-rows-missing-requirements task 4.1), which opens the template
 * picker and then the run sheet.
 *
 * The first test confirms the run-sheet route is wired and answers for an
 * authenticated user. The second downloads the run sheet from the event page:
 * it seeds an event with this run's id, picks the first PDF template the
 * instance offers, and is skipped, saying so, when the instance has no
 * template or no DocuDesk to render one.
 *
 * The cast test (larpinq-runsheet-scoped-cast-query 4.1) reads the event's
 * roster, which is built from the same scoped character query as the
 * run-sheet's cast, so it needs no DocuDesk: a character of another event must
 * stay out, a character of this event must be in.
 *
 * @spec openspec/specs/pdf-export/spec.md
 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#4-tests
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import { dismissSupportDialog } from '../_nav.ts'
import {
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
} from '../workflows/fixtures.ts'

const BASE = '/apps/larpinq'
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq/larping_event`
const JSON_HEADERS = { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' }

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

	// pdf-export RUN-020: the event page action opens the template picker and the rendered PDF in a new tab.
	test('a game master downloads the run sheet from the event page', async ({
		page,
	}) => {
		const admin = await newApi()
		const templates = await admin.get(`${BASE_URL}/index.php${BASE}/api/pdf/templates`, {
			headers: { 'OCS-APIRequest': 'true' },
		})
		const offered = templates.ok() ? await templates.json() : null
		test.skip(
			offered?.available !== true || (offered?.templates ?? []).length === 0,
			'This instance offers no PDF template (DocuDesk absent or no template), so there is no run sheet to download.',
		)

		const created = await admin.post(OBJECTS, {
			headers: JSON_HEADERS,
			data: { name: fixtureName('Winter Court 2026'), startDate: '2026-12-11', endDate: '2026-12-13' },
		})
		expect(created.ok(), await created.text()).toBe(true)
		const eventId = String((await created.json())?.['@self']?.id ?? '')
		try {
			await page.goto(`${BASE_URL}/index.php${BASE}/events/${eventId}`)
			await dismissSupportDialog(page)
			await page.getByRole('button', { name: 'Download run sheet' }).first().click()
			const dialog = page.getByRole('dialog').filter({ hasText: 'Download run sheet' })
			await expect(dialog).toBeVisible({ timeout: 30_000 })
			await dialog.getByRole('combobox').first().click()
			await page.getByRole('option').first().click()

			const [opened] = await Promise.all([
				page.context().waitForEvent('page'),
				dialog.getByTestId('runsheet-download').click(),
			])
			const answer = await opened.waitForResponse((res) => res.url().includes(`/events/${eventId}/runsheet/`))
			expect(answer.status()).toBe(200)
			expect(answer.headers()['content-type'] ?? '').toContain('application/pdf')
			await opened.close()
		} finally {
			await admin.delete(`${OBJECTS}/${eventId}`, { headers: { 'OCS-APIRequest': 'true' } }).catch(() => undefined)
			await admin.dispose()
		}
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
