/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/components/cells/cellValue.js (communication-help-desk,
 * REQ-CHD-002): what every index-column cell widget receives besides its raw
 * value, and how a cell prefers the column formatter's text.
 */

import { describe, expect, it, vi } from 'vitest'
import { cellContextProps, formattedText } from '../../src/components/cells/cellValue.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text) => text,
}))

describe('formattedText', () => {
	it('is null when the column has no formatter text', () => {
		expect(formattedText(null)).toBeNull()
		expect(formattedText(undefined)).toBeNull()
	})

	it('reads an empty formatter text as none', () => {
		expect(formattedText('')).toBeNull()
	})

	it('keeps a formatter text as it is', () => {
		expect(formattedText('14 nov. 2026')).toBe('14 nov. 2026')
		expect(formattedText('  spaced  ')).toBe('  spaced  ')
	})

	it('writes a formatted number as text, zero included', () => {
		expect(formattedText(42)).toBe('42')
		expect(formattedText(0)).toBe('0')
		expect(formattedText(-1.5)).toBe('-1.5')
	})
})

describe('cellContextProps', () => {
	it('declares the formatter text and the row, nothing else', () => {
		expect(Object.keys(cellContextProps)).toEqual(['formatted', 'row'])
	})

	it('takes the formatter text as a string or a number, absent by default', () => {
		expect(cellContextProps.formatted.type).toEqual([String, Number])
		expect(cellContextProps.formatted.default).toBeNull()
	})

	it('takes the whole row as an object, absent by default', () => {
		expect(cellContextProps.row.type).toBe(Object)
		expect(cellContextProps.row.default).toBeNull()
	})

	it('are the props the age cell takes after its value', async () => {
		const { default: AgeCell } = await import('../../src/components/cells/AgeCell.vue')
		expect(AgeCell.props.formatted).toBe(cellContextProps.formatted)
		expect(AgeCell.props.row).toBe(cellContextProps.row)
		expect(Object.keys(AgeCell.props)).toEqual(['value', 'formatted', 'row', 'olderThanDays'])
	})
})
