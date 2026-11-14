/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Staff invites and the Staff page (admin-staff-roles-and-invites): the team,
 * creating and revoking invite links, removing a member, and opening and
 * redeeming a link. Larpinq serves all of it behind its own checks.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'

/**
 * The roles an invite may name, in the order the Staff page lists them.
 *
 * @type {Array<string>}
 */
export const STAFF_ROLES = [
	'gamemasters',
	'larpinq-writers',
	'larpinq-rules',
	'larpinq-treasurers',
	'larpinq-stewards',
]

/**
 * The name of a role, as people read it.
 *
 * @param {string} role The role group.
 * @return {string} The name, or the group id for an unknown role.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-four-staff-roles-exist-next-to-the-game-masters-req-asr-001
 */
export function roleLabel(role) {
	switch (role) {
	case 'gamemasters':
		return t('larpinq', 'Game master')
	case 'larpinq-writers':
		return t('larpinq', 'Story writer')
	case 'larpinq-rules':
		return t('larpinq', 'Rules marshal')
	case 'larpinq-treasurers':
		return t('larpinq', 'Treasurer')
	case 'larpinq-stewards':
		return t('larpinq', 'Steward')
	default:
		return role
	}
}

/**
 * What an invite's state means, as people read it.
 *
 * @param {string} state valid, expired, used-up or revoked.
 * @return {string} The text.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
 */
export function stateLabel(state) {
	switch (state) {
	case 'valid':
		return t('larpinq', 'Open')
	case 'expired':
		return t('larpinq', 'Expired')
	case 'used-up':
		return t('larpinq', 'Used up')
	case 'revoked':
		return t('larpinq', 'Revoked')
	default:
		return state
	}
}

/**
 * A request to larpinq's staff endpoints.
 *
 * @param {string} path The path under /apps/larpinq/api.
 * @param {string} method The HTTP method.
 * @param {object|undefined} body The JSON body.
 * @return {Promise<{status: number, ok: boolean, body: object|null}>} The answer.
 */
async function call(path, method = 'GET', body = undefined) {
	const response = await fetch(generateUrl(`/apps/larpinq/api${path}`), {
		method,
		headers: jsonHeaders(),
		body: body === undefined ? undefined : JSON.stringify(body),
	})
	let parsed
	try {
		parsed = await response.json()
	} catch {
		parsed = null
	}
	return { status: response.status, ok: response.ok, body: parsed }
}

/**
 * The members of each role and every invite with its state.
 *
 * @return {Promise<{state: 'ok', roles: Array<object>, invites: Array<object>}|{state: 'refused'}|{state: 'failed'}>} The team, or why there is none.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
 */
export async function fetchTeam() {
	const answer = await call('/staff')
	if (answer.status === 403 || answer.status === 401) {
		return { state: 'refused' }
	}
	if (!answer.ok) {
		return { state: 'failed' }
	}
	return {
		state: 'ok',
		roles: Array.isArray(answer.body?.roles) ? answer.body.roles : [],
		invites: Array.isArray(answer.body?.invites) ? answer.body.invites : [],
	}
}

/**
 * Create an invite link.
 *
 * @param {string} role The role group.
 * @param {number} days Days the link is valid.
 * @param {number} maxUses How many people may join with it.
 * @return {Promise<{ok: true, link: string, invite: object}|{ok: false, error: string}>} The link, shown once, or the reason.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
 */
export async function createInvite(role, days, maxUses) {
	const answer = await call('/staff-invites', 'POST', { role, days, maxUses })
	if (!answer.ok) {
		return { ok: false, error: answer.body?.error || t('larpinq', 'Failed to save. Please try again.') }
	}
	return { ok: true, link: String(answer.body?.link || ''), invite: answer.body?.invite || {} }
}

/**
 * Revoke an invite.
 *
 * @param {string} id The invite id.
 * @return {Promise<boolean>} Whether it is revoked.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
 */
export async function revokeInvite(id) {
	return (await call(`/staff-invites/${encodeURIComponent(id)}/revoke`, 'POST')).ok
}

/**
 * Remove a member from a role.
 *
 * @param {string} role The role group.
 * @param {string} uid The member.
 * @return {Promise<{ok: true}|{ok: false, error: string}>} Done, or the reason.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
 */
export async function removeMember(role, uid) {
	const answer = await call(`/staff/${encodeURIComponent(role)}/members/${encodeURIComponent(uid)}`, 'DELETE')
	if (!answer.ok) {
		return { ok: false, error: answer.body?.error || t('larpinq', 'Failed to save. Please try again.') }
	}
	return { ok: true }
}

/**
 * The role and state of the invite a token opens.
 *
 * @param {string} token The token from the link.
 * @return {Promise<{state: string, role: string}|{state: 'unknown'}|{state: 'failed'}>} The invite, or why there is none.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
 */
export async function inspectInvite(token) {
	if (!token) {
		return { state: 'unknown' }
	}
	const answer = await call('/staff-invites/inspect', 'POST', { token })
	if (answer.status === 404) {
		return { state: 'unknown' }
	}
	if (!answer.ok) {
		return { state: 'failed' }
	}
	return { state: String(answer.body?.state || ''), role: String(answer.body?.role || '') }
}

/**
 * Join the role of the invite a token opens.
 *
 * @param {string} token The token from the link.
 * @return {Promise<{state: 'joined'|'member', role: string}|{state: string}>} Joined, already a member, or why not.
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
 */
export async function redeemInvite(token) {
	const answer = await call('/staff-invites/redeem', 'POST', { token })
	if (answer.status === 404) {
		return { state: 'unknown' }
	}
	if (answer.status === 410) {
		return { state: String(answer.body?.state || 'expired') }
	}
	if (!answer.ok) {
		return { state: 'failed' }
	}
	return { state: answer.body?.joined ? 'joined' : 'member', role: String(answer.body?.role || '') }
}
