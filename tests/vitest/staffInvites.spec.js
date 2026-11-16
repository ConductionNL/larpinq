/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/staffInvites.js: the Staff page's and the invite
 * landing page's calls to larpinq, and what each answer becomes.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	createInvite,
	fetchTeam,
	inspectInvite,
	redeemInvite,
	removeMember,
	revokeInvite,
	roleLabel,
	STAFF_ROLES,
	stateLabel,
} from '../../src/services/staffInvites.js'

const CODE = '0123456789abcdef0123456789abcdef'

/**
 * Answer the next fetch with this status and body.
 *
 * @param {number} status The HTTP status.
 * @param {object|null} body The JSON body.
 * @return {Function} The fetch mock.
 */
function answer(status, body) {
	globalThis.fetch = vi.fn().mockResolvedValueOnce({
		ok: status >= 200 && status < 300,
		status,
		json: async () => body,
	})
	return globalThis.fetch
}

describe('staff invites', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('lists the game masters and the four staff roles, each with a name', () => {
		expect(STAFF_ROLES).toEqual(['gamemasters', 'larpinq-writers', 'larpinq-rules', 'larpinq-treasurers', 'larpinq-stewards'])
		expect(STAFF_ROLES.map(roleLabel)).toEqual(['Game master', 'Story writer', 'Rules marshal', 'Treasurer', 'Steward'])
		expect(roleLabel('admin')).toBe('admin')
		expect(['valid', 'expired', 'used-up', 'revoked', 'odd'].map(stateLabel)).toEqual(['Open', 'Expired', 'Used up', 'Revoked', 'odd'])
	})

	it('reads the team, and tells a refusal from a failure', async () => {
		const roles = [{ role: 'larpinq-stewards', members: [{ uid: 'joris', displayName: 'Joris' }] }]
		const fetchMock = answer(200, { roles, invites: [] })
		expect(await fetchTeam()).toEqual({ state: 'ok', roles, invites: [] })
		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff')
		expect(fetchMock.mock.calls[0][1].method).toBe('GET')

		answer(403, { error: 'Access denied' })
		expect(await fetchTeam()).toEqual({ state: 'refused' })
		answer(500, null)
		expect(await fetchTeam()).toEqual({ state: 'failed' })
	})

	it('creates an invite with the role, days and uses, and returns its link', async () => {
		const fetchMock = answer(201, { invite: { id: 'i1' }, token: CODE, link: `https://cloud.example/apps/larpinq/staff-invite/${CODE}` })
		const created = await createInvite('larpinq-stewards', 7, 3)

		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff-invites')
		expect(fetchMock.mock.calls[0][1].method).toBe('POST')
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ role: 'larpinq-stewards', days: 7, maxUses: 3 })
		expect(created).toEqual({ ok: true, link: `https://cloud.example/apps/larpinq/staff-invite/${CODE}`, invite: { id: 'i1' } })
	})

	it('passes the reason of a refused invite on', async () => {
		answer(400, { error: 'Unknown role' })
		expect(await createInvite('admin', 7, 1)).toEqual({ ok: false, error: 'Unknown role' })
		answer(500, null)
		expect(await createInvite('larpinq-rules', 7, 1)).toEqual({ ok: false, error: 'Failed to save. Please try again.' })
	})

	it('revokes an invite and removes a member at their own urls', async () => {
		let fetchMock = answer(200, { id: 'i/1' })
		expect(await revokeInvite('i/1')).toBe(true)
		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff-invites/i%2F1/revoke')

		fetchMock = answer(200, { removed: true })
		expect(await removeMember('larpinq-treasurers', 'lotte')).toEqual({ ok: true })
		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff/larpinq-treasurers/members/lotte')
		expect(fetchMock.mock.calls[0][1].method).toBe('DELETE')

		answer(400, { error: 'You cannot remove yourself from the game masters' })
		expect(await removeMember('gamemasters', 'me')).toEqual({ ok: false, error: 'You cannot remove yourself from the game masters' })
	})

	it('opens a link: its role and state, or that it is unknown', async () => {
		const fetchMock = answer(200, { role: 'larpinq-stewards', state: 'valid' })
		expect(await inspectInvite(CODE)).toEqual({ role: 'larpinq-stewards', state: 'valid' })
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ token: CODE })

		answer(404, { error: 'No such invite' })
		expect(await inspectInvite(CODE)).toEqual({ state: 'unknown' })

		globalThis.fetch = vi.fn()
		expect(await inspectInvite('')).toEqual({ state: 'unknown' })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})

	it('redeems a link: joined, already a member, or why not', async () => {
		answer(200, { role: 'larpinq-stewards', joined: true })
		expect(await redeemInvite(CODE)).toEqual({ state: 'joined', role: 'larpinq-stewards' })
		answer(200, { role: 'larpinq-stewards', joined: false })
		expect(await redeemInvite(CODE)).toEqual({ state: 'member', role: 'larpinq-stewards' })
		answer(410, { error: 'This invite is used up', state: 'used-up' })
		expect(await redeemInvite(CODE)).toEqual({ state: 'used-up' })
		answer(404, null)
		expect(await redeemInvite(CODE)).toEqual({ state: 'unknown' })
		answer(429, null)
		expect(await redeemInvite(CODE)).toEqual({ state: 'failed' })
	})
})

