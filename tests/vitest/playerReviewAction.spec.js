/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/playerReviewAction.js: what the Mark reviewed
 * bulk action reports, and when it reloads the list.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const calls = []

vi.mock('@nextcloud/dialogs', () => ({
	showError: (text) => calls.push(['error', text]),
	showSuccess: (text) => calls.push(['success', text]),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, key) => String(vars[key])),
}))

const outcome = { reviewed: [], refused: [] }

vi.mock('../../src/services/playerReview.js', () => ({
	markPlayersReviewed: (ids) => {
		calls.push(['write', ids])
		return Promise.resolve(outcome)
	},
}))

const { markPlayersReviewedAction } =
	await import('../../src/services/playerReviewAction.js')

beforeEach(() => {
	calls.length = 0
	globalThis.window = { location: { reload: () => calls.push(['reload']) } }
})

afterEach(() => {
	delete globalThis.window
})

/**
 * Run the action with a write outcome.
 *
 * @param {object} scope The bulk action scope.
 * @param {object} result The write outcome.
 * @return {Promise<Array>} What happened, in order.
 */
async function run(scope, result) {
	outcome.reviewed = result.reviewed
	outcome.refused = result.refused
	await markPlayersReviewedAction(scope)
	return [...calls]
}

describe('markPlayersReviewedAction', () => {
	it('does nothing without a selection', async () => {
		expect(await run({}, { reviewed: [], refused: [] })).toStrictEqual([])
		expect(
			await run({ selectedIds: [] }, { reviewed: [], refused: [] }),
		).toStrictEqual([])
		expect(
			await run({ selectedIds: 'p-1' }, { reviewed: [], refused: [] }),
		).toStrictEqual([])
	})

	it('reports the reviewed players and reloads', async () => {
		expect(
			await run(
				{ selectedIds: ['p-1', 'p-2'] },
				{ reviewed: ['p-1', 'p-2'], refused: [] },
			),
		).toStrictEqual([
			['write', ['p-1', 'p-2']],
			['success', 'Players marked reviewed: 2'],
			['reload'],
		])
	})

	it('reports the refused players and does not reload when none was reviewed', async () => {
		expect(
			await run({ selectedIds: ['p-1'] }, { reviewed: [], refused: ['p-1'] }),
		).toStrictEqual([
			['write', ['p-1']],
			[
				'error',
				'Players not marked reviewed: 1. Only game masters can review players.',
			],
		])
	})

	it('reports both and reloads when some were reviewed', async () => {
		expect(
			await run(
				{ selectedIds: ['p-1', 'p-2', 'p-3'] },
				{ reviewed: ['p-1'], refused: ['p-2', 'p-3'] },
			),
		).toStrictEqual([
			['write', ['p-1', 'p-2', 'p-3']],
			['success', 'Players marked reviewed: 1'],
			[
				'error',
				'Players not marked reviewed: 2. Only game masters can review players.',
			],
			['reload'],
		])
	})

	it('reports nothing when the write touched no one', async () => {
		expect(
			await run({ selectedIds: ['p-1'] }, { reviewed: [], refused: [] }),
		).toStrictEqual([['write', ['p-1']]])
	})
})
