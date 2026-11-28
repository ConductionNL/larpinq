/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Another player reads none of Anna's care fields: not on her player, not in
 * the list, not through a search and not in a CSV export, while Anna reads
 * her own (REQ-PCE-001).
 *
 * Anna and Pieter are real larpers made for the run, each with their own
 * request context and no stored session.
 *
 * @spec openspec/specs/player-care-details/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL, OR_OBJECTS_API } from './_base-url.ts'

const RUN = `care${Date.now().toString(36)}`
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const ADMIN = { username: process.env.NC_ADMIN_USER ?? 'admin', password: process.env.NC_ADMIN_PASS ?? 'admin' }
const PLAYERS = `${OR_OBJECTS_API}/larpinq/player`
const CARE = { allergies: `hazelnuts-${RUN}`, emergencyContactPhone: '+31 6 00000000' }

const anna = { username: `${RUN}-anna`, password: `Anna-${RUN}-pw!1` }
const pieter = { username: `${RUN}-pieter`, password: `Pieter-${RUN}-pw!1` }

/**
 * A request context that signs in as one user and carries no stored session.
 *
 * @param {{username: string, password: string}} user The account.
 * @return {Promise<APIRequestContext>}
 */
function contextFor(user: { username: string, password: string }): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: user.username, password: user.password, send: 'always' },
	})
}

let admin: APIRequestContext
let asAnna: APIRequestContext
let asPieter: APIRequestContext
let playerId = ''

test.beforeAll(async () => {
	admin = await contextFor(ADMIN)
	for (const user of [anna, pieter]) {
		const res = await admin.post('/ocs/v2.php/cloud/users', {
			headers: OCS,
			form: { userid: user.username, password: user.password, 'groups[]': 'larpers' },
		})
		expect(res.status(), `create ${user.username}`).toBe(200)
	}
	asAnna = await contextFor(anna)
	asPieter = await contextFor(pieter)

	const created = await admin.post(PLAYERS, {
		headers: JSON_HEADERS,
		data: { name: `${RUN} Anna de Vries`, userUid: anna.username, ...CARE },
	})
	expect(created.status(), await created.text()).toBe(201)
	playerId = (await created.json()).id
})

test.afterAll(async () => {
	if (playerId !== '') {
		await admin.delete(`${PLAYERS}/${playerId}`, { headers: OCS })
	}
	for (const user of [anna, pieter]) {
		await admin.delete(`/ocs/v2.php/cloud/users/${user.username}`, { headers: OCS })
	}
	await Promise.all([admin, asAnna, asPieter].map((api) => api?.dispose()))
})

test('Anna reads her own care fields', async () => {
	const player = await (await asAnna.get(`${PLAYERS}/${playerId}`, { headers: OCS })).json()
	expect(player.allergies).toBe(CARE.allergies)
	expect(player.emergencyContactPhone).toBe(CARE.emergencyContactPhone)
})

test('another player reads no care field on her player, in the list, a search or a CSV export', async () => {
	const single = await asPieter.get(`${PLAYERS}/${playerId}`, { headers: OCS })
	expect(single.status()).toBe(200)
	const player = await single.json()
	expect(player.name).toBe(`${RUN} Anna de Vries`)
	for (const field of Object.keys(CARE)) {
		expect(player[field], field).toBeUndefined()
	}

	const list = await (await asPieter.get(`${PLAYERS}?_limit=500`, { headers: OCS })).json()
	const row = (list.results as Array<Record<string, unknown>>).find((r) => r.id === playerId)
	expect(row).toBeDefined()
	for (const field of Object.keys(CARE)) {
		expect(row?.[field], field).toBeUndefined()
	}

	const search = await (await asPieter.get(`${PLAYERS}?_search=${encodeURIComponent(CARE.allergies)}`, { headers: OCS })).json()
	expect(search.results).toHaveLength(0)

	const csv = await asPieter.get(`${PLAYERS}/export?type=csv`, { headers: { 'OCS-APIRequest': 'true' } })
	expect(csv.status()).toBe(200)
	const text = await csv.text()
	expect(text).toContain(`${RUN} Anna de Vries`)
	expect(text).not.toContain(CARE.allergies)
	expect(text).not.toContain(CARE.emergencyContactPhone)
})