describe('staff invites, the edges of each answer', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('sends JSON headers on every call and no body on a read', async () => {
		const fetchMock = answer(200, { roles: [], invites: [] })
		await fetchTeam()

		const init = fetchMock.mock.calls[0][1]
		expect(init.body).toBeUndefined()
		expect(init.headers.Accept).toBe('application/json')
		expect(init.headers['Content-Type']).toBe('application/json')
		expect(init.headers).toHaveProperty('requesttoken')
	})

	it('treats a signed-out read of the team as a refusal', async () => {
		answer(401, { error: 'Not authenticated' })
		expect(await fetchTeam()).toEqual({ state: 'refused' })
	})

	it('reads a team answer without lists as an empty team', async () => {
		answer(200, { roles: 'none', invites: null })
		expect(await fetchTeam()).toEqual({ state: 'ok', roles: [], invites: [] })

		answer(200, null)
		expect(await fetchTeam()).toEqual({ state: 'ok', roles: [], invites: [] })
	})

	it('survives an answer that is not JSON', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: false,
			status: 502,
			json: async () => {
				throw new SyntaxError('Unexpected token <')
			},
		})
		expect(await createInvite('larpinq-writers', 7, 1)).toEqual({ ok: false, error: 'Failed to save. Please try again.' })
	})

	it('returns an empty link and invite when a created invite lacks them', async () => {
		answer(201, {})
		expect(await createInvite('larpinq-writers', 1, 1)).toEqual({ ok: true, link: '', invite: {} })
	})

	it('reports a failed revoke as not revoked', async () => {
		const fetchMock = answer(404, { error: 'Invite not found' })
		expect(await revokeInvite('gone')).toBe(false)
		expect(fetchMock.mock.calls[0][1].method).toBe('POST')
		expect(fetchMock.mock.calls[0][1].body).toBeUndefined()
	})

	it('gives the general reason when a removal fails without one', async () => {
		answer(500, null)
		expect(await removeMember('larpinq-rules', 'anna')).toEqual({ ok: false, error: 'Failed to save. Please try again.' })
	})

	it('escapes the role and member in the removal url', async () => {
		const fetchMock = answer(200, { removed: true })
		await removeMember('larpinq-rules', 'anna maria/2')
		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff/larpinq-rules/members/anna%20maria%2F2')
	})

	it('tells a failed opening from an unknown link', async () => {
		const fetchMock = answer(500, null)
		expect(await inspectInvite(CODE)).toEqual({ state: 'failed' })
		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff-invites/inspect')
		expect(fetchMock.mock.calls[0][1].method).toBe('POST')

		answer(200, {})
		expect(await inspectInvite(CODE)).toEqual({ state: '', role: '' })
	})

	it('reads a gone link without a state as expired', async () => {
		answer(410, { error: 'This invite adds nobody' })
		expect(await redeemInvite(CODE)).toEqual({ state: 'expired' })
		answer(410, null)
		expect(await redeemInvite(CODE)).toEqual({ state: 'expired' })
	})

	it('redeems at its own url with the token as the body', async () => {
		const fetchMock = answer(200, { joined: true })
		expect(await redeemInvite(CODE)).toEqual({ state: 'joined', role: '' })
		expect(fetchMock.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/staff-invites/redeem')
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ token: CODE })
	})

	it('names each staff role apart from the others', () => {
		const labels = STAFF_ROLES.map(roleLabel)
		expect(new Set(labels).size).toBe(STAFF_ROLES.length)
		expect(labels).not.toContain('')
		expect(roleLabel('')).toBe('')
		expect(stateLabel('')).toBe('')
	})
})
