#!/usr/bin/env node
// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// check-schema-l10n.js — a RATCHET on untranslated schema strings.
//
// WHY THIS EXISTS
//
//   Every string inside a form comes from the OpenRegister schema, not from
//   the app manifest: `fieldsFromSchema()` runs a property `title` and
//   `description` through the injected `cnTranslate`, which CnAppRoot binds to
//   THIS app's id. So a schema title is a key in THIS catalogue — and when the
//   key is absent, `t()` hands the source string back and the field renders in
//   English inside an otherwise translated form. Nothing errors.
//
//   Measured across the fleet on 2026-08-23: 30,459 schema strings had no
//   catalogue key. That is far too much to translate in one go, and the
//   descriptions need rewriting for the person filling in the form before
//   translating them is even worth doing.
//
//   So this is a RATCHET, not a gate. It records how many strings are
//   currently uncovered and fails only when that number GROWS — the debt is
//   measured and cannot expand, while burning it down stays an ordinary PR.
//   Same shape as the JSDoc baseline in @conduction/nextcloud-vue.
//
// WHAT COUNTS AS A SCHEMA STRING
//
//   - a schema `title`         — the create/edit dialog heading, and the noun
//                                in an index page's Add button
//   - a property `title`       — the field's label and its column header
//   - a property `description` — the helper text under the field
//   - the VALUES of a property's `x-enum-labels` — dropdown options and the
//     text of a status badge
//
//   Enum VALUES themselves are deliberately NOT counted. They are stored
//   contract values — several are non-English by design (`ingediend`) — and
//   are never rendered once the property declares `x-enum-labels`.
//
//   `x-notes` is not counted either: it holds the engineering rationale a
//   description used to carry, is never rendered, and so is never translated.
//
// Usage:
//   node scripts/check-schema-l10n.js            (npm run check:schema-l10n)
//   node scripts/check-schema-l10n.js --update   rewrite the baseline
//   node scripts/check-schema-l10n.js --list     print what is uncovered
//
// Exit codes:
//   0 — uncovered count is at or below the baseline
//   1 — it grew, or the baseline file is missing

'use strict'

const fs = require('fs')
const path = require('path')

const REPO_ROOT = path.resolve(__dirname, '..')
const SCHEMA_DIR = path.join(REPO_ROOT, 'lib', 'Settings')
const CATALOGUE = path.join(REPO_ROOT, 'l10n', 'en.json')
const BASELINE = path.join(REPO_ROOT, 'l10n', '.schema-l10n-baseline.json')

/**
 * Every *.json under lib/Settings, at any depth — apps differ in whether they
 * use register.d/, templates/ or a single monolith.
 *
 * @param {string} dir - directory to walk
 * @return {string[]} absolute paths
 */
function schemaFiles(dir) {
	if (!fs.existsSync(dir)) return []
	return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
		const full = path.join(dir, entry.name)
		if (entry.isDirectory()) return schemaFiles(full)
		return entry.name.endsWith('.json') ? [full] : []
	})
}

/**
 * True for any object value, arrays included.
 *
 * @param {unknown} value - the value to test
 * @return {boolean} whether it is a non-null object
 */
function isObject(value) {
	return value !== null && typeof value === 'object'
}

/**
 * A function that records a display string once, with the first place it was
 * seen.
 *
 * @param {string} where - file label, for --list
 * @param {Map<string, string>} sink - string -> first place it was seen
 * @return {function(unknown, string): void} records (value, what)
 */
function recorder(where, sink) {
	return (value, what) => {
		if (typeof value !== 'string' || value.trim() === '') return
		if (!sink.has(value)) sink.set(value, `${where}:${what}`)
	}
}

/**
 * Record a property's title, description and enum labels, on the property
 * itself and on its `items`.
 *
 * @param {object} prop - the property schema
 * @param {function(unknown, string): void} remember - records (value, what)
 */
function collectProperty(prop, remember) {
	remember(prop.title, 'title')
	remember(prop.description, 'description')
	for (const source of [prop, prop.items]) {
		const labels = isObject(source) ? source['x-enum-labels'] : null
		if (!isObject(labels)) continue
		for (const label of Object.values(labels)) remember(label, 'x-enum-labels')
	}
}

/**
 * Record the strings of a node that declares `properties`.
 *
 * @param {object} node - a schema with a `properties` object
 * @param {function(unknown, string): void} remember - records (value, what)
 */
