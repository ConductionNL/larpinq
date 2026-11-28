// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// What every index-column cell widget receives besides its raw value, and how
// it prefers the column formatter's text (TranslatedNameCell, AgeCell).

/**
 * The props a cell widget takes after `value`: the formatter's text and the row.
 */
export const cellContextProps = {
	/** The value as the column's formatter rendered it, when there is one. */
	formatted: {
		type: [String, Number],
		default: null,
	},

	/** The whole row. */
	row: {
		type: Object,
		default: null,
	},
}

/**
 * The formatter's text of a cell, or null when the column has none.
 *
 * @param {string|number|null|undefined} formatted The formatted value.
 * @return {string|null}
 */
export function formattedText(formatted) {
	if (formatted === null || formatted === undefined || formatted === '') {
		return null
	}
	return String(formatted)
}
