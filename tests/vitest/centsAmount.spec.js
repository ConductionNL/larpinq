/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */
import { describe, expect, it } from 'vitest'
import { centsAmount } from '../../src/services/centsAmount.js'

const euro = (amount, currency = 'EUR') => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)

describe('centsAmount', () => {
	it('shows cents as euros', () => {
		expect(centsAmount(8500)).toBe(euro(85))
		expect(centsAmount('1000')).toBe(euro(10))
		expect(centsAmount(0)).toBe(euro(0))
	})

	it('takes the currency from the column options', () => {
		expect(centsAmount(1250, {}, {}, { currency: 'GBP' })).toBe(euro(12.5, 'GBP'))
		expect(centsAmount(1250, {}, {}, { currency: 'nope' })).toBe(euro(12.5))
	})

	it('shows nothing for an empty or unreadable value', () => {
		for (const value of [null, undefined, '', 'abc', true]) {
			expect(centsAmount(value)).toBe('')
		}
	})
})
