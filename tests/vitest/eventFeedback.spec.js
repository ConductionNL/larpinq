/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/eventFeedback.js: the Feedback tab's fetch, its
 * refusal and the count-only answer.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchEventFeedback } from '../../src/services/eventFeedback.js'

describe('fetchEventFeedback', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('reads 1 of 2 answered and who has not, with the results link', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => ({
				formId: 42,
				opensAt: '2025-08-17T16:00:00+00:00',
				closesAt: '2025-09-07T16:00:00+00:00',
				invited: 2,
				answered: 1,
				notAnswered: [{ uid: 'sanne', character: 'Brannoc' }],
				resultsLink: 'https://cloud.example/apps/forms/abc/results',
			}),
		})

		const feedback = await fetchEventFeedback('e1')

		expect(globalThis.fetch.mock.calls[0][0]).toBe('/index.php/apps/larpinq/api/events/e1/feedback')
		expect(feedback).toEqual({
			state: 'ok',
			summary: {
				formId: 42,
				opensAt: '2025-08-17T16:00:00+00:00',
				closesAt: '2025-09-07T16:00:00+00:00',
				invited: 2,
				answered: 1,
				notAnswered: [{ uid: 'sanne', character: 'Brannoc' }],
				resultsLink: 'https://cloud.example/apps/forms/abc/results',
			},
		})
	})

	it('keeps an unknown answer count unknown', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			status: 200,
			json: async () => ({ formId: 42, invited: 2, answered: null, notAnswered: null }),
		})
		const { summary } = await fetchEventFeedback('e1')
		expect(summary.answered).toBeNull()
		expect(summary.notAnswered).toBeNull()
		expect(summary.invited).toBe(2)
	})

	it('says so to someone who is not a game master, and fails without an id', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 403 })
		expect(await fetchEventFeedback('e1')).toEqual({ state: 'refused' })
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 500 })
		expect(await fetchEventFeedback('e1')).toEqual({ state: 'failed' })
		globalThis.fetch = vi.fn()
		expect(await fetchEventFeedback('')).toEqual({ state: 'failed' })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})
