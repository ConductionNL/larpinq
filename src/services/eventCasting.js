/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Casting and crew (events-casting-and-crew-roles): the proposal and its
 * confirmation, which larpinq serves to game masters, and everything else
 * through the OpenRegister objects API: the event's registrations, ticket
 * types, crew roles and crew assignments, and a player's ranking of the
 * characters open for casting.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */

import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'
import { bodyOf, refusalOf } from './responseBody.js'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/** The most characters a player ranks, and the worst rank. */
export const MAX_RANK = 5

/** The most rows read per list. */
const LIST_LIMIT = 500

/**
 * The id of an OpenRegister object.
 *
 * @param {object} object The object.
 * @return {string} Its uuid, or ''.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */
export function idOf(object) {
	return String(object?.id ?? object?.['@self']?.id ?? object?.uuid ?? '')
}

/**
 * The objects of a schema that match the filters.
 *
 * @param {string} schema The schema slug.
 * @param {object} filters The field filters.
 * @return {Promise<Array<object>|null>} The objects, or null when they cannot be read.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */
export async function listObjects(schema, filters) {
	const query = new URLSearchParams({ ...filters, _limit: String(LIST_LIMIT) })
	const response = await fetch(generateUrl(`${OBJECTS}/${schema}`) + `?${query}`, {
		headers: jsonHeaders(),
	})
	if (!response.ok) {
		return null
	}
	const body = await bodyOf(response)
	return Array.isArray(body.results) ? body.results : []
}

/**
 * Write one object: create it, or update it when it has an id.
 *
 * @param {string} schema The schema slug.
 * @param {object} data The fields.
 * @param {string} id The uuid to update, or '' to create.
 * @return {Promise<{ok: boolean, message: string}>} The result, with the refusal's message.
 */
async function writeObject(schema, data, id = '') {
	const url =
		id === ''
			? `${OBJECTS}/${schema}`
			: `${OBJECTS}/${schema}/${encodeURIComponent(id)}`
	const response = await fetch(generateUrl(url), {
		method: id === '' ? 'POST' : 'PATCH',
		headers: jsonHeaders(),
		body: JSON.stringify(data),
	})
	if (response.ok) {
		return { ok: true, message: '' }
	}
	const { message } = await refusalOf(response)
	return { ok: false, message }
}

/**
 * Delete one object.
 *
 * @param {string} schema The schema slug.
 * @param {string} id The uuid.
 * @return {Promise<{ok: boolean, message: string}>} The result.
 */
async function deleteObject(schema, id) {
	const response = await fetch(
		generateUrl(`${OBJECTS}/${schema}/${encodeURIComponent(id)}`),
		{
			method: 'DELETE',
			headers: jsonHeaders(),
		},
	)
	if (response.ok) {
		return { ok: true, message: '' }
	}
	const { message } = await refusalOf(response)
	return { ok: false, message }
}

/**
 * The proposed casting of an event (game masters only). Writes nothing.
 *
 * @param {string} eventId The event uuid.
 * @return {Promise<object>} `{state: 'ok', pairs, unmatched, characters}`, or `{state: 'refused'|'failed'}`.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
 */
export async function fetchProposal(eventId) {
	if (!eventId) {
		return { state: 'failed' }
	}
	const response = await fetch(
		generateUrl(
			`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/casting/proposal`,
		),
		{ headers: jsonHeaders() },
	)
	if (response.status === 403) {
		return { state: 'refused' }
	}
	if (!response.ok) {
		return { state: 'failed' }
	}
	return { state: 'ok', ...(await bodyOf(response)) }
}

/**
 * Confirm the pairs the game master kept or changed. Pairs without a
 * character are left out: that player is not cast.
 *
 * @param {string} eventId The event uuid.
 * @param {Array<{registration: string, character: string}>} pairs The pairs.
 * @return {Promise<{ok: boolean, written: Array<object>, refused: Array<object>}>} The result.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
 */
export async function confirmCasting(eventId, pairs) {
	const kept = (pairs ?? [])
		.filter((pair) => pair?.registration && pair?.character)
		.map((pair) => ({
			registration: String(pair.registration),
			character: String(pair.character),
		}))
	const response = await fetch(
		generateUrl(
			`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/casting/confirm`,
		),
		{
			method: 'POST',
			headers: jsonHeaders(),
			body: JSON.stringify({ pairs: kept }),
		},
	)
	if (!response.ok) {
		return { ok: false, written: [], refused: [] }
	}
	const body = await bodyOf(response)
	const written = Array.isArray(body.written) ? body.written : []
	const refused = Array.isArray(body.refused) ? body.refused : []
	return { ok: refused.length === 0, written, refused }
}

