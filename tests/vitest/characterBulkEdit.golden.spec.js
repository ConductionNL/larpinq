/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for applyBulkEdit: a set of selections is written against
 * scripted responses (success, refusals in each body shape, bodies that do
 * not parse, network failures), and the requests sent, the progress calls and
 * the report are compared case by case with
 * fixtures/characterBulkEdit.golden.json.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'
import { applyBulkEdit } from '../../src/services/characterBulkEdit.js'

const FIXTURE = resolve(__dirname, 'fixtures/characterBulkEdit.golden.json')

const THROWS = Symbol('throws')

const RESPONSES = {
	ok: { status: 200, body: {} },
	'nested errors': {
		status: 400,
		body: { errors: { message: 'Locked' }, message: 'outer', error: 'e' },
	},
	'errors without a message': {
		status: 400,
		body: { errors: { field: 'x' }, message: 'outer', error: 'e' },
	},
	'errors as a string': {
		status: 400,
		body: { errors: 'flat', message: 'Top', error: 'e' },
	},
	'null errors': { status: 400, body: { errors: null, message: 'm' } },
	'error only': { status: 403, body: { error: 'Not yours' } },
	'numeric message': { status: 409, body: { message: 7 } },
	'zero message': { status: 409, body: { message: 0 } },
	'empty message': { status: 409, body: { message: '', error: 'fallback' } },
	'null body': { status: 500, body: null },
	'string body': { status: 400, body: 'nope' },
	'array body': { status: 400, body: ['x'] },
	'no body': { status: 502, body: THROWS },
	'network error': { network: new Error('offline') },
	'network string': { network: 'down' },
}

const SELECTIONS = {
	'three ids, names for one': {
		ids: ['harrow', 'venn', 'to mas'],
		choices: { status: 'retired', type: '', setting: null },
		names: { venn: 'Lady Venn' },
	},
	'one id, no names': { ids: ['x'], choices: { type: 'npc' } },
	'empty name falls back to the id': {
		ids: ['a'],
		choices: { setting: 'w1' },
		names: { a: '' },
	},
	'null ids': { ids: null, choices: { status: 'dead' } },
	'no ids': { ids: [], choices: {} },
	'null choices': { ids: ['y'], choices: null },
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
		json: async () => {
			if (spec.body === THROWS) {
				throw new SyntaxError('Unexpected end of JSON input')
			}
			return structuredClone(spec.body)
		},
	}
}

/**
 * Run one selection with every request answered by one response, and once
 * with the responses alternating between success and that response.
 *
 * @param {object} selection The selection.
 * @param {object} spec The response spec.
 * @param {boolean} alternate Whether every other request succeeds.
 * @return {Promise<object>} The requests, the progress calls and the report.
 */
async function run(selection, spec, alternate) {
	const requests = []
	globalThis.fetch = async (url, init) => {
		requests.push({
			url,
			method: init?.method,
			headers: init?.headers,
			body: init?.body,
		})
		if (alternate && requests.length % 2 === 1) {
			return responseOf(RESPONSES.ok)
		}
		if ('network' in spec) {
			throw spec.network
		}
		return responseOf(spec)
	}
	const progress = []
	const args = [selection.ids, selection.choices]
	if ('names' in selection) {
		args.push(selection.names, (count) => progress.push(count))
	}
	try {
		return { requests, progress, returned: await applyBulkEdit(...args) }
	} catch (error) {
		return { requests, progress, threw: String(error?.message ?? error) }
	}
}

/**
 * Every selection against every response.
 *
 * @return {Promise<object>} The recorded cases.
 */
async function record() {
	const out = {}
	for (const [name, selection] of Object.entries(SELECTIONS)) {
		for (const [label, spec] of Object.entries(RESPONSES)) {
			for (const alternate of [false, true]) {
				const key = `${name} / ${label}${alternate ? ' / alternating' : ''}`
				out[key] = await run(selection, spec, alternate)
			}
		}
	}
	return out
}

describe('applyBulkEdit (golden)', () => {
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
