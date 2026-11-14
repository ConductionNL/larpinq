/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for scripts/check-schema-l10n.js: every case runs the script in
 * a throwaway repository and records its exit code, stdout, stderr and the
 * files present afterwards, compared byte for byte with
 * fixtures/checkSchemaL10n.golden.json.
 *
 * Set RECORD_GOLDEN=1 to rewrite the fixture.
 */

import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { afterAll, describe, expect, it } from 'vitest'

const SCRIPT = path.resolve(__dirname, '../../scripts/check-schema-l10n.js')
const GOLDEN = path.resolve(__dirname, 'fixtures/checkSchemaL10n.golden.json')

const json = (value) => JSON.stringify(value, null, 2)

const SCHEMAS = {
	'lib/Settings/larpinq_register.json': json({
		components: {
			schemas: {
				Character: {
					title: 'Character',
					properties: {
						name: { title: 'Name', description: 'Full name' },
						status: {
							title: 'Status',
							description: 'Full name',
							'x-enum-labels': { alive: 'Alive', dead: 'Dead' },
						},
						tags: {
							items: { 'x-enum-labels': { a: 'Tag A', b: ' ' } },
						},
						both: {
							'x-enum-labels': { x: 'Own label' },
							items: { 'x-enum-labels': ['Item label', 7] },
						},
						nolabels: { 'x-enum-labels': null, items: null },
						broken: null,
						count: 3,
						blank: { title: '', description: 42 },
					},
				},
				List: [{ properties: { item: { title: 'Item title' } } }, 'text'],
			},
		},
	}),
	'lib/Settings/register.d/event.json': json({
		title: 'Event',
		properties: { days: { title: 'Days', description: 'Name' } },
		nested: {
			properties: 'not an object',
			other: { properties: [] },
			nulled: { properties: null, title: 'Never seen' },
			listed: {
				title: 'Array props',
				properties: [{ title: 'Array item' }],
			},
		},
	}),
	'lib/Settings/register.d/deep/more.json': json([
		{ title: 'Deep', properties: { a: { title: 'Deep field' } } },
	]),
	'lib/Settings/register.d/notes.txt': 'not json',
	'lib/Settings/register.d/bad.json': '{ broken',
}

const CATALOGUE = {
	'l10n/en.json': json({
		translations: { Character: 'Character', Name: 'Name', 'Tag A': 'Tag A' },
	}),
}

function baseline(uncovered) {
	return {
		'l10n/.schema-l10n-baseline.json': json({ uncovered }),
	}
}

const CASES = {
	'at the baseline': { files: { ...SCHEMAS, ...CATALOGUE, ...baseline(11) } },
	'--list': {
		args: ['--list'],
		files: { ...SCHEMAS, ...CATALOGUE, ...baseline(14) },
	},
	'--update': { args: ['--update'], files: { ...SCHEMAS, ...CATALOGUE } },
	'--update and --list': {
		args: ['--list', '--update'],
		files: { ...SCHEMAS, ...CATALOGUE },
	},
	'no baseline': { files: { ...SCHEMAS, ...CATALOGUE } },
	'--list without a baseline': {
		args: ['--list'],
		files: { ...SCHEMAS, ...CATALOGUE },
	},
	'grew past the baseline': {
		files: { ...SCHEMAS, ...CATALOGUE, ...baseline(5) },
	},
	'grew by one': { files: { ...SCHEMAS, ...CATALOGUE, ...baseline(10) } },
	'fell below the baseline': {
		files: { ...SCHEMAS, ...CATALOGUE, ...baseline(20) },
	},
	'no catalogue': { files: { ...SCHEMAS, ...baseline(14) } },
	'catalogue without translations': {
		files: { ...SCHEMAS, 'l10n/en.json': json({}), ...baseline(17) },
	},
	'unreadable catalogue': {
		files: { ...SCHEMAS, 'l10n/en.json': '{ nope', ...baseline(17) },
	},
	'no lib/Settings': { files: baseline(0) },
	'unknown argument': {
		args: ['--other'],
		files: { ...SCHEMAS, ...CATALOGUE, ...baseline(14) },
	},
}

/**
 * Every file under a directory, as repo-relative path -> content.
 *
 * @param {string} root - the throwaway repository
 * @return {object} the tree
 */
function readTree(root) {
	const tree = {}
	const walk = (dir) => {
		for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
			const abs = path.join(dir, entry.name)
			if (entry.isDirectory()) {
				walk(abs)
			} else if (!abs.includes(`${path.sep}scripts${path.sep}`)) {
				tree[path.relative(root, abs).split(path.sep).join('/')] =
					fs.readFileSync(abs, 'utf8')
			}
		}
	}
	walk(root)
	return Object.fromEntries(
		Object.entries(tree).sort(([a], [b]) => a.localeCompare(b)),
	)
}

/**
 * Run the script in a fresh repository built from one case.
 *
 * @param {object} spec - { files, args }
 * @return {object} exit code, output and the files present afterwards
 */
function runCase(spec) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'larpinq-golden-'))
	try {
		const files = {
			'scripts/check-schema-l10n.js': fs.readFileSync(SCRIPT, 'utf8'),
			...spec.files,
		}
		for (const [rel, content] of Object.entries(files)) {
			fs.mkdirSync(path.dirname(path.join(root, rel)), { recursive: true })
			fs.writeFileSync(path.join(root, rel), content)
		}
		const run = spawnSync(
			process.execPath,
			[path.join(root, 'scripts/check-schema-l10n.js'), ...(spec.args || [])],
			{ cwd: root, encoding: 'utf8' },
		)
		return {
			status: run.status,
			stdout: run.stdout,
			stderr: run.stderr,
			files: readTree(root),
		}
	} finally {
		fs.rmSync(root, { recursive: true, force: true })
	}
}

// One test per case: each case starts its own node process, so a single test
// holding all of them took 14 process starts and ran over the 5 s test timeout
// on a loaded machine.
describe('scripts/check-schema-l10n.js (golden)', () => {
	const recorded = JSON.parse(fs.readFileSync(GOLDEN, 'utf8'))
	const actual = {}

	it('records the same cases', () => {
		expect(Object.keys(recorded)).toEqual(Object.keys(CASES))
	})

	it.each(Object.keys(CASES))(
		'%s matches the recorded run byte for byte',
		(name) => {
			actual[name] = runCase(CASES[name])
			if (process.env.RECORD_GOLDEN === '1') {
				return
			}
			expect(actual[name]).toEqual(recorded[name])
		},
	)

	afterAll(() => {
		if (process.env.RECORD_GOLDEN === '1') {
			fs.writeFileSync(GOLDEN, JSON.stringify(actual, null, '\t') + '\n')
		}
	})
})
