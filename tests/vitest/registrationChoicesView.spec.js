/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/RegistrationChoices.vue and
 * src/views/EventChoiceCounts.vue: loading the offer and the earlier choice,
 * saving a choice, and the counts an event shows. Only fetch is faked; the
 * real registrationChoices service runs.
 *
 * @spec openspec/specs/event-registration/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import EventChoiceCounts from '../../src/views/EventChoiceCounts.vue'
import RegistrationChoices from '../../src/views/RegistrationChoices.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

/**
 * Fake fetch answering each request in turn.
 *
 * @param {Array<{status: number, body: object}>} answers The answers, in order.
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
		const answer = answers.shift()
		return {
			ok: answer.status < 400,
			status: answer.status,
			json: async () => answer.body,
		}
	}
	return calls
}

/**
 * The choices tab's state, bound to its methods.
 *
 * @return {object} The instance.
 */
function choices() {
	const self = { ...RegistrationChoices.data(), registrationId: 'reg-1' }
	for (const [name, method] of Object.entries(RegistrationChoices.methods)) {
		self[name] = method.bind(self)
	}
	return self
}

afterEach(() => {
	delete globalThis.fetch
})

describe('RegistrationChoices mounted', () => {
	it('loads the offer and preselects the earlier choice', async () => {
		const calls = fakeFetch([
			{ status: 200, body: { ticketTypes: [{ id: 't-1' }], options: [], code: 'none', chosen: { ticketType: 't-1', options: ['o-1'] } } },
		])
		const self = choices()

		await RegistrationChoices.mounted.call(self)

		expect(calls.map((call) => call.url)).toEqual(['/index.php/apps/larpinq/api/registrations/reg-1/offer'])
		expect(self.offer.state).toBe('ok')
		expect(self.ticketType).toBe('t-1')
		expect(self.options).toEqual(['o-1'])
		expect(self.loading).toBe(false)
	})

	it('starts empty when the offer is refused', async () => {
		fakeFetch([{ status: 403, body: {} }])
		const self = choices()

		await RegistrationChoices.mounted.call(self)

		expect(self.offer).toEqual({ state: 'refused' })
		expect(self.ticketType).toBe('')
		expect(self.options).toEqual([])
	})

	it('asks the offer again with a typed access code', async () => {
		const calls = fakeFetch([{ status: 200, body: { code: 'valid' } }])
		const self = choices()
		self.code = ' EARLY BIRD '

		await self.load()

		expect(calls[0].url).toBe('/index.php/apps/larpinq/api/registrations/reg-1/offer?code=EARLY%20BIRD')
		expect(self.offer).toEqual({ state: 'ok', code: 'valid' })
	})
})

describe('RegistrationChoices save', () => {
	it('patches the registration with the choice and marks it saved', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }])
		const self = choices()
		Object.assign(self, { ticketType: 't-2', options: ['o-1', 'o-3'], code: 'EARLY', message: 'old' })

		await self.save()

		expect(calls).toEqual([
			{
				url: '/index.php/apps/openregister/api/objects/larpinq/larping_registration/reg-1',
				method: 'PATCH',
				body: { options: ['o-1', 'o-3'], ticketType: 't-2', code: 'EARLY' },
			},
		])
		expect(self).toMatchObject({ saving: false, saved: true, message: '' })
	})

	it('shows the refusal message', async () => {
		fakeFetch([{ status: 400, body: { errors: { message: 'That ticket is sold out.' } } }])
		const self = choices()

		await self.save()

		expect(self).toMatchObject({ saving: false, saved: false, message: 'That ticket is sold out.' })
	})

	it('falls back to a generic message', async () => {
		fakeFetch([{ status: 500, body: {} }])
		const self = choices()

		await self.save()

		expect(self.message).toBe('Could not save the choices.')
		expect(self.saved).toBe(false)
	})
})

describe('EventChoiceCounts', () => {
	it('loads the counts of the event', async () => {
		const calls = fakeFetch([{ status: 200, body: { ticketTypes: [{ id: 't-1', count: 3 }], options: [{ id: 'o-1', count: 1 }] } }])
		const self = { ...EventChoiceCounts.data(), eventId: 'ev-1' }

		await EventChoiceCounts.mounted.call(self)

		expect(calls[0].url).toBe('/index.php/apps/larpinq/api/events/ev-1/choices')
		expect(self.counts.state).toBe('ok')
		expect(self.loading).toBe(false)
	})

	it('lists ticket types before options, and tolerates missing lists', () => {
		const { rows } = EventChoiceCounts.computed

		expect(rows.call({ counts: { ticketTypes: [{ id: 't-1' }], options: [{ id: 'o-1' }] } }).map((row) => row.id)).toEqual(['t-1', 'o-1'])
		expect(rows.call({ counts: { state: 'failed' } })).toEqual([])
	})
})