/**
 * How a registration is named on the casting tab: its player account, else
 * the account that signed it up, else its id.
 *
 * @param {object} registration The registration.
 * @return {string} The name.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */
export function registrationLabel(registration) {
	return String(
		registration?.playerUid || registration?.submitterUid || idOf(registration),
	)
}

/**
 * The registrations that may fill a crew or NPC role: accepted, with a ticket
 * type of role `crew` or `npc`, and not yet assigned to the role.
 *
 * @param {Array<object>} registrations The event's registrations.
 * @param {Array<object>} ticketTypes The event's ticket types.
 * @param {Array<object>} assignments The role's assignments.
 * @return {Array<object>} The registrations.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-volunteers-fill-crew-and-npc-roles-req-ecc-004
 */
export function volunteersFor(registrations, ticketTypes, assignments) {
	const volunteerTickets = new Set(
		(ticketTypes ?? [])
			.filter((ticket) => ticket?.role === 'crew' || ticket?.role === 'npc')
			.map(idOf),
	)
	const assigned = new Set(
		(assignments ?? []).map((assignment) =>
			String(assignment?.registration ?? ''),
		),
	)
	return (registrations ?? []).filter(
		(registration) =>
			registration?.status === 'accepted'
			&& volunteerTickets.has(String(registration?.ticketType ?? ''))
			&& !assigned.has(idOf(registration)),
	)
}

/**
 * The event's crew and NPC roles, each with its assignments and how many
 * places are filled and open.
 *
 * @param {Array<object>} roles The roles.
 * @param {Array<object>} assignments The assignments of those roles.
 * @return {Array<object>} `{id, name, kind, places, filled, open, assignments}` per role.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-volunteers-fill-crew-and-npc-roles-req-ecc-004
 */
export function crewPlaces(roles, assignments) {
	return (roles ?? []).map((role) => {
		const id = idOf(role)
		const own = (assignments ?? []).filter(
			(assignment) => String(assignment?.crewRole ?? '') === id,
		)
		const places = Math.max(0, Number(role?.places) || 0)
		return {
			id,
			name: String(role?.name ?? ''),
			kind: String(role?.kind ?? ''),
			places,
			filled: own.length,
			open: Math.max(0, places - own.length),
			assignments: own,
		}
	})
}

/**
 * Everything the casting tab of an event shows to a game master.
 *
 * @param {string} eventId The event uuid.
 * @return {Promise<object>} `{state, proposal, registrations, ticketTypes, roles, assignments}`.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */
export async function fetchEventCasting(eventId) {
	const proposal = await fetchProposal(eventId)
	if (proposal.state !== 'ok') {
		return { state: proposal.state }
	}
	const [registrations, ticketTypes, roles] = await Promise.all([
		listObjects('larping_registration', { event: eventId }),
		listObjects('larping_ticket_type', { event: eventId }),
		listObjects('larping_crew_role', { event: eventId }),
	])
	if (registrations === null || ticketTypes === null || roles === null) {
		return { state: 'failed' }
	}
	const assignments = []
	for (const role of roles) {
		const own = await listObjects('larping_crew_assignment', {
			crewRole: idOf(role),
		})
		assignments.push(...(own ?? []))
	}
	return { state: 'ok', proposal, registrations, ticketTypes, roles, assignments }
}

/**
 * Assign a registration to a crew or NPC role.
 *
 * @param {string} roleId The crew role uuid.
 * @param {string} registrationId The registration uuid.
 * @return {Promise<{ok: boolean, message: string}>} The result.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-volunteers-fill-crew-and-npc-roles-req-ecc-004
 */
export function assignCrew(roleId, registrationId) {
	return writeObject('larping_crew_assignment', {
		crewRole: roleId,
		registration: registrationId,
	})
}

