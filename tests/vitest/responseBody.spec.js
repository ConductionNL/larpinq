/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pins how a fetch response body is read: an unreadable or non-object body
 * reads as an empty object, and a refusal yields its errors and a message
 * from either the `errors` envelope or the body itself.
 */

import { describe, expect, it } from 'vitest'
import { bodyOf, refusalOf } from '../../src/services/responseBody.js'

/**
 * A response whose json() answers `value`, or throws when `value` is an Error.
 *
 * @param {*} value The parsed body, or an Error.
 * @return {{json: Function}} The response.
 */
function response(value) {
	return {
		json: async () => {
			if (value instanceof Error) {
				throw value
			}
			return value
		},
	}
}

describe('bodyOf', () => {
	it('returns the parsed object', async () => {
		expect(await bodyOf(response({ id: 'c-1' }))).toEqual({ id: 'c-1' })
	})

	it('reads an unreadable body as empty', async () => {
		expect(await bodyOf(response(new SyntaxError('Unexpected token <')))).toEqual({})
	})

	it('reads a body that is not an object as empty', async () => {
		expect(await bodyOf(response(null))).toEqual({})
		expect(await bodyOf(response('ok'))).toEqual({})
		expect(await bodyOf(response(42))).toEqual({})
	})

	it('keeps an array body, which is an object', async () => {
		expect(await bodyOf(response([1, 2]))).toEqual([1, 2])
	})
})

describe('refusalOf', () => {
	it('takes the errors envelope and its message', async () => {
		const errors = { message: 'Name is taken', name: ['taken'] }
		expect(await refusalOf(response({ errors, message: 'outer' }))).toEqual({ errors, message: 'Name is taken' })
	})

	it('falls back to the body message when the envelope has none', async () => {
		const errors = { name: ['taken'] }
		expect(await refusalOf(response({ errors, message: 'Validation failed' }))).toEqual({
			errors,
			message: 'Validation failed',
		})
	})

	it('treats the body as the errors when there is no envelope', async () => {
		const body = { message: 'Forbidden' }
		expect(await refusalOf(response(body))).toEqual({ errors: body, message: 'Forbidden' })
	})

	it('ignores an errors value that is not an object', async () => {
		const body = { errors: 'bad', message: 'Nope' }
		expect(await refusalOf(response(body))).toEqual({ errors: body, message: 'Nope' })
	})

	it('has an empty message for an unreadable body', async () => {
		expect(await refusalOf(response(new Error('network')))).toEqual({ errors: {}, message: '' })
	})

	it('stringifies a non-string message', async () => {
		expect((await refusalOf(response({ message: 404 }))).message).toBe('404')
	})
})
