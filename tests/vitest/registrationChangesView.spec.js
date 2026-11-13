/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The registration-changes tab (views/RegistrationChanges.vue): what the user
 * may change, running a change and showing larpinq's answer, and the date
 * shown for a deadline.
 *
 * The component's options run against a fake `this`; only fetch and the
 * Nextcloud components are mocked.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import RegistrationChanges from '../../src/views/RegistrationChanges.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
	translatePlural: (app, one) => one,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

/**
 * A fake component for one registration.
 *
 * @param {object} overrides Data to override.
 * @return {object} The fake `this`.
 */
function component(overrides = {}) {
	const self = { registrationId: 'reg-1', ...RegistrationChanges.data(), ...overrides }
	for (const [name, method] of Object.entries(RegistrationChanges.methods)) {
		self[name] = method.bind(self)
	}
	Object.defineProperty(self, 'nothingToDo', {
		get: () => RegistrationChanges.computed.nothingToDo.call(self),
	})
	return self
}

/**
 * Mock fetch with a queue of answers.
 *
 * @param {Array<{ok: boolean, body: object}>} answers The answers, in order.
 * @return {Array} The recorded calls.
 */
function mockFetch(answers) {
	const calls = []
	globalThis.OC = { requestToken: 'token-1' }
	globalThis.fetch = vi.fn(async (url, init) => {
		calls.push([url, init])
		const next = answers.shift()
		return { ok: next.ok, json: async () => next.body }
	})
	return calls
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('registration changes: what may change', () => {
	it('has nothing to do while every action is closed', () => {
		expect(component({ allowed: { loaded: true, canCancel: false } }).nothingToDo).toBe(true)
	})

	it.each(['canCancel', 'canOfferTransfer', 'canWithdrawTransfer', 'canAcceptTransfer', 'canAddParticipant'])(
		'has something to do when %s is open',
		(flag) => {
			expect(component({ allowed: { loaded: true, [flag]: true } }).nothingToDo).toBe(false)
		},
	)

	it('loads the answers for this registration', async () => {
		const calls = mockFetch([{ ok: true, body: { canCancel: true, deadline: '2026-12-01' } }])
		const self = component()

		await self.load()

		expect(calls[0][0]).toBe('/index.php/apps/larpinq/api/registrations/reg-1/changes')
		expect(self.allowed).toEqual({ canCancel: true, deadline: '2026-12-01', loaded: true })
		expect(self.loading).toBe(false)
	})

	it('marks the answers as not loaded when larpinq refuses', async () => {
		mockFetch([{ ok: false, body: { error: 'Forbidden' } }])
		const self = component({ allowed: { loaded: true, canCancel: true } })

		await self.load()

		expect(self.allowed).toEqual({ loaded: false })
		expect(self.loading).toBe(false)
	})
})

describe('registration changes: running a change', () => {
	it('on success shows the success text, clears the inputs and reloads', async () => {
		const calls = mockFetch([{ ok: true, body: { canCancel: false } }])
		const self = component({ busy: false, confirming: true, message: 'old', account: 'bob', participant: 'Ann' })
		const change = vi.fn(async () => ({ ok: true, message: '' }))

		await self.run(change, 'Cancelled.')

		expect(change).toHaveBeenCalledTimes(1)
		expect(self.done).toBe('Cancelled.')
		expect(self.message).toBe('')
		expect(self.account).toBe('')
		expect(self.participant).toBe('')
		expect(self.busy).toBe(false)
		expect(self.confirming).toBe(false)
		expect(calls).toHaveLength(1)
		expect(self.allowed).toEqual({ canCancel: false, loaded: true })
	})

	it('on failure shows larpinq\'s reason and keeps the inputs', async () => {
		const calls = mockFetch([])
		const self = component({ confirming: true, account: 'bob' })

		await self.run(async () => ({ ok: false, message: 'Deadline passed' }), 'Done.')

		expect(self.message).toBe('Deadline passed')
		expect(self.done).toBe('')
		expect(self.account).toBe('bob')
		expect(self.confirming).toBe(false)
		expect(self.busy).toBe(false)
		expect(calls).toHaveLength(0)
	})

	it('on failure without a reason shows a fallback', async () => {
		mockFetch([])
		const self = component()

		await self.run(async () => ({ ok: false, message: '' }), 'Done.')

		expect(self.message).toBe('That did not work.')
	})
})

describe('registration changes: dates', () => {
	it('formats a valid moment and leaves an invalid one empty', () => {
		const self = component()
		expect(self.formatDate('2026-12-01T10:00:00Z')).toBe(new Date('2026-12-01T10:00:00Z').toLocaleDateString())
		expect(self.formatDate('not a date')).toBe('')
	})
})
