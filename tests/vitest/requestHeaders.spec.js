/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/requestHeaders.js: the CSRF token and the JSON
 * headers every JSON request sends.
 */

import { afterEach, describe, expect, it } from 'vitest'
import {
	jsonHeaders,
	ocsHeaders,
	ocsJsonHeaders,
} from '../../src/services/requestHeaders.js'

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

describe('ocsHeaders', () => {
	afterEach(() => {
		delete globalThis.OC
	})

	it('sends the page token and the OCS marker, read on each call', () => {
		globalThis.OC = { requestToken: 'first' }
		expect(ocsHeaders()).toStrictEqual({
			requesttoken: 'first',
			'OCS-APIREQUEST': 'true',
		})
		globalThis.OC.requestToken = 'second'
		expect(ocsHeaders().requesttoken).toBe('second')
	})

	it('adds the JSON content type first for a JSON body', () => {
		globalThis.OC = { requestToken: 'first' }
		expect(JSON.stringify(ocsJsonHeaders())).toBe(
			JSON.stringify({
				'Content-Type': 'application/json',
				requesttoken: 'first',
				'OCS-APIREQUEST': 'true',
			}),
		)
	})
})
