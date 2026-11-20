/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: players who sign up themselves (players-self-signup).
 *
 * portaliq's writer is stood in for by the admin: it creates the player with
 * a portal subject reference, exactly as the portal's create action stamps it.
 * A portal character goes through larpinq's createCharacter receiver, the way
 * portaliq forwards it: a signed X-Portal-Subject assertion carrying the
 * account's ownerRef claim. What larpinq adds on top is what is proven here:
 * the profile is marked self-registered and awaiting review, a second profile
 * for the same portal account is refused, the portal character is played by
 * its owner, and a game master marks the player reviewed on the New players
 * page. The claim itself is portaliq's and needs a portal instance;
 * PortalProfileListenerTest proves the event larpinq sends.
 *
 * Minting an assertion needs portaliq's signing secret, which the suite never
 * reads from the instance: set PORTALIQ_JWT_SIGNING_SECRET to the instance's
 * `portaliq jwt_signing_secret` to run the positive case. The refusals run
 * without it.
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request as playwrightRequest, test } from '@playwright/test'
import { createHmac } from 'node:crypto'
import { BASE_URL } from '../_base-url.ts'
import {
	cleanupLedger,
	FixtureLedger,
	fixtureName,
	newApi,
	OR_BASE,
	resolveSchemaIds,
	RUN_ID,
} from './fixtures.ts'

const JSON_HEADERS = {
	Accept: 'application/json',
	'Content-Type': 'application/json',
	// Basic auth without a session passes Nextcloud's CSRF check only with this header.
	'OCS-APIRequest': 'true',
}
const subject = `portal-subject-${RUN_ID}`
const RECEIVER = `${BASE_URL}/index.php/apps/larpinq/api/portal/characters`
const SIGNING_SECRET = process.env.PORTALIQ_JWT_SIGNING_SECRET || ''

let admin: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Post an object by schema slug (the fixtures' SCHEMA_IDS has no id for
 * every slug, so this posts by slug).
 *
 * @param {string} slug The schema slug.
 * @param {object} body The object.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function post(slug: string, body: Record<string, unknown>) {
	const res = await admin.post(`${OR_BASE}/larpinq/${slug}`, {
		headers: JSON_HEADERS,
		data: body,
	})
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * Read an object by schema slug.
 *
 * @param {string} slug The schema slug.
 * @param {string} id The uuid.
 * @return {Promise<any>} The object.
 */
async function read(slug: string, id: string) {
	const res = await admin.get(`${OR_BASE}/larpinq/${slug}/${id}`, {
		headers: JSON_HEADERS,
	})
	expect(res.ok(), await res.text()).toBe(true)
	return res.json()
}

/**
 * Base64url without padding, as portaliq encodes.
 *
 * @param {string} text The text.
 * @return {string} The encoded text.
 */
function b64url(text: string): string {
	return Buffer.from(text).toString('base64url')
}

/**
 * Mint an X-Portal-Subject assertion the way portaliq's
 * PortalJwtService::createAssertion() does.
 *
 * @param {string} secret The signing secret.
 * @param {object} claims Claims added to the frozen set.
 * @return {string} The compact JWT.
 */
function assertion(secret: string, claims: Record<string, unknown>): string {
	const iat = Math.floor(Date.now() / 1000)
	const header = b64url(JSON.stringify({ alg: 'HS256', typ: 'JWT' }))
	const body = b64url(JSON.stringify({
		sub: subject,
		audience: 'player',
		organisation: '',
		trust: 'low',
		jti: `e2e-${RUN_ID}`,
		use: 'assertion',
		iat,
		exp: iat + 60,
		iss: 'portaliq',
		...claims,
	}))
	const signature = createHmac('sha256', secret).update(`${header}.${body}`).digest('base64url')
	return `${header}.${body}.${signature}`
}

/**
 * POST to the createCharacter receiver as portaliq does: no Nextcloud
 * session, only the assertion header.
 *
 * @param {string} header The X-Portal-Subject value, or '' for none.
 * @param {object} body The form answers.
 * @return {Promise<{status: number, body: any}>} The answer.
 */
async function forward(header: string, body: Record<string, unknown>) {
	// An empty storage state: the receiver must not lean on a browser session.
	const anon = await playwrightRequest.newContext({ storageState: { cookies: [], origins: [] } })
	try {
		const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json' }
		if (header !== '') {
			headers['X-Portal-Subject'] = header
		}
		const res = await anon.post(RECEIVER, { headers, data: body })
		return { status: res.status(), body: await res.json().catch(() => ({})) }
	} finally {
		await anon.dispose()
	}
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)
})

