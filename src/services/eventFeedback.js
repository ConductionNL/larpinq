/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Feedback tab on the event page (events-post-event-survey): who was
 * invited to give feedback and who answered, served by larpinq to game
 * masters only. The answers themselves stay in Nextcloud Forms.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */

import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'

/**
 * The statuses that mean the caller may not see the feedback.
 */
const REFUSED = [401, 403]

/**
 * Fetch the feedback summary of an event.
 *
 * @param {string} eventId The event UUID.
 * @return {Promise<{state: 'ok', summary: object}|{state: 'refused'}|{state: 'failed'}>} The summary, or why there is none.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */
export async function fetchEventFeedback(eventId) {
	if (!eventId) {
		return { state: 'failed' }
	}
	const url = generateUrl(`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/feedback`)
	return outcomeOf(await fetch(url, { headers: jsonHeaders() }))
}

/**
 * The page's state from the server's answer: the summary, refused (401, 403) or failed.
 *
 * @param {Response} response The answer.
 * @return {Promise<{state: 'ok', summary: object}|{state: 'refused'}|{state: 'failed'}>}
 */
async function outcomeOf(response) {
	if (REFUSED.includes(response.status)) {
		return { state: 'refused' }
	}
	return response.ok
		? { state: 'ok', summary: summaryOf(await response.json()) }
		: { state: 'failed' }
}

/**
 * The summary the page shows, from the server's answer.
 *
 * `answered` and `notAnswered` stay null when Forms cannot tell who answered.
 *
 * @param {object|null} body The answer.
 * @return {object} The summary.
 */
function summaryOf(body) {
	const answer = body ?? {}
	return {
		formId: numberOrZero(answer.formId),
		opensAt: textOrEmpty(answer.opensAt),
		closesAt: textOrEmpty(answer.closesAt),
		invited: numberOrZero(answer.invited),
		answered: typeof answer.answered === 'number' ? answer.answered : null,
		notAnswered: Array.isArray(answer.notAnswered) ? answer.notAnswered : null,
		resultsLink: textOrEmpty(answer.resultsLink),
	}
}

/**
 * A number, or 0 when the value is not one.
 *
 * @param {string|number|null|undefined} value The value.
 * @return {number}
 */
function numberOrZero(value) {
	return Number(value) || 0
}

/**
 * The value, or '' when it is empty.
 *
 * @param {string|number|null|undefined} value The value.
 * @return {string|number}
 */
function textOrEmpty(value) {
	return value || ''
}
