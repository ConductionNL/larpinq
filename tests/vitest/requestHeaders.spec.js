/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/requestHeaders.js: the CSRF token and the JSON
 * headers every JSON request sends.
 */

import { describe, expect, it } from 'vitest'
import { jsonHeaders } from '../../src/services/requestHeaders.js'

describe('jsonHeaders', () => {
	it('sends the CSRF token and JSON both ways', () => {
		expect(jsonHeaders()).toStrictEqual({
			requesttoken: 'test-token',
			Accept: 'application/json',
			'Content-Type': 'application/json',
		})
	})

	it('returns a fresh object on each call', () => {
		const first = jsonHeaders()
		first.requesttoken = 'changed'
		expect(jsonHeaders().requesttoken).toBe('test-token')
	})
})
