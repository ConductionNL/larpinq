/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Erasure of care records through OpenRegister (players-care-details-and-erasure
 * task 3.1, REQ-PCE-003 and REQ-PCE-004).
 *
 * The `eventCareRecord` schema declares its retention in the register fragment:
 * `archive.afleidingswijze: eigenschap` on `eraseAfter` (the event's end plus
 * the event's `careRetentionDays`), nomination `vernietigen`. Larpinq itself
 * never deletes a copy; OpenRegister's destruction workflow does:
 *
 *   1. A fixture care record whose `eraseAfter` lies in the past.
 *   2. The daily sweep runs (the instance's background jobs), and the record is
 *      put on a destruction list. When the sweep is not due in this run, the
 *      list is drawn up for the record through `POST /api/archival/destruction-lists`,
 *      which judges it with the sweep's own rule.
 *   3. A game master (the suite's admin, an archivist) approves the list.
 *   4. The execution job runs; the record is gone and a destruction
 *      certificate names the list.
 *
 * A second record, due in the future, is the control: it is never eligible.
 *
 * Written 24 Nov 2026 per the UNBLOCK rule; runs in the nightly against a live
 * instance. Not run in the build that wrote it.
 *
 * @spec openspec/specs/player-care-details/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { runBackgroundJobs } from './_background-jobs.ts'
import { BASE_URL, OR_OBJECTS_API } from './_base-url.ts'
import { postInInitialState } from './workflows/fixtures.ts'

const RUN = `erase${Date.now().toString(36)}`
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const ADMIN = { username: process.env.NC_ADMIN_USER ?? 'admin', password: process.env.NC_ADMIN_PASS ?? 'admin' }
const OBJECTS = `${OR_OBJECTS_API}/larpinq`
const ARCHIVAL = `${BASE_URL}/index.php/apps/openregister/api/archival`
const CARE = 'larping_event_care_record'
const DAY = 24 * 3600 * 1000

let admin: APIRequestContext
const created: string[] = []
let dueId = ''
let futureId = ''
let listId = ''

/**
 * Create an object as the admin and remember it for afterAll.
 *
 * @param schema The schema slug.
 * @param data The object.
 * @return The new id.
 */
async function create(schema: string, data: Record<string, unknown>): Promise<string> {
	const res = await postInInitialState(admin, `${OBJECTS}/${schema}`, data, JSON_HEADERS)
	expect(res.ok(), await res.text()).toBe(true)
	const json = await res.json()
	const id = String(json?.id ?? json?.['@self']?.id)
	created.unshift(`${OBJECTS}/${schema}/${id}`)
	return id
}

/**
 * The destruction lists in review that hold this record, newest first.
 *
 * @param uuid The record uuid.
 * @return The list uuids.
 */
async function listsHolding(uuid: string): Promise<string[]> {
	const res = await admin.get(`${ARCHIVAL}/destruction-lists`, { headers: OCS })
	expect(res.ok(), await res.text()).toBe(true)
	const body = await res.json()
	expect(body.configured, 'OpenRegister has a destruction-list register').toBe(true)
	const found: string[] = []
	for (const row of (body.results ?? [])) {
		const detail = await admin.get(`${ARCHIVAL}/destruction-lists/${row.uuid}`, { headers: OCS })
		if (!detail.ok()) continue
		const objects = ((await detail.json())?.objects ?? []) as Array<{ uuid?: string }>
		if (objects.some((entry) => entry.uuid === uuid)) {
			found.push(String(row.uuid))
		}
	}
	return found
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { ...ADMIN, send: 'always' },
	})

	const playerId = await create('player', { name: `${RUN} Anna de Vries` })
	const pastEvent = await create('larping_event', {
		name: `${RUN} Autumn Gathering`,
		startDate: new Date(Date.now() - 40 * DAY).toISOString(),
		endDate: new Date(Date.now() - 38 * DAY).toISOString(),
		careRetentionDays: 30,
	})
	const pastRegistration = await create('larping_registration', { event: pastEvent, player: playerId, status: 'accepted' })
	dueId = await create(CARE, {
		event: pastEvent,
		registration: pastRegistration,
		player: playerId,
		allergies: `hazelnuts-${RUN}`,
		eventEndDate: new Date(Date.now() - 38 * DAY).toISOString(),
		eraseAfter: new Date(Date.now() - 8 * DAY).toISOString(),
	})

	const nextEvent = await create('larping_event', {
		name: `${RUN} Winter Court`,
		startDate: new Date(Date.now() + 5 * DAY).toISOString(),
		endDate: new Date(Date.now() + 7 * DAY).toISOString(),
		careRetentionDays: 30,
	})
	const nextRegistration = await create('larping_registration', { event: nextEvent, player: playerId, status: 'accepted' })
	futureId = await create(CARE, {
		event: nextEvent,
		registration: nextRegistration,
		player: playerId,
		allergies: `hazelnuts-${RUN}`,
		eventEndDate: new Date(Date.now() + 7 * DAY).toISOString(),
		eraseAfter: new Date(Date.now() + 37 * DAY).toISOString(),
	})
})

test.afterAll(async () => {
	if (!admin) return
	for (const url of created) {
		await admin.delete(url, { headers: OCS }).catch(() => undefined)
	}
	await admin.dispose()
})

test('a care record past its erase date is put on a destruction list', async () => {
	runBackgroundJobs()
	let lists = await listsHolding(dueId)
	if (lists.length === 0) {
		// The daily sweep was not due in this run: draw the list up for the
		// record, judged by the sweep's own rule.
		const res = await admin.post(`${ARCHIVAL}/destruction-lists`, {
			headers: JSON_HEADERS,
			data: { objects: [dueId, futureId] },
		})
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		const refused = ((body.refused ?? []) as Array<{ uuid?: string }>).map((row) => row.uuid)
		expect(refused, 'the record due in the future is not eligible').toContain(futureId)
		lists = await listsHolding(dueId)
	}
	expect(lists.length, 'the due record is on a destruction list').toBeGreaterThan(0)
	listId = lists[0]
	expect(await listsHolding(futureId), 'the record due in the future is on no list').toEqual([])
})

test('after approval the record is destroyed and a certificate names the list', async () => {
	expect(listId, 'a list from the previous test').not.toBe('')
	const approve = await admin.post(`${ARCHIVAL}/destruction-lists/${listId}/approve`, {
		headers: JSON_HEADERS,
		data: { action: 'approve_all' },
	})
	expect(approve.ok(), await approve.text()).toBe(true)

	runBackgroundJobs()

	const gone = await admin.get(`${OBJECTS}/${CARE}/${dueId}`, { headers: OCS })
	expect(gone.status(), 'the destroyed copy no longer reads').toBe(404)
	const kept = await admin.get(`${OBJECTS}/${CARE}/${futureId}`, { headers: OCS })
	expect(kept.ok(), 'the copy due in the future is kept').toBe(true)

	const certificates = await admin.get(`${ARCHIVAL}/certificates?destructionList=${listId}`, { headers: OCS })
	expect(certificates.ok(), await certificates.text()).toBe(true)
	const body = await certificates.json()
	expect(body.total, 'a destruction certificate for the list').toBeGreaterThan(0)
	expect(body.missing ?? [], 'no executed list without its certificate').toEqual([])
})
