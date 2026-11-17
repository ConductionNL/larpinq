/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * communication-help-desk task 3.3 (REQ-CHD-001 to REQ-CHD-003): player Anna
 * (in `larpers` only) asks a question, game master Joris (in `gamemasters`)
 * answers it, Anna is notified and replies, and the question is open again.
 * Every write goes through OpenRegister as that user, the path the Help and
 * Help desk pages use, so HelpMessageListener stamps the asker, moves the
 * status and refuses what it must. Anna's Help page and Joris's Help desk are
 * checked in the browser, each in a context of their own with no stored
 * admin state.
 *
 * Portal asking (REQ-CHD-004) goes through portaliq and is covered by the
 * PortalContributionProvider PHPUnit tests.
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './_base-url.ts'

const RUN = `${Date.now()}-${Math.floor(Math.random() * 1e4)}`
const OCS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const OBJECTS = `${BASE_URL}/index.php/apps/openregister/api/objects/larpinq`
const QUESTIONS = `${OBJECTS}/larping_help_question`
const MESSAGES = `${OBJECTS}/larping_help_message`
const NOTIFICATIONS = `${BASE_URL}/ocs/v2.php/apps/notifications/api/v2/notifications?format=json`
const APP = `${BASE_URL}/index.php/apps/larpinq`

const anna = { uid: `anna-${RUN}`, password: `Anna-${RUN}-pw!9`, group: 'larpers' }
const joris = { uid: `joris-${RUN}`, password: `Joris-${RUN}-pw!9`, group: 'gamemasters' }

let admin: APIRequestContext
let asAnna: APIRequestContext
let asJoris: APIRequestContext
let questionId = ''
const messageIds: string[] = []

/**
 * A request context signed in as one user.
 *
 * @param user The user.
 * @return The context.
 */
async function signedIn(user: { uid: string, password: string }): Promise<APIRequestContext> {
	return request.newContext({ httpCredentials: { username: user.uid, password: user.password }, extraHTTPHeaders: OCS })
}

/**
 * A page signed in as one user, with an empty storage state.
 *
 * @param browser The browser.
 * @param user The user.
 * @return The page.
 */
async function pageAs(browser: Browser, user: { uid: string, password: string }): Promise<Page> {
	const context = await browser.newContext({
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: user.uid, password: user.password },
	})
	return context.newPage()
}

/**
 * The question as its asker reads it.
 *
 * @return The question.
 */
async function question(): Promise<Record<string, any>> {
	const res = await asAnna.get(`${QUESTIONS}/${questionId}`)
	expect(res.status()).toBe(200)
	return res.json()
}

/**
 * The subjects of a user's Nextcloud notifications, or null without the app.
 *
 * @param api The user's context.
 * @return The notification texts.
 */
async function notificationTexts(api: APIRequestContext): Promise<string[] | null> {
	const res = await api.get(NOTIFICATIONS)
	if (res.status() === 404) {
		return null
	}
	const data = (await res.json())?.ocs?.data ?? []
	return (data as Array<Record<string, any>>).map((row) => `${row.subject ?? ''} ${row.message ?? ''} ${row.object_id ?? ''}`)
}

test.describe.serial('communication-help-desk', () => {
	test.beforeAll(async () => {
		admin = await request.newContext({ httpCredentials: { username: process.env.NC_USER || 'admin', password: process.env.NC_PASS || 'admin' }, extraHTTPHeaders: OCS })
		for (const group of [anna.group, joris.group]) {
			await admin.post(`${BASE_URL}/ocs/v2.php/cloud/groups`, { form: { groupid: group } })
		}
		for (const user of [anna, joris]) {
			const made = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
				form: { userid: user.uid, password: user.password, 'groups[]': user.group },
			})
			expect(made.ok(), `creating ${user.uid} must succeed`).toBe(true)
		}
		asAnna = await signedIn(anna)
		asJoris = await signedIn(joris)
	})

	test.afterAll(async () => {
		for (const id of messageIds) {
			await admin.delete(`${MESSAGES}/${id}`)
		}
		if (questionId !== '') {
			await admin.delete(`${QUESTIONS}/${questionId}`)
		}
		for (const user of [anna, joris]) {
			await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${user.uid}`)
		}
		await Promise.all([asAnna?.dispose(), asJoris?.dispose(), admin?.dispose()])
	})

	// @e2e help-desk::anna-asks-about-her-bow
	test('Anna asks a question and it is open on her Help page', async ({ browser }) => {
		const res = await asAnna.post(QUESTIONS, {
			data: { subject: 'Can I bring a real bow?', body: 'For Winter Court 2026.', event: 'Winter Court 2026', status: 'open' },
		})
		expect(res.ok(), await res.text()).toBe(true)
		const body = await res.json()
		questionId = String(body.id ?? body['@self']?.id)

		const stored = await question()
		expect(stored).toMatchObject({ subject: 'Can I bring a real bow?', status: 'open', askerUid: anna.uid })

		const notified = await notificationTexts(asJoris)
		if (notified !== null) {
			await expect.poll(async () => (await notificationTexts(asJoris))!.join('\n'), { timeout: 30_000, message: 'game masters hear of a new question' })
				.toMatch(/bow|question|vraag/i)
		}

		const page = await pageAs(browser, anna)
		await page.goto(`${APP}/help`, { waitUntil: 'domcontentloaded' })
		await expect(page.getByText('Can I bring a real bow?').first()).toBeVisible({ timeout: 20_000 })
		await page.context().close()
	})

	// @e2e help-desk::joris-answers
	test('Joris answers, the question is answered, and Anna is notified', async () => {
		const res = await asJoris.post(MESSAGES, {
			data: { question: questionId, body: 'Only LARP-safe bows, checked at the gate' },
		})
		expect(res.ok(), await res.text()).toBe(true)
		const body = await res.json()
		messageIds.push(String(body.id ?? body['@self']?.id))
		expect(body).toMatchObject({ author: joris.uid, fromOrganiser: true })

		await expect.poll(async () => (await question()).status, { timeout: 15_000 }).toBe('answered')

		const notified = await notificationTexts(asAnna)
		if (notified !== null) {
			await expect.poll(async () => (await notificationTexts(asAnna))!.length, { timeout: 30_000, message: 'Anna hears of the answer' })
				.toBeGreaterThan(0)
		}
	})

	// @e2e help-desk::a-follow-up
	test('Anna replies and the question is open again on the Help desk', async ({ browser }) => {
		const res = await asAnna.post(MESSAGES, { data: { question: questionId, body: 'Does a crossbow count?' } })
		expect(res.ok(), await res.text()).toBe(true)
		const body = await res.json()
		messageIds.push(String(body.id ?? body['@self']?.id))
		expect(body).toMatchObject({ author: anna.uid, fromOrganiser: false })

		await expect.poll(async () => (await question()).status, { timeout: 15_000 }).toBe('open')

		const page = await pageAs(browser, joris)
		await page.goto(`${APP}/help`, { waitUntil: 'domcontentloaded' })
		await expect(page.getByText('Can I bring a real bow?').first()).toBeVisible({ timeout: 20_000 })
		await page.context().close()
	})

	test('a closed question takes no new message', async () => {
		const closed = await asJoris.patch(`${QUESTIONS}/${questionId}`, { data: { status: 'closed' } })
		expect(closed.ok(), await closed.text()).toBe(true)

		const res = await asAnna.post(MESSAGES, { data: { question: questionId, body: 'One more thing' } })
		expect(res.status()).toBe(422)
		expect(await res.text()).toMatch(/larpinq\.help\.closed|This question is closed/)
	})
})
