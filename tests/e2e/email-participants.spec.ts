/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec coverage: openspec/changes/communication-mail-participants (task 2.2,
 * REQ-CMP-001 to REQ-CMP-004).
 *
 * A game master writes to the accepted participants of "Winter Court 2026"
 * from the event page: the preview counts three recipients (the waitlisted
 * one is left out), the send is confirmed, and the message is listed on the
 * event. The three players are real Nextcloud accounts with an email address,
 * which is where ParticipantContact finds the address. Delivery itself goes
 * to the dev mail catcher in a background job and is PHPUnit's
 * (SendEventMessageJobTest).
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './_base-url.ts'
import { dismissSupportDialog } from './_nav.ts'
import { FixtureLedger, fixtureName, newApi, OR_BASE, resolveSchemaIds } from './workflows/fixtures.ts'

const JSON_HEADERS = { Accept: 'application/json', 'Content-Type': 'application/json' }
const OCS_HEADERS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
const RUN = Date.now().toString(36)
const PLAYERS = ['anna', 'joris', 'mila', 'wachtlijst'].map((name) => `cmp-${name}-${RUN}`)

let admin: APIRequestContext
const ledger = new FixtureLedger()
const ids: Record<string, string> = {}

/**
 * Create an object and track it for clean-up.
 *
 * @param slug The schema slug.
 * @param body The object.
 */
async function make(slug: string, body: Record<string, unknown>): Promise<string> {
	const res = await admin.post(`${OR_BASE}/larpinq/${slug}`, { headers: JSON_HEADERS, data: body })
	const created = await res.json().catch(() => ({}))
	expect(res.status(), JSON.stringify(created)).toBeLessThan(300)

	return ledger.track(slug, String(created.id ?? created['@self']?.id))
}

test.beforeAll(async () => {
	admin = await newApi()
	await resolveSchemaIds(admin)

	for (const uid of PLAYERS) {
		const user = await admin.post(`${BASE_URL}/ocs/v2.php/cloud/users`, {
			headers: OCS_HEADERS,
			form: { userid: uid, password: `Cmp-${RUN}-wachtwoord!`, email: `${uid}@example.org` },
		})
		expect(user.ok(), await user.text()).toBe(true)
	}

	ids.world = await make('setting', { name: fixtureName('Aldmoor') })
	ids.event = await make('larping_event', {
		name: fixtureName('Winter Court 2026'),
		setting: ids.world,
		approvalRequired: false,
	})
	for (const [index, uid] of PLAYERS.entries()) {
		const player = await make('player', { name: fixtureName(uid), userUid: uid })
		await make('larping_registration', {
			event: ids.event,
			player,
			playerUid: uid,
			status: index < 3 ? 'accepted' : 'waitlisted',
		})
	}
})

test.afterAll(async () => {
	if (!admin) return
	for (const slug of ['larping_event_message', 'larping_registration', 'player', 'larping_event', 'setting']) {
		for (const id of ledger.ids(slug)) {
			await admin.delete(`${OR_BASE}/larpinq/${slug}/${id}`, { failOnStatusCode: false })
		}
	}
	for (const uid of PLAYERS) {
		await admin.delete(`${BASE_URL}/ocs/v2.php/cloud/users/${uid}`, { headers: OCS_HEADERS, failOnStatusCode: false })
	}
	await admin.dispose()
})

// @e2e openspec/changes/communication-mail-participants/specs
test('a game master writes to the accepted participants, sees 3 recipients, sends, and finds the message on the event', async ({
	page,
}) => {
	const subject = `Briefing Winter Court ${RUN}`

	await page.goto(`${BASE_URL}/index.php/apps/larpinq/events/${ids.event}`)
	await dismissSupportDialog(page)
	await page.getByRole('button', { name: 'Email participants' }).first().click({ timeout: 30_000 })

	const dialog = page.getByTestId('email-participants-dialog')
	await expect(dialog).toBeVisible({ timeout: 15_000 })
	await dialog.getByTestId('email-participants-subject').locator('input').fill(subject)
	await dialog.getByTestId('email-participants-body').locator('textarea').fill('Welcome to {event} on {date}. Gates open at ten.')

	// Accepted is the default audience; the waitlisted player is not counted.
	const preview = page.waitForResponse((resp) => resp.url().includes('/api/') && resp.url().includes('preview'))
	await dialog.getByRole('button', { name: 'Preview recipients' }).click()
	expect((await preview).ok()).toBe(true)
	await expect(dialog.getByTestId('email-participants-preview')).toContainText('Recipients: 3')
	await expect(dialog.getByTestId('email-participants-no-address')).toHaveCount(0)

	await dialog.getByTestId('email-participants-send').click()
	await expect(dialog.getByTestId('email-participants-confirm')).toContainText('Recipients: 3')
	const sent = page.waitForResponse((resp) => resp.request().method() === 'POST' && resp.url().includes('/api/') && !resp.url().includes('preview'))
	await dialog.getByTestId('email-participants-confirm-send').click()
	expect((await sent).ok()).toBe(true)
	await expect(dialog.getByTestId('email-participants-sent')).toBeVisible({ timeout: 15_000 })

	// The message is listed on the event, once, and the store holds it with its count.
	const messages = await admin.get(`${OR_BASE}/larpinq/larping_event_message?event=${ids.event}&_limit=50`, { headers: JSON_HEADERS })
	expect(messages.ok()).toBe(true)
	const rows = ((await messages.json()).results ?? []) as Array<Record<string, any>>
	const ours = rows.filter((row) => row.subject === subject)
	expect(ours).toHaveLength(1)
	for (const row of ours) {
		ledger.track('larping_event_message', String(row.id ?? row['@self']?.id))
	}

	await dialog.getByRole('button', { name: 'Close' }).click()
	await page.reload()
	await expect(page.getByText(subject).first()).toBeVisible({ timeout: 30_000 })
})
