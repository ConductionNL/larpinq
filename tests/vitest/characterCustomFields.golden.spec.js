/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for sheetFrom() and inputValue() in
 * src/services/characterCustomFields.js: seeded random definitions and
 * characters (world scoping, duplicate keys, order and label ties, private
 * and public values, malformed bags) and every field type against a fixed set
 * of raw inputs, compared case by case with
 * fixtures/characterCustomFields.golden.json.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { inputValue, sheetFrom } from '../../src/services/characterCustomFields.js'

const FIXTURE = resolve(__dirname, 'fixtures/characterCustomFields.golden.json')

/**
 * A seeded pseudo-random generator (mulberry32).
 *
 * @param {number} seed The seed.
 * @return {() => number} A function returning a number in [0, 1).
 */
function mulberry32(seed) {
	let a = seed
	return () => {
		a = (a + 0x6d2b79f5) | 0
		let t = Math.imul(a ^ (a >>> 15), 1 | a)
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296
	}
}

const KEYS = ['class', 'oath', 'rank', 'secret', '', null, undefined]
const SETTINGS = ['', 'w1', 'w2', undefined, null]
const ORDERS = [undefined, 0, 1, 2, -1, null]
const LABELS = ['B', 'a', undefined, 'A', 'b', '', 7]
const VISIBILITY = ['gamemasters', 'players', undefined]
const TYPES = ['text', 'number', 'choice', 'yes-no', undefined, '']
const CHOICES = [['x', 'y'], [], 'x,y', undefined]
const HELP = ['Some help', '', undefined]
const BAGS = [
	{},
	{ class: 'Mage', oath: true },
	{ rank: 3, orphan: 'left over' },
	{ secret: 'kept', extra: null },
	null,
	undefined,
	['class'],
	'text',
]

/**
 * One random case for sheetFrom().
 *
 * @param {() => number} rnd The generator.
 * @return {{definitions: unknown, character: unknown}} The inputs.
 */
function sheetCase(rnd) {
	const pick = (list) => list[Math.floor(rnd() * list.length)]
	const count = Math.floor(rnd() * 7)
	const definitions = []
	for (let i = 0; i < count; i++) {
		if (rnd() < 0.06) {
			definitions.push(null)
			continue
		}
		definitions.push({
			key: pick(KEYS),
			setting: pick(SETTINGS),
			order: pick(ORDERS),
			label: pick(LABELS),
			visibility: pick(VISIBILITY),
			fieldType: pick(TYPES),
			choices: pick(CHOICES),
			help: pick(HELP),
			tag: i,
		})
	}
	const character =
		rnd() < 0.05
			? null
			: {
					setting: pick(SETTINGS),
					customFields: pick(BAGS),
					customFieldsPrivate: pick(BAGS),
				}
	return { definitions: rnd() < 0.05 ? null : definitions, character }
}

const RAWS = [
	null,
	undefined,
	true,
	false,
	'yes',
	' 12 ',
	'',
	'   ',
	'abc',
	0,
	3.5,
	Number.NaN,
	Number.POSITIVE_INFINITY,
	'1e3',
	' x ',
	'0x10',
]

/**
 * Make a value JSON-safe so undefined, NaN and infinity stay visible.
 *
 * @param {unknown} value The value.
 * @return {unknown} The JSON-safe value.
 */
function plain(value) {
	return JSON.parse(
		JSON.stringify(value, (k, v) => {
			if (v === undefined) {
				return '(undefined)'
			}
			if (typeof v === 'number' && !Number.isFinite(v)) {
				return `(${v})`
			}
			return v
		}),
	)
}

/**
 * Every recorded case.
 *
 * @return {object} The sheet and input cases.
 */
function record() {
	const rnd = mulberry32(20261102)
	const sheets = []
	for (let i = 0; i < 400; i++) {
		const { definitions, character } = sheetCase(rnd)
		sheets.push(
			plain({
				in: { definitions, character },
				out: sheetFrom(definitions, character),
			}),
		)
	}
	const inputs = []
	for (const type of [...TYPES, 'unknown']) {
		for (const raw of RAWS) {
			inputs.push(
				plain({ type, raw: String(raw), out: inputValue(type, raw) }),
			)
		}
	}
	return { sheets, inputs }
}

describe('characterCustomFields sheetFrom and inputValue (golden)', () => {
	it('matches the recorded cases', () => {
		const actual = record()
		if (process.env.RECORD_GOLDEN === '1') {
			writeFileSync(FIXTURE, JSON.stringify(actual) + '\n')
		}
		const expected = JSON.parse(readFileSync(FIXTURE, 'utf8'))
		expect(actual.sheets.length).toBe(expected.sheets.length)
		actual.sheets.forEach((sheet, i) =>
			expect(sheet, `sheet ${i}`).toEqual(expected.sheets[i]),
		)
		actual.inputs.forEach((input, i) =>
			expect(input, `input ${i}`).toEqual(expected.inputs[i]),
		)
		expect(actual.inputs.length).toBe(expected.inputs.length)
	})
})
