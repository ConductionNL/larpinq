/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/RegistrationChanges.vue: what the tab knows it may
 * offer after loading, and what a change does to the tab once it answers.
 * Only fetch is faked; the real registrationChanges service runs.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import RegistrationChanges from '../../src/views/RegistrationChanges.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

const REG = 'reg-1'

/**
 * Fake fetch answering each request in turn, recording url and method.
 *
 * @param {Array<{ok: boolean, body: object}>} answers The answers, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({
			url,
			method: options.method ?? 'GET',
			body: options.body ? JSON.parse(options.body) : undefined,
		})
		const answer = answers.shift() ?? { ok: true, body: {} }
		return { ok: answer.ok, status: answer.ok ? 200 : 400, json: async () => answer.body }
	}
	return calls
}

/**
 * The component's state, bound to its methods and computed values.
 *
 * @return {object} The instance.
 */
function instance() {
	const self = { ...RegistrationChanges.data(), registrationId: REG }
	for (const [name, method] of Object.entries(RegistrationChanges.methods)) {
		self[name] = method.bind(self)
	}
	return self
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('RegistrationChanges load', () => {
	it('keeps what the server allows and stops loading', async () => {
		const calls = fakeFetch([{ ok: true, body: { canCancel: true, refundable: true } }])
		const self = instance()

		await self.load()

		expect(calls).toEqual([
			{ url: `/index.php/apps/larpinq/api/registrations/${REG}/changes`, method: 'GET', body: undefined },
		])
		expect(self.allowed).toEqual({ canCancel: true, refundable: true, loaded: true })
		expect(self.loading).toBe(false)
	})

	it('offers nothing when the changes cannot be read', async () => {
		fakeFetch([{ ok: false, body: { error: 'no' } }])
		const self = instance()

		await self.load()

		expect(self.allowed).toEqual({ loaded: false })
		expect(self.loading).toBe(false)
	})
})

describe('RegistrationChanges nothingToDo', () => {
	it.each([
		[{}, true],
		[{ canCancel: true }, false],
		[{ canOfferTransfer: true }, false],
		[{ canWithdrawTransfer: true }, false],
		[{ canAcceptTransfer: true }, false],
		[{ canAddParticipant: true }, false],
		[{ canCancel: false, canAddParticipant: false }, true],
	])('%j gives %s', (allowed, expected) => {
		expect(RegistrationChanges.computed.nothingToDo.call({ allowed })).toBe(expected)
	})
})

describe('RegistrationChanges run', () => {
	it('on success shows the done text, clears the inputs and reloads', async () => {
		const calls = fakeFetch([{ ok: true, body: {} }, { ok: true, body: { canCancel: false } }])
		const self = instance()
		Object.assign(self, { account: 'bob', participant: 'Kim', confirming: true, message: 'old' })

		await self.run(() => self.offerTransfer(REG, ' bob '), 'Offered.')

		expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual([
			`POST /index.php/apps/larpinq/api/registrations/${REG}/transfer`,
			`GET /index.php/apps/larpinq/api/registrations/${REG}/changes`,
		])
		expect(calls[0].body).toEqual({ account: 'bob' })
		expect(self).toMatchObject({
			busy: false,
			confirming: false,
			message: '',
			done: 'Offered.',
			account: '',
			participant: '',
			allowed: { canCancel: false, loaded: true },
		})
	})

	it('on refusal shows the server message and keeps the inputs', async () => {
		const calls = fakeFetch([{ ok: false, body: { error: 'The event is full.' } }])
		const self = instance()
		Object.assign(self, { participant: 'Kim', confirming: true, done: 'earlier' })

		await self.run(() => self.addParticipant(REG, 'Kim'), 'Added.')

		expect(calls).toHaveLength(1)
		expect(self).toMatchObject({
			busy: false,
			confirming: false,
			message: 'The event is full.',
			done: '',
			participant: 'Kim',
		})
	})

	it('on a refusal without a message falls back to a generic one', async () => {
		fakeFetch([{ ok: false, body: {} }])
		const self = instance()

		await self.run(() => self.cancelRegistration(REG, 'credit'), 'Cancelled.')

		expect(self.message).toBe('That did not work.')
	})
})

describe('RegistrationChanges formatDate', () => {
	it('formats a valid date and blanks an invalid one', () => {
		const { formatDate } = RegistrationChanges.methods
		expect(formatDate('2026-12-01T10:00:00Z')).toBe(new Date('2026-12-01T10:00:00Z').toLocaleDateString())
		expect(formatDate('not a date')).toBe('')
	})
})
