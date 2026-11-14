/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/userLanguage.js
 * (larpinq-adopt-or-abstractions, Phase 3).
 */

import { describe, expect, it, vi } from 'vitest'

const language = { value: 'nl' }
vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => {
		if (language.value instanceof Error) {
			throw language.value
		}
		return language.value
	},
}))

const { baseLanguage, userLanguage } = await import('../../src/services/userLanguage.js')

describe('baseLanguage', () => {
	it.each([
		['de_DE', 'de'],
		['en-GB', 'en'],
		['NL', 'nl'],
		['ast', 'ast'],
		['pt_BR', 'pt'],
	])('strips the region of %s', (input, expected) => {
		expect(baseLanguage(input)).toBe(expected)
	})

	it.each([[''], ['   '], [null], [undefined], [42], ['x'], ['klingon_KL']])('gives null for %j', (input) => {
		expect(baseLanguage(input)).toBeNull()
	})
})

describe('userLanguage', () => {
	it('reads the Nextcloud language and strips the region', () => {
		language.value = 'en_GB'
		expect(userLanguage()).toBe('en')
	})

	it('gives null when Nextcloud cannot say', () => {
		language.value = new Error('no OC')
		expect(userLanguage()).toBeNull()
		language.value = 'nl'
	})
})