test.afterAll(async () => {
	await cleanupLedger(admin, ledger)
	await admin.dispose()
})

test.describe.serial('players who sign up themselves', () => {
	// @e2e openspec/specs/portal-contribution/spec.md#lotte-joins-the-campaign
	test('a portal profile is marked self-registered and awaiting review', async () => {
		const created = await post('player', {
			name: fixtureName('Lotte Bakker'),
			portalSubjectRef: subject,
		})
		expect(created.status, JSON.stringify(created.body)).toBeLessThan(300)
		ids.lotte = ledger.track(
			'player',
			String(created.body.id ?? created.body['@self']?.id),
		)

		const lotte = await read('player', ids.lotte)
		expect(lotte.portalSubjectRef).toBe(subject)
		expect(lotte.selfRegistered).toBe(true)
		expect(lotte.awaitingReview).toBe(true)
	})

	// @e2e openspec/specs/portal-contribution/spec.md#a-double-click
	test('a second profile for the same portal account is refused', async () => {
		const second = await post('player', {
			name: fixtureName('Lotte B.'),
			portalSubjectRef: subject,
		})
		expect(second.status).toBeGreaterThanOrEqual(400)
		expect(JSON.stringify(second.body)).toContain('already has a player profile')
	})

	// @e2e openspec/specs/portal-contribution/spec.md#lotte-creates-her-first-character
	test('a character Lotte creates in the portal is played by Lotte', async () => {
		test.skip(SIGNING_SECRET === '', 'PORTALIQ_JWT_SIGNING_SECRET is not set: cannot mint a portaliq assertion')

		// The body names another player and tries a staff field: both are ignored.
		const created = await forward(assertion(SIGNING_SECRET, { ownerRef: ids.lotte }), {
			name: fixtureName('Wren'),
			background: 'Raised by the river folk.',
			ocName: '99999999-9999-4999-8999-999999999999',
			approved: true,
		})
		expect(created.status, JSON.stringify(created.body)).toBe(201)
		ids.wren = ledger.track('character', String(created.body.id))

		const wren = await read('character', ids.wren)
		expect(wren.ocName).toBe(ids.lotte)
		expect(wren.ownerRef).toBe(ids.lotte)
		expect(wren.background).toBe('Raised by the river folk.')
		expect(wren.approved).not.toBe(true)
	})

	// @e2e openspec/specs/portal-contribution/spec.md#the-receiver-refuses-a-call-without-a-valid-assertion
	test('the portal character receiver refuses a call without a valid assertion', async () => {
		const missing = await forward('', { name: fixtureName('Nobody') })
		expect(missing.status).toBe(401)

		const forged = await forward(assertion('not-the-portaliq-secret-0000', { ownerRef: ids.lotte }), {
			name: fixtureName('Forged'),
		})
		expect(forged.status).toBe(401)
	})

	// @e2e openspec/specs/portal-contribution/spec.md#a-game-master-welcomes-lotte
	test('a game master marks Lotte reviewed on the New players page', async ({
		page,
	}) => {
		await page.goto(`${BASE_URL}/index.php/apps/larpinq/new-players`)
		const row = page.getByRole('row', {
			name: new RegExp(fixtureName('Lotte Bakker')),
		})
		await row.getByRole('checkbox').check()
		await page.getByRole('button', { name: 'Mark reviewed' }).click()
		await expect(row).toHaveCount(0)

		const lotte = await read('player', ids.lotte)
		expect(lotte.awaitingReview).toBe(false)
		expect(lotte.reviewedBy).toBe(process.env.NC_USER || 'admin')
		expect(Date.parse(lotte.reviewedAt)).not.toBeNaN()
	})
})
