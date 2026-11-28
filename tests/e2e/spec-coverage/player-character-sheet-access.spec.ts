/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec-coverage Playwright regression — player-character-sheet-access.
 *
 * Who may download a character sheet (CharactersController::downloadPdf):
 * an admin, a member of `gamemasters`, or the player whose uid is the
 * character's `ownerUid`. Everyone else gets 403 `{"error": "Access denied"}`,
 * never a 404, so a character id cannot be probed.
 *
 * The template id and DocuDesk are checked before the character is read, so
 * on an instance without DocuDesk every authenticated caller gets 424 and the
 * access matrix cannot be observed. Those tests skip with that reason; the
 * matrix itself is covered by CharactersControllerTest. What holds on every
 * instance runs everywhere: no caller can tell an existing character from a
 * missing one, and an anonymous request is refused.
 *
 * Callers are fresh accounts, each with its own request context and an empty
 * storage state, so the admin session from global-setup never leaks in.
 *
 * @e2e exclude Owner render context excludes GM-private notes — the render
 *   context handed to DocuDesk is not observable in the returned PDF bytes;
 *   covered by CharactersControllerTest (owner context has no slNotesPrivate).
 * @e2e exclude GM render context keeps GM-private notes — same reason;
 *   covered by CharactersControllerTest.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../_base-url.ts'
import {
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	resolveSchemaIds,
	RUN_ID,
} from '../workflows/fixtures.ts'

const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const EMPTY_STATE = { cookies: [], origins: [] }
const MISSING_ID = '00000000-0000-0000-0000-00000000dead'
const PLACEHOLDER_TEMPLATE = '00000000-0000-0000-0000-000000000001'

const people = {
	alice: '',
	bob: '',
	carol: 'gamemasters',
} as const
type Who = keyof typeof people
const uid = (who: Who): string => `sheet-${who}-${RUN_ID}`
const password = (who: Who): string => `${uid(who)}-pw!9`

let admin: APIRequestContext
const as: Partial<Record<Who, APIRequestContext>> = {}
const ledger = new FixtureLedger()
let ownedId = ''
let unownedId = ''
let templateId = PLACEHOLDER_TEMPLATE
let canRender = false

/**
 * Ask for a character sheet as one caller.
 *
 * @param caller The caller's request context.
 * @param id The character id.
 * @return The response.
 */
function download(caller: APIRequestContext, id: string): Promise<APIResponse> {
	return caller.get(`${BASE_URL}/index.php/apps/larpinq/characters/${id}/download/${templateId}`, {
		maxRedirects: 0,
		failOnStatusCode: false,
	})
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)

	await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, {
		headers: OCS,
		form: { groupid: 'gamemasters' },
	})
	for (const [who, group] of Object.entries(people) as Array<[Who, string]>) {
		const form: Record<string, string> = { userid: uid(who), password: password(who) }
		if (group !== '') {
			form['groups[]'] = group
		}
		const made = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, { headers: OCS, form })
		expect(made.ok(), await made.text()).toBe(true)
		as[who] = await request.newContext({
			storageState: EMPTY_STATE,
			httpCredentials: { username: uid(who), password: password(who) },
		})
	}

	ownedId = ledger.track(
		'character',
		await createObject(admin, 'character', {
			name: fixtureName('Sir Lancelot'),
			ownerUid: uid('alice'),
			slNotesPublic: 'Known at court',
			slNotesPrivate: 'Secretly in love with the queen',
		}),
	)
	unownedId = ledger.track(
		'character',
		await createObject(admin, 'character', { name: fixtureName('Unclaimed knight') }),
	)

	const templates = await admin.get(`${BASE_URL}/index.php/apps/larpinq/api/pdf/templates`, {
		headers: OCS,
	})
	if (templates.ok()) {
		const state = (await templates.json()) as { available?: boolean, templates?: Array<{ id: string }> }
		if (state.available === true && (state.templates ?? []).length > 0) {
			templateId = state.templates![0].id
			canRender = true
		}
	}
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	for (const who of Object.keys(people) as Who[]) {
		await as[who]?.dispose()
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid(who)}`, { headers: OCS })
	}
	await admin.dispose()
})

const NEEDS_DOCUDESK = 'needs DocuDesk with a larpinq template: without it every caller gets 424 before the access check'

test.describe('player-character-sheet-access', () => {
	// @e2e player-character-sheet-access::owner-downloads-their-own-character-sheet
	// @e2e player-character-sheet-access::non-admin-owner-download-succeeds
	// @e2e player-character-sheet-access::csrf-not-required-for-download
	test('the owner downloads their own sheet, without a CSRF token', async () => {
		test.skip(!canRender, NEEDS_DOCUDESK)
		const res = await download(as.alice!, ownedId)
		expect(res.status(), await res.text()).toBe(200)
		expect(res.headers()['content-type']).toContain('application/pdf')
	})

	// @e2e player-character-sheet-access::non-owner-player-is-denied-without-id-disclosure
	test('another player is refused with 403, not 404', async () => {
		test.skip(!canRender, NEEDS_DOCUDESK)
		const res = await download(as.bob!, ownedId)
		expect(res.status()).toBe(403)
		expect(await res.json()).toEqual({ error: 'Access denied' })
	})

	// @e2e player-character-sheet-access::gm-group-member-downloads-any-character-sheet
	test('a game master who is not an admin downloads any sheet', async () => {
		test.skip(!canRender, NEEDS_DOCUDESK)
		for (const id of [ownedId, unownedId]) {
			const res = await download(as.carol!, id)
			expect(res.status(), await res.text()).toBe(200)
		}
	})

	// @e2e player-character-sheet-access::character-without-owneruid-grants-no-player-access
	test('a character without an owner is refused to every player', async () => {
		test.skip(!canRender, NEEDS_DOCUDESK)
		for (const who of ['alice', 'bob'] as Who[]) {
			const res = await download(as[who]!, unownedId)
			expect(res.status(), `${who} on an unowned character`).toBe(403)
		}
	})

	// @e2e player-character-sheet-access::non-owner-player-is-denied-without-id-disclosure
	test('a player cannot tell an existing character from a missing one', async () => {
		const existing = await download(as.bob!, ownedId)
		const missing = await download(as.bob!, MISSING_ID)
		expect(existing.status()).toBe(missing.status())
		expect(await existing.text()).toBe(await missing.text())
		expect([403, 424]).toContain(existing.status())
	})

	// @e2e player-character-sheet-access::unauthenticated-user-denied
	test('an anonymous request is refused', async () => {
		const anonymous = await request.newContext({ storageState: EMPTY_STATE })
		try {
			const res = await download(anonymous, ownedId)
			if (res.status() >= 300 && res.status() < 400) {
				expect(res.headers().location ?? '').toContain('/login')
			} else {
				expect(res.status()).toBe(401)
			}
		} finally {
			await anonymous.dispose()
		}
	})
})
