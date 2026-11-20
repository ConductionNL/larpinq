/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/StaffInviteLanding.vue: reading the invite a link
 * opens, joining its role, and the message shown for a link that adds nobody.
 * Only fetch is faked; the real staffInvites service runs.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import StaffInviteLanding from '../../src/views/StaffInviteLanding.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))

const LINK_CODE = 'link-code-from-the-route'

/**
 * Fake fetch answering each request in turn, recording path, method and body.
 *
 * @param {Array<{status: number, body: object|null}>} answers The answers, in order.
 * @return {Array<{path: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({
			path: String(url).replace(/^.*\/apps\/larpinq\/api/, ''),
			method: options.method ?? 'GET',
			body: options.body ? JSON.parse(options.body) : undefined,
		})
		const answer = answers.shift() ?? { status: 200, body: {} }
		return {
			ok: answer.status >= 200 && answer.status < 300,
			status: answer.status,
			json: async () => answer.body,
		}
	}
	return calls
}

/**
 * The component's state with the route's link code, bound to its methods and computed values.
 *
 * @param {string|null} code The route's token parameter, or null for none.
 * @return {object} The instance.
 */
function instance(code = LINK_CODE) {
	const self = { ...StaffInviteLanding.data(), $route: { params: code === null ? {} : { token: code } } }
	for (const [name, method] of Object.entries(StaffInviteLanding.methods)) {
		self[name] = method.bind(self)
	}
	Object.defineProperty(self, 'refusal', { get: StaffInviteLanding.computed.refusal.bind(self) })
	return self
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('StaffInviteLanding mounted', () => {
	it('inspects the route code and keeps the state and role', async () => {
		const calls = fakeFetch([{ status: 200, body: { state: 'valid', role: 'larpinq-writers' } }])
		const self = instance()

		await StaffInviteLanding.mounted.call(self)

		expect(calls).toEqual([{ path: '/staff-invites/inspect', method: 'POST', body: { token: LINK_CODE } }])
		expect(self.state).toBe('valid')
		expect(self.role).toBe('larpinq-writers')
		expect(self.loading).toBe(false)
	})

	it('treats a link without a code as unknown, without asking the server', async () => {
		const calls = fakeFetch([])
		const self = instance(null)

		await StaffInviteLanding.mounted.call(self)

		expect(calls).toEqual([])
		expect(self.state).toBe('unknown')
		expect(self.role).toBe('')
		expect(self.loading).toBe(false)
	})

	it('treats a code the server does not know as unknown', async () => {
		fakeFetch([{ status: 404, body: null }])
		const self = instance()

		await StaffInviteLanding.mounted.call(self)

		expect(self.state).toBe('unknown')
		expect(self.refusal).toBe('This invite link is not valid.')
	})
})

describe('StaffInviteLanding confirm', () => {
	it('redeems the route code and shows the joined role', async () => {
		const calls = fakeFetch([{ status: 200, body: { joined: true, role: 'larpinq-rules' } }])
		const self = instance()
		self.state = 'valid'

		await self.confirm()

		expect(calls).toEqual([{ path: '/staff-invites/redeem', method: 'POST', body: { token: LINK_CODE } }])
		expect(self.state).toBe('joined')
		expect(self.role).toBe('larpinq-rules')
		expect(self.busy).toBe(false)
	})

	it('says the caller is already a member when nothing was joined', async () => {
		fakeFetch([{ status: 200, body: { joined: false, role: 'larpinq-rules' } }])
		const self = instance()

		await self.confirm()

		expect(self.state).toBe('member')
	})

	it('keeps the role it showed when a gone invite answers without one', async () => {
		fakeFetch([{ status: 410, body: { state: 'used-up' } }])
		const self = instance()
		self.role = 'larpinq-stewards'

		await self.confirm()

		expect(self.state).toBe('used-up')
		expect(self.role).toBe('larpinq-stewards')
		expect(self.refusal).toBe('This invite is used up. Ask a game master for a new one.')
		expect(self.busy).toBe(false)
	})

	it('reads a gone invite without a state as expired', async () => {
		fakeFetch([{ status: 410, body: null }])
		const self = instance()

		await self.confirm()

		expect(self.state).toBe('expired')
		expect(self.refusal).toBe('This invite has expired. Ask a game master for a new one.')
	})

	it('shows the retry message when the server fails', async () => {
		fakeFetch([{ status: 500, body: null }])
		const self = instance()

		await self.confirm()

		expect(self.state).toBe('failed')
		expect(self.refusal).toBe('The invite could not be read. Try again later.')
	})
})

describe('StaffInviteLanding refusal', () => {
	it('names each state that adds nobody', () => {
		const self = instance()
		const said = {}
		for (const state of ['expired', 'used-up', 'revoked', 'unknown', 'failed']) {
			self.state = state
			said[state] = self.refusal
		}

		expect(said).toEqual({
			expired: 'This invite has expired. Ask a game master for a new one.',
			'used-up': 'This invite is used up. Ask a game master for a new one.',
			revoked: 'This invite was revoked.',
			unknown: 'This invite link is not valid.',
			failed: 'The invite could not be read. Try again later.',
		})
	})
})