function collectSchema(node, remember) {
	remember(node.title, 'schema title')
	for (const [key, prop] of Object.entries(node.properties)) {
		if (!isObject(prop)) continue
		collectProperty(prop, (value, what) => remember(value, `${key}.${what}`))
	}
}

/**
 * Collect the display strings a schema puts on screen.
 *
 * @param {object|Array} node - current node
 * @param {function(unknown, string): void} remember - records (value, what)
 */
function collect(node, remember) {
	if (!isObject(node)) return

	if (isObject(node.properties) && !Array.isArray(node.properties)) {
		collectSchema(node, remember)
	}

	for (const value of Object.values(node)) collect(value, remember)
}

/**
 * Every display string under lib/Settings, with the first place it was seen.
 *
 * @return {Map<string, string>} string -> first place it was seen
 */
function schemaStrings() {
	const strings = new Map()
	for (const file of schemaFiles(SCHEMA_DIR)) {
		let doc
		try {
			doc = JSON.parse(fs.readFileSync(file, 'utf8'))
		} catch {
			continue // not a schema document; the manifest checks own their own files
		}
		collect(doc, recorder(path.relative(REPO_ROOT, file), strings))
	}
	return strings
}

/**
 * The keys of l10n/en.json.
 *
 * @return {Set<string>} the catalogue keys
 */
function catalogueKeys() {
	try {
		return new Set(
			Object.keys(
				JSON.parse(fs.readFileSync(CATALOGUE, 'utf8')).translations || {},
			),
		)
	} catch {
		// no catalogue yet — then everything is uncovered, which the baseline records
		return new Set()
	}
}

/**
 * Write the uncovered count as the new baseline.
 *
 * @param {number} count - uncovered schema strings
 */
function writeBaseline(count) {
	fs.writeFileSync(BASELINE, JSON.stringify({ uncovered: count }, null, 2) + '\n')
	console.log(`baseline written: ${count} uncovered schema string(s)`)
}

/**
 * The recorded baseline; exits 1 when there is none.
 *
 * @return {number} the uncovered count it allows
 */
function readBaseline() {
	if (!fs.existsSync(BASELINE)) {
		console.error(
			'No baseline. Run `npm run check:schema-l10n -- --update` and commit it.',
		)
		process.exit(1)
	}
	return JSON.parse(fs.readFileSync(BASELINE, 'utf8')).uncovered
}

/**
 * Explain the strings added since the baseline, and exit 1.
 *
 * @param {number} added - how many more are uncovered than the baseline allows
 */
function failOnGrowth(added) {
	console.error('')
	console.error(
		`${added} schema string(s) added with no catalogue key — they will render`,
	)
	console.error('in English inside an otherwise translated form.')
	console.error('')
	console.error(
		'Add them to l10n/en.json (identity) and l10n/nl.json (translated), then',
	)
	console.error('run `npm run l10n:build`. See what is uncovered with:')
	console.error('  node scripts/check-schema-l10n.js --list')
	process.exit(1)
}

/**
 * Compare the counts with the baseline: exit 1 when the uncovered count grew,
 * suggest lowering the baseline when it fell.
 *
 * @param {{total: number, uncovered: number}} counts - schema strings found and uncovered
 */
function compareWithBaseline(counts) {
	const baseline = readBaseline()

	console.log(
		`${counts.total} schema string(s); ${counts.uncovered} uncovered, baseline ${baseline}`,
	)

	if (counts.uncovered > baseline) {
		failOnGrowth(counts.uncovered - baseline)
	}

	if (counts.uncovered < baseline) {
		console.log(
			`${baseline - counts.uncovered} fewer than the baseline — lower it with:`,
		)
		console.log('  npm run check:schema-l10n -- --update')
	}
}

/**
 *
 */
function main() {
	const update = process.argv.includes('--update')
	const list = process.argv.includes('--list')

	const strings = schemaStrings()
	const covered = catalogueKeys()
	const uncovered = [...strings.keys()].filter((s) => !covered.has(s)).sort()

	if (list) {
		for (const s of uncovered) console.log(`${strings.get(s)}\n  ${s}`)
	}

	if (update) {
		writeBaseline(uncovered.length)
		return
	}

	compareWithBaseline({ total: strings.size, uncovered: uncovered.length })
}

main()