/**
 * Whether ranking has closed: the event's casting deadline lies in the past.
 *
 * @param {object} event The event.
 * @param {Date} now The moment.
 * @return {boolean} True when it has closed.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
export function rankingClosed(event, now = new Date()) {
	const deadline = Date.parse(String(event?.castingDeadline ?? ''))
	return Number.isFinite(deadline) && now.getTime() >= deadline
}

/**
 * Whether a ranking gives each rank to one character at most, and only ranks 1 to 5.
 *
 * @param {object} ranks Rank per character id; 0 or '' for not ranked.
 * @return {boolean} True when it can be saved.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
export function rankingIsValid(ranks) {
	const given = Object.values(ranks ?? {})
		.map(Number)
		.filter((rank) => rank !== 0)
	return (
		given.every(
			(rank) => Number.isInteger(rank) && rank >= 1 && rank <= MAX_RANK,
		) && new Set(given).size === given.length
	)
}

/**
 * The writes that turn the stored preferences into the wanted ranking.
 *
 * @param {Array<object>} stored The stored preferences of the registration.
 * @param {object} ranks The wanted rank per character id; 0 or '' for not ranked.
 * @return {{create: Array<object>, update: Array<object>, remove: Array<string>}} The writes.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
export function rankingChanges(stored, ranks) {
	const byCharacter = new Map(
		(stored ?? []).map((preference) => [
			String(preference?.character ?? ''),
			preference,
		]),
	)
	const create = []
	const update = []
	const remove = []
	for (const [character, value] of Object.entries(ranks ?? {})) {
		const rank = Number(value) || 0
		const existing = byCharacter.get(character)
		if (rank === 0) {
			if (existing) {
				remove.push(idOf(existing))
			}
		} else if (!existing) {
			create.push({ character, rank })
		} else if (Number(existing.rank) !== rank) {
			update.push({ id: idOf(existing), rank })
		}
	}
	return { create, update, remove }
}

/**
 * What the casting tab of a registration shows its player: the event, the
 * characters open for casting on it, the player's ranking and their crew roles.
 *
 * @param {string} registrationId The registration uuid.
 * @return {Promise<object>} `{state, registration, event, characters, preferences, crew}`.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
export async function fetchRegistrationCasting(registrationId) {
	if (!registrationId) {
		return { state: 'failed' }
	}
	const response = await fetch(
		generateUrl(
			`${OBJECTS}/larping_registration/${encodeURIComponent(registrationId)}`,
		),
		{ headers: jsonHeaders() },
	)
	if (!response.ok) {
		return { state: 'failed' }
	}
	const registration = await bodyOf(response)
	const eventId = String(registration.event ?? '')
	const [eventResponse, characters, preferences, assignments] = await Promise.all([
		fetch(
			generateUrl(`${OBJECTS}/larping_event/${encodeURIComponent(eventId)}`),
			{ headers: jsonHeaders() },
		),
		listObjects('character', { castingEvent: eventId }),
		listObjects('larping_casting_preference', { registration: registrationId }),
		listObjects('larping_crew_assignment', { registration: registrationId }),
	])
	if (
		!eventResponse.ok
		|| characters === null
		|| preferences === null
		|| assignments === null
	) {
		return { state: 'failed' }
	}
	const event = await bodyOf(eventResponse)
	const roles =
		eventId === ''
			? []
			: ((await listObjects('larping_crew_role', { event: eventId })) ?? [])
	const assignedRoles = new Set(
		assignments.map((assignment) => String(assignment?.crewRole ?? '')),
	)
	return {
		state: 'ok',
		registration,
		event,
		characters,
		preferences,
		crew: roles.filter((role) => assignedRoles.has(idOf(role))),
	}
}

/**
 * Save a player's ranking: create, update and delete preferences. Stops at
 * the first refusal and hands back its message.
 *
 * @param {object} registration The registration (with its id and event).
 * @param {Array<object>} stored The stored preferences.
 * @param {object} ranks The wanted rank per character id.
 * @return {Promise<{ok: boolean, message: string}>} The result.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
export async function saveRanking(registration, stored, ranks) {
	const { create, update, remove } = rankingChanges(stored, ranks)
	const event = String(registration?.event ?? '')
	const registrationId = idOf(registration)
	// Removals first, so a rank freed by one character can go to another.
	const writes = [
		...remove.map((id) => () => deleteObject('larping_casting_preference', id)),
		...update.map(
			({ id, rank }) =>
				() =>
					writeObject('larping_casting_preference', { rank }, id),
		),
		...create.map(
			({ character, rank }) =>
				() =>
					writeObject('larping_casting_preference', {
						event,
						registration: registrationId,
						character,
						rank,
					}),
		),
	]
	for (const write of writes) {
		const result = await write()
		if (!result.ok) {
			return result
		}
	}
	return { ok: true, message: '' }
}
