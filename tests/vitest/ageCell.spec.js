/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/components/cells/AgeCell.vue (communication-help-desk,
 * REQ-CHD-002): a list date with a marker once it lies more than the given
 * number of days in the past. This suite compiles `<script>` only, so the
 * cell's logic is exercised directly.
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text) => `[${app}] ${text}`,
}))

const { default: AgeCell } = await import('../../src/components/cells/AgeCell.vue')

const NOW = Date.parse('2026-11-14T12:00:00Z')
const DAY = 24 * 60 * 60 * 1000

/**
 * The cell's options bound as `this`, with its methods available.
 *
 * @param {object} props The props.
 * @return {object}
 */
function cell(props) {
	const self = { value: null, formatted: null, row: null, olderThanDays: 0, ...props }
	for (const [name, fn] of Object.entries(AgeCell.methods)) {
		self[name] = fn.bind(self)
	}
	return self
}

describe('AgeCell', () => {
	it('takes the column value, its formatted text, the row and the age limit', () => {
		expect(Object.keys(AgeCell.props)).toEqual(['value', 'formatted', 'row', 'olderThanDays'])
		expect(AgeCell.props.olderThanDays.default).toBe(0)
	})

	it('marks a date more than the limit in the past, and not one within it', () => {
		const self = cell({})
		expect(self.isOlderThan(new Date(NOW - 4 * DAY).toISOString(), 3, NOW)).toBe(true)
		expect(self.isOlderThan(new Date(NOW - 3 * DAY - 1000).toISOString(), 3, NOW)).toBe(true)
		expect(self.isOlderThan(new Date(NOW - 3 * DAY).toISOString(), 3, NOW)).toBe(false)
		expect(self.isOlderThan(new Date(NOW - DAY).toISOString(), 3, NOW)).toBe(false)
	})

	it('never marks without a limit or without a date', () => {
		const self = cell({})
		const old = new Date(NOW - 30 * DAY).toISOString()
		expect(self.isOlderThan(old, 0, NOW)).toBe(false)
		expect(self.isOlderThan(old, undefined, NOW)).toBe(false)
		expect(self.isOlderThan('', 3, NOW)).toBe(false)
		expect(self.isOlderThan(null, 3, NOW)).toBe(false)
		expect(self.isOlderThan('not a date', 3, NOW)).toBe(false)
		expect(self.isOlderThan({ at: old }, 3, NOW)).toBe(false)
	})

	it('shows the formatted value first, else the date, else nothing', () => {
		const shown = (props) => AgeCell.computed.shown.call(cell(props))
		expect(shown({ value: '2026-11-01T10:00:00Z', formatted: '1 Nov' })).toBe('1 Nov')
		expect(shown({ value: '2026-11-01T10:00:00Z' })).toBe(new Date('2026-11-01T10:00:00Z').toLocaleDateString())
		expect(shown({ value: '' })).toBe('')
		expect(shown({})).toBe('')
	})

	it('says the date has been waiting too long, in the app catalogue', () => {
		expect(AgeCell.computed.marker.call(cell({ olderThanDays: 3 }))).toBe('[larpinq] Waiting too long')
	})
})
