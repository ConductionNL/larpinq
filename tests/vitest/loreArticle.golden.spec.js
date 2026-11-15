/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for buildLoreTree: page sets with nesting, ordering ties,
 * orphans, cycles, ids in each shape and missing fields are turned into a
 * tree and compared case by case with fixtures/loreArticle.golden.json.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { buildLoreTree } from '../../src/services/loreArticle.js'

const FIXTURE = resolve(__dirname, 'fixtures/loreArticle.golden.json')

const PAGE_SETS = {
	null: null,
	undefined,
	empty: [],
	'flat, ordered by order then title': [
		{ id: 'c', title: 'Cities', order: 2 },
		{ id: 'a', title: 'Beasts', order: 1 },
		{ id: 'b', title: 'Apples', order: 1 },
		{ id: 'd', title: 'Zed' },
		{ id: 'e', title: 'Alpha', order: 'x' },
		{ id: 'f', title: 'Neg', order: -3 },
		{ id: 'g', title: 'Str', order: '1' },
	],
	'deep nesting': [
		{ id: 'r', title: 'Root' },
		{ id: 'k1', title: 'Kid 2', parent: 'r', order: 2 },
		{ id: 'k2', title: 'Kid 1', parent: 'r', order: 1 },
		{ id: 'g1', title: 'Grandkid', parent: 'k1' },
		{ id: 'g2', title: 'Another', parent: 'k1' },
		{ id: 'gg', title: 'Great', parent: 'g2' },
	],
	'orphan and hidden parent': [
		{ id: 'o', title: 'Orphan', parent: 'hidden' },
		{ id: 'p', title: 'Top' },
		{ id: 'q', title: 'Under orphan', parent: 'o' },
	],
	'self parent': [{ id: 's', title: 'Self', parent: 's' }],
	'two cycle': [
		{ id: 'x', title: 'X', parent: 'y' },
		{ id: 'y', title: 'Y', parent: 'x' },
	],
	'three cycle with a tail': [
		{ id: 'm', title: 'M', parent: 'n' },
		{ id: 'n', title: 'N', parent: 'o' },
		{ id: 'o', title: 'O', parent: 'm' },
		{ id: 't', title: 'Tail', parent: 'm' },
		{ id: 'u', title: 'Root' },
		{ id: 'v', title: 'Child of root', parent: 'u' },
	],
	'ids in each shape': [
		{ '@self': { id: 'self-id' }, title: 'From self' },
		{ id: 5, title: 'Numeric' },
		{ id: '', '@self': { id: 'fallback' }, title: 'Empty id' },
		{ title: 'No id' },
		{ id: 0, title: 'Zero id' },
		{ '@self': {}, title: 'Empty self' },
		{ id: 'child', title: 'Of numeric', parent: '5' },
		{ id: 'child2', title: 'Of numeric int', parent: 5 },
	],
	'missing fields': [
		{ id: 'n1' },
		{ id: 'n2', title: null, order: null, parent: '' },
		{ id: 'n3', title: 'T', parent: null },
		{ id: 'n4', title: 'Under n1', parent: 'n1' },
	],
	'duplicate ids': [
		{ id: 'd', title: 'First', order: 1 },
		{ id: 'd', title: 'Second', order: 2 },
		{ id: 'e', title: 'Under d', parent: 'd' },
	],
	'null entries': [null, { id: 'z', title: 'Z' }],
}

/**
 * Every page set through buildLoreTree.
 *
 * @return {object} The recorded cases.
 */
function record() {
	const out = {}
	for (const [name, pages] of Object.entries(PAGE_SETS)) {
		try {
			out[name] = { returned: buildLoreTree(structuredClone(pages)) }
		} catch (error) {
			out[name] = { threw: String(error?.message ?? error) }
		}
	}
	return out
}

describe('buildLoreTree (golden)', () => {
	it('matches the recorded cases', () => {
		const actual = record()
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
