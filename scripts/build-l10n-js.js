#!/usr/bin/env node
// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.
//
// build-l10n-js.js — regenerate l10n/<locale>.js from l10n/<locale>.json.
//
// WHY THIS EXISTS
//
//   Nextcloud loads a locale catalogue in TWO formats and neither substitutes
//   for the other:
//
//     l10n/<locale>.json — read server-side by PHP `$l->t()`.
//     l10n/<locale>.js   — an `OC.L10N.register(<appId>, {…}, <pluralForm>)`
//                          call, the ONLY thing the browser ever sees. Raw
//                          JSON is not served from an app directory at all:
//                          `/custom_apps/humaniq/l10n/nl.json` is a 404.
//
//   The pair was hand-maintained, which is exactly the shape of drift the
//   parity guard exists to catch: a key added to the .json and forgotten in
//   the .js renders in English for every browser while every server-rendered
//   string is Dutch, and nothing throws. Deriving the .js removes the chance
//   to forget.
//
//   `npm run check:l10n` still asserts the two carry identical pairs — this
//   script makes that assertion cheap to satisfy rather than replacing it.
//
// Usage:
//   node scripts/build-l10n-js.js            (npm run l10n:build)
//   node scripts/build-l10n-js.js --check    exit 1 if any .js is stale
//
// Exit codes:
//   0 — every .js written (or already current, under --check)
//   1 — a catalogue is malformed, or --check found a stale .js

'use strict'

const fs = require('fs')
const path = require('path')

const REPO_ROOT = path.resolve(__dirname, '..')
const L10N_DIR = path.join(REPO_ROOT, 'l10n')

/**
 * The app id the browser catalogue must register under. Read from
 * appinfo/info.xml rather than hard-coded: this app has already been renamed
 * once (hrmq -> humaniq), and a catalogue registered under the old id is
 * silently ignored by `t()` — every string falls back to its English key with
 * no error anywhere.
 *
 * @return {string} the <id> declared in appinfo/info.xml
 */
function appId() {
	const xml = fs.readFileSync(path.join(REPO_ROOT, 'appinfo', 'info.xml'), 'utf8')
	const match = xml.match(/<id>([^<]+)<\/id>/)
	if (match === null) {
		console.error('appinfo/info.xml declares no <id>')
		process.exit(1)
	}
	return match[1].trim()
}

/**
 * Render one catalogue as the `OC.L10N.register` call the browser expects.
 *
 * @param {string} id - the app id to register under
 * @param {object} translations - key -> translation
 * @param {string} pluralForm - the catalogue's gettext plural rule
 * @return {string} the .js file body
 */
function renderJs(id, translations, pluralForm) {
	const body = Object.keys(translations)
		.map((key) => `        ${JSON.stringify(key)}: ${JSON.stringify(translations[key])}`)
		.join(',\n')
	return [
		'OC.L10N.register(',
		`    ${JSON.stringify(id)},`,
		'    {',
		body,
		'    },',
		`    ${JSON.stringify(pluralForm)}`,
		')',
		'',
	].join('\n')
}

function main() {
	const check = process.argv.includes('--check')
	const id = appId()
	const stale = []

	for (const locale of ['en', 'nl']) {
		const jsonFile = path.join(L10N_DIR, `${locale}.json`)
		const jsFile = path.join(L10N_DIR, `${locale}.js`)

		let doc
		try {
			doc = JSON.parse(fs.readFileSync(jsonFile, 'utf8'))
		} catch (error) {
			console.error(`l10n/${locale}.json does not parse: ${error.message}`)
			process.exit(1)
		}
		if (doc.translations === undefined || doc.pluralForm === undefined) {
			console.error(`l10n/${locale}.json is missing "translations" or "pluralForm"`)
			process.exit(1)
		}

		const rendered = renderJs(id, doc.translations, doc.pluralForm)
		const current = fs.existsSync(jsFile) ? fs.readFileSync(jsFile, 'utf8') : null

		if (current === rendered) {
			console.log(`  ✓ l10n/${locale}.js up to date (${Object.keys(doc.translations).length} keys)`)
			continue
		}
		if (check) {
			stale.push(`l10n/${locale}.js`)
			continue
		}
		fs.writeFileSync(jsFile, rendered)
		console.log(`  ✎ l10n/${locale}.js written (${Object.keys(doc.translations).length} keys)`)
	}

	if (stale.length > 0) {
		console.error('')
		console.error(`Stale browser catalogue: ${stale.join(', ')}`)
		console.error('Run `npm run l10n:build` and commit the result.')
		process.exit(1)
	}
}

main()
