/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for how the service fetch helpers read a response: every helper
 * is called against the same set of scripted responses (success bodies,
 * refusals in each shape, bodies that do not parse, auth and rate-limit
 * statuses, a network failure) and the request it sent plus what it returned
 * or threw are compared case by case with
 * fixtures/serviceResponses.golden.json.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { applyBuild } from '../../src/services/characterBuilds.js'
import { queryGraphQL } from '../../src/services/graphql.js'
import { saveChoices } from '../../src/services/registrationChoices.js'
import { copyWorld, fetchCopyPreview } from '../../src/services/worldCopy.js'
import { fetchAwardRoster, saveAwards } from '../../src/services/xpAwardBatch.js'

vi.mock('@nextcloud/l10n', () => ({ translate: (app, key) => key }))

const FIXTURE = resolve(__dirname, 'fixtures/serviceResponses.golden.json')

const THROWS = Symbol('throws')

const RESPONSES = {
	'200 empty object': { status: 200, body: {} },
	'200 full body': {
		status: 200,
		body: {
			data: { worlds: [1] },
			world: { id: 'w-9', name: 'Aldmoor' },
			counts: { characters: 3 },
			created: [{}, {}],
			refused: [{ character: 'c', reason: 'r' }],
			eventName: 'Feast',
			rows: [{ id: 1 }],
			attendanceAvailable: true,
		},
	},
	'200 wrong shapes': {
		status: 200,
		body: {
			world: { id: 7, name: null },
			counts: 'many',
			created: 'two',
			refused: {},
			eventName: 0,
			rows: 'x',
			attendanceAvailable: 'yes',
		},
	},
	'200 graphql errors without data': {
		status: 200,
		body: { errors: [{ message: 'bad field' }] },
	},
	'200 graphql error without message': {
		status: 200,
		body: { errors: [{}] },
	},
	'200 graphql errors with data': {
		status: 200,
		body: { errors: [{ message: 'partial' }], data: { a: 1 } },
	},
	'200 graphql empty errors': { status: 200, body: { errors: [] } },
	'200 null body': { status: 200, body: null },
	'200 body does not parse': { status: 200, body: THROWS },
	'400 nested errors': {
		status: 400,
		body: {
			errors: { message: 'Not enough XP', budget: { shortfall: 12 } },
			message: 'outer',
			error: 'refused',
			leftovers: ['a'],
		},
	},
	'400 errors as a string': {
		status: 400,
		body: { errors: 'flat', message: 'Top message', error: 5 },
	},
	'400 top-level message': {
		status: 400,
		body: { message: 'Closed', budget: { shortfall: '4' } },
	},
	'400 errors as a string with a budget': {
		status: 400,
		body: { errors: 'flat', message: 'm', budget: { shortfall: 3 } },
	},
	'400 errors without a message': {
		status: 400,
		body: { errors: { budget: { shortfall: 2 } }, message: 'outer only' },
	},
	'400 string body': { status: 400, body: 'nope' },
	'200 string body': { status: 200, body: 'text' },
	'400 null errors': { status: 400, body: { errors: null, message: 'm' } },
	'400 no body': { status: 400, body: THROWS },
	'400 array body': { status: 400, body: ['x'] },
	401: { status: 401, body: { error: 'login' } },
	403: { status: 403, body: { error: 'Not yours' } },
	404: { status: 404, statusText: 'Not Found', body: {} },
	422: { status: 422, body: { error: 'Too big' } },
	'429 with retry': { status: 429, retryAfter: '5', body: {} },
	'429 without retry': { status: 429, body: {} },
	500: { status: 500, statusText: 'Server Error', body: THROWS },
	'network error': { network: new Error('offline') },
	'network string': { network: 'down' },
}

const CALLS = {
	applyBuild: () =>
		applyBuild('c 1', { skills: ['s'], items: 'x', conditions: null }),
	applyBuildNoLists: () => applyBuild('c-2', null),
	saveChoices: () =>
		saveChoices('r 1', { ticketType: 't', options: ['o'], code: 'C' }),
	saveAwards: () => saveAwards('e 1', [{ character: 'c', amount: 2 }]),
	fetchAwardRoster: () => fetchAwardRoster('e 2'),
	fetchCopyPreview: () => fetchCopyPreview('w 1'),
	copyWorld: () => copyWorld('w 2', '  New world  '),
	queryGraphQL: () => queryGraphQL('{ worlds }'),
	queryGraphQLVariables: () => queryGraphQL('{ w }', { id: 1 }),
}

/**
 * A scripted fetch response.
 *
 * @param {object} spec The response spec.
 * @return {object} The response stand-in.
 */
function responseOf(spec) {
	return {
		ok: spec.status >= 200 && spec.status < 300,
		status: spec.status,
		statusText: spec.statusText ?? '',
		headers: {
			get: (name) =>
				name === 'Retry-After' ? (spec.retryAfter ?? null) : null,
		},
		json: async () => {
			if (spec.body === THROWS) {
				throw new SyntaxError('Unexpected end of JSON input')
			}
			return structuredClone(spec.body)
		},
	}
}

/**
 * Run one helper against one response.
 *
 * @param {Function} call The helper call.
 * @param {object} spec The response spec.
 * @return {Promise<object>} The request sent and the outcome.
 */
async function run(call, spec) {
	const requests = []
	globalThis.fetch = async (url, init) => {
		requests.push({
			url,
			method: init?.method ?? '(none)',
			headers: init?.headers ?? '(none)',
			credentials: init?.credentials ?? '(none)',
			body: init?.body === undefined ? '(none)' : JSON.parse(init.body),
		})
		if ('network' in spec) {
			throw spec.network
		}
		return responseOf(spec)
	}
	try {
		return { requests, returned: await call() }
	} catch (error) {
		return {
			requests,
			threw: String(error?.message ?? error),
			type: error?.constructor?.name ?? typeof error,
		}
	}
}

/**
 * Every helper against every response.
 *
 * @return {Promise<object>} The recorded cases.
 */
async function record() {
	const out = {}
	for (const [name, call] of Object.entries(CALLS)) {
		for (const [label, spec] of Object.entries(RESPONSES)) {
			out[`${name} / ${label}`] = JSON.parse(
				JSON.stringify(await run(call, spec), (k, v) =>
					v === undefined ? '(undefined)' : v,
				),
			)
		}
	}
	return out
}

describe('service response reading (golden)', () => {
	const realFetch = globalThis.fetch
	afterEach(() => {
		globalThis.fetch = realFetch
	})

	it('matches the recorded cases', async () => {
		const actual = await record()
		if (process.env.RECORD_GOLDEN === '1') {
			writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(readFileSync(FIXTURE, 'utf8'))
		for (const name of Object.keys(expected)) {
			expect(actual[name], name).toEqual(expected[name])
		}
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
	})
})
