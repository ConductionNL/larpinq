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

	const response = await fetch(
		generateUrl(`/apps/larpinq/api/events/${encodeURIComponent(eventId)}/feedback`),
		{ headers: jsonHeaders() },
	)
	if (response.status === 403 || response.status === 401) {
		return { state: 'refused' }
	}
	if (!response.ok) {
		return { state: 'failed' }
	}

	const body = await response.json()
	return {
		state: 'ok',
		summary: {
			formId: Number(body?.formId) || 0,
			opensAt: body?.opensAt || '',
			closesAt: body?.closesAt || '',
			invited: Number(body?.invited) || 0,
			answered: typeof body?.answered === 'number' ? body.answered : null,
			notAnswered: Array.isArray(body?.notAnswered) ? body.notAnswered : null,
			resultsLink: body?.resultsLink || '',
		},
	}
}
