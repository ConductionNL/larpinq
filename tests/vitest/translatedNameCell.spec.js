/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/components/cells/TranslatedNameCell.vue
 * (larpinq-adopt-or-abstractions, Phase 3): the list cell shows its value and
 * hands the row to the library's CnTranslatedBadge, which renders only for a
 * row OpenRegister served in another language than its source. This suite
 * compiles `<script>` only, so the cell's logic is exercised directly.
 */

import { describe, expect, it, vi } from 'vitest'

const CnTranslatedBadge = { name: 'CnTranslatedBadge' }
vi.mock('@conduction/nextcloud-vue', () => ({ CnTranslatedBadge }))

const { default: TranslatedNameCell } = await import('../../src/components/cells/TranslatedNameCell.vue')

/**
 * The `shown` computed for a set of props.
 *
 * @param {object} props The props.
 * @return {string}
 */
function shown(props) {
	return TranslatedNameCell.computed.shown.call({ value: null, formatted: null, row: null, ...props })
}

describe('TranslatedNameCell', () => {
	it('uses the library badge and takes the row it reads _translationMeta from', () => {
		expect(TranslatedNameCell.components.CnTranslatedBadge).toBe(CnTranslatedBadge)
		expect(Object.keys(TranslatedNameCell.props)).toEqual(['value', 'formatted', 'row'])
		expect(TranslatedNameCell.props.row.type).toBe(Object)
	})

	it('shows the formatted value first, then the raw one', () => {
		expect(shown({ value: 'raw', formatted: 'Shown' })).toBe('Shown')
		expect(shown({ value: 'Drakenlus', formatted: '' })).toBe('Drakenlus')
		expect(shown({ value: 7 })).toBe('7')
		expect(shown({ value: false })).toBe('false')
	})

	it('never prints an object or a missing value', () => {
		expect(shown({ value: { nl: 'Drakenlus' } })).toBe('')
		expect(shown({ value: ['a'] })).toBe('')
		expect(shown({ value: undefined })).toBe('')
		expect(shown({})).toBe('')
	})
})
