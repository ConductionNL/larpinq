/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Larpinq stores money in cents (price lines, and the part of a registration
 * paid from the player's credit). A `cents` column formatter shows such a
 * value as a currency amount, EUR unless the column's `formatterOptions`
 * name another ISO-4217 code. Empty and non-numeric values show nothing, so
 * a registration without credit leaves the cell blank.
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */

/**
 * The text of a cents cell, such as "€ 85,00" for 8500.
 *
 * @param {unknown} value The stored amount in cents.
 * @param {object} [_row] The full row (unused).
 * @param {object} [_property] The schema property (unused).
 * @param {{currency?: string}} [options] The column's `formatterOptions`.
 * @return {string} The formatted amount, or '' when there is none.
 * @spec openspec/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */
export function centsAmount(value, _row, _property, options) {
	if (value === null || value === undefined || value === '' || typeof value === 'boolean') {
		return ''
	}
	const cents = Number(value)
	if (!Number.isFinite(cents)) {
		return ''
	}
	const code = typeof options?.currency === 'string' && /^[A-Z]{3}$/.test(options.currency) ? options.currency : 'EUR'
	return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(cents / 100)
}
