/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The help desk thread: Anna asks, Joris answers, Anna is notified and
 * replies, and the question is open again (REQ-CHD-001 to REQ-CHD-003).
 *
 * Anna (larpers) and Joris (gamemasters) are real accounts made for the run,
 * each with its own request context and no stored session, so every write is
 * judged as that user and not as the admin of the Playwright config.
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { runBackgroundJobs } from './_background-jobs.ts'
import { BASE_URL, LARPINQ_SETTINGS_API, OR_OBJECTS_API } from './_base-url.ts'

const RUN = `hd${Date.now().toString(36)}`
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const JSON_HEADERS = { ...OCS, 'Content-Type': 'application/json' }
const ADMIN = { username: process.env.NC_ADMIN_USER ?? 'admin', password: process.env.NC_ADMIN_PASS ?? 'admin' }

const anna = { username: `${RUN}-anna`, password: `Anna-${RUN}-pw!1`, group: 'larpers' }
const joris = { username: `${RUN}-joris`, password: `Joris-${RUN}-pw!1`, group: 'gamemasters' }

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

/**
 * The subjects of a user's Nextcloud notifications.
 *
 * @param {APIRequestContext} api The user's context.
 * @return {Promise<string[]>}
 */
async function notificationSubjects(api: APIRequestContext): Promise<string[]> {
	const res = await api.get('/ocs/v2.php/apps/notifications/api/v2/notifications', { headers: OCS })
	expect(res.status(), 'the notifications app answers').toBe(200)
	return ((await res.json()).ocs.data as Array<{ subject: string }>).map((n) => n.subject)
}

test.describe('help desk thread (communication-help-desk)', () => {
	test.describe.configure({ mode: 'serial' })

	let admin: APIRequestContext
	let asAnna: APIRequestContext
	let asJoris: APIRequestContext
	let questions = ''
	let messages = ''
	let questionId = ''

	test.beforeAll(async () => {
		admin = await contextFor(ADMIN)
		for (const user of [anna, joris]) {
			const res = await admin.post('/ocs/v2.php/cloud/users', {
				headers: OCS,
				form: { userid: user.username, password: user.password, 'groups[]': user.group },
			})
			expect(res.status(), `create ${user.username}`).toBe(200)
		}
		asAnna = await contextFor(anna)
		asJoris = await contextFor(joris)

		const settings = await (await admin.get(LARPINQ_SETTINGS_API, { headers: OCS })).json()
		const cfg = settings.configuration
		questions = `${OR_OBJECTS_API}/${cfg.register}/${cfg.helpquestion_schema}`
		messages = `${OR_OBJECTS_API}/${cfg.register}/${cfg.helpmessage_schema}`
	})

	test.afterAll(async () => {
		if (questionId !== '') {
			await admin.delete(`${questions}/${questionId}`, { headers: OCS })
		}
		for (const user of [anna, joris]) {
			await admin.delete(`/ocs/v2.php/cloud/users/${user.username}`, { headers: OCS })
		}
		await Promise.all([admin, asAnna, asJoris].map((api) => api?.dispose()))
	})

	test('Anna asks and her question is open, with her as the asker', async () => {
		const res = await asAnna.post(questions, {
			headers: JSON_HEADERS,
			data: { subject: `Can I bring a real bow? ${RUN}`, body: 'For Winter Court 2026.', status: 'open' },
		})
		expect(res.status()).toBe(201)
		const question = await res.json()
		questionId = question.id
		expect(question.status).toBe('open')
		expect(question.askerUid).toBe(anna.username)
	})

	test('Joris answers: the question is answered and Anna is notified', async () => {
		const res = await asJoris.post(messages, {
			headers: JSON_HEADERS,
			data: { question: questionId, body: 'Only LARP-safe bows, checked at the gate.' },
		})
		expect(res.status()).toBe(201)
		const message = await res.json()
		expect(message.fromOrganiser).toBe(true)
		expect(message.author).toBe(joris.username)

		const question = await (await asJoris.get(`${questions}/${questionId}`, { headers: OCS })).json()
		expect(question.status).toBe('answered')

		runBackgroundJobs()
		const subjects = await notificationSubjects(asAnna)
		expect(subjects.some((s) => s.includes(`Can I bring a real bow? ${RUN}`))).toBe(true)
	})

	test('Anna replies and the question is open again', async () => {
		const res = await asAnna.post(messages, {
			headers: JSON_HEADERS,
			data: { question: questionId, body: 'Does a crossbow count?' },
		})
		expect(res.status()).toBe(201)
		expect((await res.json()).fromOrganiser).toBe(false)

		const question = await (await asJoris.get(`${questions}/${questionId}`, { headers: OCS })).json()
		expect(question.status).toBe('open')
	})

	test('another player cannot write in Anna\'s thread', async () => {
		const other = { username: `${RUN}-pieter`, password: `Pieter-${RUN}-pw!1`, group: 'larpers' }
		expect((await admin.post('/ocs/v2.php/cloud/users', {
			headers: OCS,
			form: { userid: other.username, password: other.password, 'groups[]': other.group },
		})).status()).toBe(200)
		const asOther = await contextFor(other)
		try {
			const res = await asOther.post(messages, {
				headers: JSON_HEADERS,
				data: { question: questionId, body: 'Can I come too?' },
			})
			expect(res.ok()).toBe(false)
		} finally {
			await asOther.dispose()
			await admin.delete(`/ocs/v2.php/cloud/users/${other.username}`, { headers: OCS })
		}
	})
})
