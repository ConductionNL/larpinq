/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/StaffPage.vue: loading the team, creating and
 * revoking invites, removing members, and what each answer does to the page.
 * Only fetch is faked; the real staffInvites service runs.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import StaffPage from '../../src/views/StaffPage.vue'
import { STAFF_ROLES } from '../../src/services/staffInvites.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))

const TEAM = {
	roles: [{ id: 'gamemasters', members: [{ uid: 'alice', displayName: 'Alice' }] }],
	invites: [{ id: 'inv-1', role: 'larpinq-stewards', state: 'valid', uses: [] }],
}

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
 * The component's state, bound to its methods and computed values.
 *
 * @return {object} The instance.
 */
function instance() {
	const self = { ...StaffPage.data() }
	for (const [name, method] of Object.entries(StaffPage.methods)) {
		self[name] = method.bind(self)
	}
	Object.defineProperty(self, 'roleOptions', { get: StaffPage.computed.roleOptions.bind(self) })
	return self
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('StaffPage defaults', () => {
	it('starts loading with a one-use, week-long steward invite form', () => {
		const self = instance()

		expect(self.loading).toBe(true)
		expect(self.busy).toBe(false)
		expect(self.form).toEqual({ role: 'larpinq-stewards', days: '7', maxUses: '1' })
		expect(self.link).toBe('')
	})

	it('offers every staff role with its label', () => {
		const self = instance()

		expect(self.roleOptions.map((option) => option.id)).toEqual(STAFF_ROLES)
		expect(self.roleOptions[0]).toEqual({ id: 'gamemasters', label: 'Game master' })
		expect(self.roleOptions.find((option) => option.id === 'larpinq-treasurers').label).toBe('Treasurer')
	})
})

describe('StaffPage loading', () => {
	it('reads the team when mounted and stops loading', async () => {
		const calls = fakeFetch([{ status: 200, body: TEAM }])
		const self = instance()

		await StaffPage.mounted.call(self)

		expect(calls).toEqual([{ path: '/staff', method: 'GET', body: undefined }])
		expect(self.team).toEqual({ state: 'ok', roles: TEAM.roles, invites: TEAM.invites })
		expect(self.loading).toBe(false)
	})

	it('shows a refused team when the caller may not manage staff', async () => {
		fakeFetch([{ status: 403, body: { error: 'no' } }])
		const self = instance()

		await StaffPage.mounted.call(self)

		expect(self.team).toEqual({ state: 'refused' })
		expect(self.loading).toBe(false)
	})
})

describe('StaffPage joinedNames', () => {
	it('lists the uids that joined with the invite', () => {
		const self = instance()

		expect(self.joinedNames({ uses: [{ uid: 'bob' }, { uid: 'carol' }] })).toBe('bob, carol')
	})

	it('shows a dash when nobody joined or the uses are missing', () => {
		const self = instance()

		expect(self.joinedNames({ uses: [] })).toBe('–')
		expect(self.joinedNames({})).toBe('–')
		expect(self.joinedNames({ uses: 'bob' })).toBe('–')
	})
})

describe('StaffPage create', () => {
	it('sends the form as numbers, shows the link once and reloads the team', async () => {
		const calls = fakeFetch([
			{ status: 201, body: { link: 'https://cloud.example/invite/abc', invite: { id: 'inv-2' } } },
			{ status: 200, body: TEAM },
		])
		const self = instance()
		self.form = { role: 'larpinq-writers', days: '3', maxUses: '5' }
		self.message = 'old'

		await self.create()

		expect(calls).toEqual([
			{ path: '/staff-invites', method: 'POST', body: { role: 'larpinq-writers', days: 3, maxUses: 5 } },
			{ path: '/staff', method: 'GET', body: undefined },
		])
		expect(self.link).toBe('https://cloud.example/invite/abc')
		expect(self.message).toBe('')
		expect(self.messageType).toBe('success')
		expect(self.team.invites).toEqual(TEAM.invites)
		expect(self.busy).toBe(false)
	})

	it('clears the link and shows the server error on a refusal, without reloading', async () => {
		const calls = fakeFetch([{ status: 400, body: { error: 'Unknown role' } }])
		const self = instance()
		self.link = 'https://cloud.example/invite/old'

		await self.create()

		expect(calls).toHaveLength(1)
		expect(self.link).toBe('')
		expect(self.message).toBe('Unknown role')
		expect(self.messageType).toBe('error')
		expect(self.busy).toBe(false)
	})

	it('falls back to the generic save error when the refusal has no message', async () => {
		fakeFetch([{ status: 500, body: null }])
		const self = instance()

		await self.create()

		expect(self.message).toBe('Failed to save. Please try again.')
		expect(self.messageType).toBe('error')
	})
})

describe('StaffPage revoke', () => {
	it('revokes the invite by id and reloads the team', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }, { status: 200, body: TEAM }])
		const self = instance()

		await self.revoke({ id: 'inv 1' })

		expect(calls).toEqual([
			{ path: '/staff-invites/inv%201/revoke', method: 'POST', body: undefined },
			{ path: '/staff', method: 'GET', body: undefined },
		])
		expect(self.messageType).toBe('success')
		expect(self.busy).toBe(false)
	})

	it('shows the save error and does not reload when the revoke fails', async () => {
		const calls = fakeFetch([{ status: 409, body: {} }])
		const self = instance()

		await self.revoke({ id: 'inv-1' })

		expect(calls).toHaveLength(1)
		expect(self.message).toBe('Failed to save. Please try again.')
		expect(self.messageType).toBe('error')
		expect(self.busy).toBe(false)
	})
})

describe('StaffPage remove', () => {
	it('removes the member from the role and reloads the team', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }, { status: 200, body: TEAM }])
		const self = instance()

		await self.remove('gamemasters', { uid: 'alice', displayName: 'Alice' })

		expect(calls).toEqual([
			{ path: '/staff/gamemasters/members/alice', method: 'DELETE', body: undefined },
			{ path: '/staff', method: 'GET', body: undefined },
		])
		expect(self.messageType).toBe('success')
		expect(self.busy).toBe(false)
	})

	it('shows why the member stays, without reloading', async () => {
		const calls = fakeFetch([{ status: 409, body: { error: 'The last game master cannot be removed' } }])
		const self = instance()

		await self.remove('gamemasters', { uid: 'alice', displayName: 'Alice' })

		expect(calls).toHaveLength(1)
		expect(self.message).toBe('The last game master cannot be removed')
		expect(self.messageType).toBe('error')
		expect(self.busy).toBe(false)
	})
})

describe('StaffPage say', () => {
	it('sets the message and its type', () => {
		const self = instance()

		self.say('Saved', 'success')
		expect([self.message, self.messageType]).toEqual(['Saved', 'success'])

		self.say('Nope', 'error')
		expect([self.message, self.messageType]).toEqual(['Nope', 'error'])
	})
})
