/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/eventMessages.js and
 * src/dialogs/EmailParticipantsDialog.vue (communication-mail-participants):
 * the audience query, the preview, saving and sending, and the dialog's
 * preview-then-confirm flow. Only fetch is faked; the real service runs.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import EmailParticipantsDialog from '../../src/dialogs/EmailParticipantsDialog.vue'
import { audienceQuery, previewAudience, sendMessage } from '../../src/services/eventMessages.js'

vi.mock('@nextcloud/l10n', () => {
	const translate = (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match))
	return { translate, translatePlural: (app, one) => one }
})
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextArea', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

const BASE = '/index.php/apps/larpinq/api/events/ev-1/messages'

/**
 * Fake fetch answering each request in turn.
 *
 * @param {Array<{status: number, body: object}>} answers The answers, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({ url, method: options.method ?? 'GET', body: options.body ? JSON.parse(options.body) : undefined })
		const answer = answers.shift()
		return { ok: answer.status < 400, status: answer.status, json: async () => answer.body }
	}
	return calls
}

/**
 * The dialog's state, bound to its methods, with its computed values live.
 *
 * @return {object} The instance.
 */
function instance() {
	const self = { ...EmailParticipantsDialog.data(), eventId: 'ev-1', $route: { params: {} } }
	for (const [name, method] of Object.entries(EmailParticipantsDialog.methods)) {
		self[name] = method.bind(self)
	}
	for (const name of ['id', 'audience', 'canSend']) {
		Object.defineProperty(self, name, { get: () => EmailParticipantsDialog.computed[name].call(self) })
	}
	return self
}

afterEach(() => {
	delete globalThis.fetch
})

describe('eventMessages service', () => {
	it('puts the audience in the query', () => {
		expect(audienceQuery({ statuses: ['accepted', 'waitlisted'], checkedIn: true, paymentOpen: false, ticketRoles: ['crew'] }))
			.toBe('statuses%5B%5D=accepted&statuses%5B%5D=waitlisted&ticketRoles%5B%5D=crew&checkedIn=true')
		expect(audienceQuery({ statuses: [], ticketRoles: [] })).toBe('')
	})

	it('previews the recipients of an audience', async () => {
		const calls = fakeFetch([{ status: 200, body: { count: 2, names: ['Anna', 'Bram'], noAddressCount: 1, noAddressNames: ['Cees'] } }])

		const preview = await previewAudience('ev-1', { statuses: ['accepted'], ticketRoles: [] })

		expect(calls[0].url).toBe(`${BASE}/preview?statuses%5B%5D=accepted`)
		expect(preview).toEqual({ ok: true, count: 2, names: ['Anna', 'Bram'], noAddressCount: 1, noAddressNames: ['Cees'], error: '' })
	})

	it('reports a refused preview', async () => {
		fakeFetch([{ status: 403, body: { error: 'Access denied' } }])

		const preview = await previewAudience('ev-1', { statuses: [] })

		expect(preview.ok).toBe(false)
		expect(preview.error).toBe('Access denied')
	})

	it('saves the draft and then sends it', async () => {
		const calls = fakeFetch([
			{ status: 201, body: { id: 'm-1', state: 'draft' } },
			{ status: 202, body: { id: 'm-1', state: 'queued', recipientCount: 2 } },
		])
		const message = { subject: 'Practical letter', body: 'See you', audience: { statuses: ['accepted'] } }

		const outcome = await sendMessage('ev-1', message)

		expect(calls.map((call) => [call.method, call.url])).toEqual([
			['POST', BASE],
			['POST', `${BASE}/m-1/send`],
		])
		expect(calls[0].body).toEqual(message)
		expect(outcome).toEqual({ ok: true, message: { id: 'm-1', state: 'queued', recipientCount: 2 }, error: '' })
	})

	it('does not send when the draft is refused', async () => {
		const calls = fakeFetch([{ status: 400, body: { error: 'A message needs a subject and a body' } }])

		const outcome = await sendMessage('ev-1', { subject: '', body: '' })

		expect(calls).toHaveLength(1)
		expect(outcome).toEqual({ ok: false, message: null, error: 'A message needs a subject and a body' })
	})
})

describe('EmailParticipantsDialog', () => {
	it('can send only after a preview with recipients, a subject and a body', async () => {
		fakeFetch([{ status: 200, body: { count: 2, names: ['Anna', 'Bram'], noAddressCount: 1, noAddressNames: ['Cees'] } }])
		const self = instance()
		self.subject = 'Practical letter'
		self.body = 'See you at {event}'

		expect(self.canSend).toBe(false)
		await self.loadPreview()
		expect(self.preview.count).toBe(2)
		expect(self.canSend).toBe(true)

		self.onAudienceChange()
		expect(self.preview).toBe(null)
		expect(self.canSend).toBe(false)
	})

	it('sends the message with its audience and shows the result', async () => {
		const calls = fakeFetch([
			{ status: 201, body: { id: 'm-1' } },
			{ status: 202, body: { id: 'm-1', state: 'queued', recipientCount: 2 } },
		])
		const self = instance()
		self.subject = ' Practical letter '
		self.body = 'See you'
		self.ticketRoles = ['player']
		self.confirming = true

		await self.send()

		expect(calls[0].body).toEqual({
			subject: 'Practical letter',
			body: 'See you',
			audience: { statuses: ['accepted'], checkedIn: false, paymentOpen: false, ticketRoles: ['player'] },
		})
		expect(self.result.state).toBe('queued')
		expect(self.confirming).toBe(false)
		expect(self.error).toBe('')
	})

	it('shows why a send failed', async () => {
		fakeFetch([
			{ status: 201, body: { id: 'm-1' } },
			{ status: 409, body: { error: 'This message was already sent; send a copy instead.' } },
		])
		const self = instance()
		self.subject = 'x'
		self.body = 'y'

		await self.send()

		expect(self.result).toBe(null)
		expect(self.error).toBe('The message was not sent: This message was already sent; send a copy instead.')
	})

	it('takes the event from the route when the action passes a placeholder', () => {
		const self = instance()
		self.eventId = '@objectId'
		self.$route = { params: { id: 'ev-9' } }

		expect(self.id).toBe('ev-9')
	})
})
