/**
 * SPDX-FileCopyrightText: 2026 Conduction / LarpingApp Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/graphql.js — the OpenRegister GraphQL client's
 * pure HTTP-status → Error mapping (401 / 429 / non-ok), variable threading,
 * and the "GraphQL errors without data" rejection. fetch is mocked; the
 * @nextcloud/router + @nextcloud/auth helpers are aliased to stubs.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { queryGraphQL } from '../../src/services/graphql.js'

function mockFetchOnce({ ok = true, status = 200, json = {}, headers = {} } = {}) {
	globalThis.fetch = vi.fn().mockResolvedValueOnce({
		ok,
		status,
		statusText: 'Status',
		headers: { get: (name) => headers[name] ?? null },
		json: async () => json,
	})
}

describe('queryGraphQL', () => {
	beforeEach(() => {})
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('POSTs to the OR graphql endpoint with the request token and query', async () => {
		mockFetchOnce({ json: { data: { ok: true } } })
		const result = await queryGraphQL('{ ping }')
		const [url, opts] = globalThis.fetch.mock.calls[0]
		expect(url).toBe('/index.php/apps/openregister/api/graphql')
		expect(opts.method).toBe('POST')
		expect(opts.headers.requesttoken).toBe('test-token')
		expect(JSON.parse(opts.body)).toEqual({ query: '{ ping }' })
		expect(result).toEqual({ data: { ok: true } })
	})

	it('threads variables into the request body when provided', async () => {
		mockFetchOnce({ json: { data: {} } })
		await queryGraphQL('query($id: ID!){ x(id:$id) }', { id: '42' })
		const body = JSON.parse(globalThis.fetch.mock.calls[0][1].body)
		expect(body).toEqual({
			query: 'query($id: ID!){ x(id:$id) }',
			variables: { id: '42' },
		})
	})

	it('maps a 401 to an authentication error', async () => {
		mockFetchOnce({ ok: false, status: 401 })
		await expect(queryGraphQL('{ x }')).rejects.toThrow(
			'Authentication error — please log in again',
		)
	})

	it('maps a 429 to a rate-limit error including the Retry-After header', async () => {
		mockFetchOnce({ ok: false, status: 429, headers: { 'Retry-After': '120' } })
		await expect(queryGraphQL('{ x }')).rejects.toThrow(
			'Rate limited — retry after 120 seconds',
		)
	})

	it('defaults Retry-After to 60 seconds when the header is absent', async () => {
		mockFetchOnce({ ok: false, status: 429 })
		await expect(queryGraphQL('{ x }')).rejects.toThrow(
			'Rate limited — retry after 60 seconds',
		)
	})

	it('throws a generic failure for other non-ok responses', async () => {
		mockFetchOnce({ ok: false, status: 500 })
		await expect(queryGraphQL('{ x }')).rejects.toThrow(
			'GraphQL request failed: 500',
		)
	})

	it('rejects on GraphQL errors when no data is returned', async () => {
		mockFetchOnce({ json: { errors: [{ message: 'Field not found' }] } })
		await expect(queryGraphQL('{ x }')).rejects.toThrow('Field not found')
	})

	it('resolves (does not throw) when errors accompany partial data', async () => {
		mockFetchOnce({
			json: { data: { x: 1 }, errors: [{ message: 'deprecated' }] },
		})
		const result = await queryGraphQL('{ x }')
		expect(result.data).toEqual({ x: 1 })
	})
})
