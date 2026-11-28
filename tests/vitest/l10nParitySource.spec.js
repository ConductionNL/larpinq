/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * tests/l10n/check-l10n-parity.js takes the frontend source keys from en.json
 * completed by en.js: scripts/build-l10n-js.js leaves out of en.js every entry
 * equal to its key, so en.js alone would let a locale .js miss those keys.
 *
 * @spec openspec/specs/platform-baseline/spec.md
 */

import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const SCRIPT = path.resolve(__dirname, '../../tests/l10n/check-l10n-parity.js')

/**
 * An OC.L10N.register body.
 *
 * @param {object} translations - the entries
 * @return {string} the .js text
 */
function js(translations) {
	return `OC.L10N.register("larpinq", ${JSON.stringify(translations)}, "nplurals=2; plural=(n != 1);")\n`
}

/**
 * Run the parity check over a throwaway l10n/ with nl as the only required locale.
 *
 * @param {object} files - l10n file name -> content
 * @return {{status: number, out: string}} exit code and combined output
 */
function run(files) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'larpinq-parity-'))
	try {
		fs.mkdirSync(path.join(root, 'l10n'))
		fs.writeFileSync(path.join(root, 'package.json'), '{"name":"larpinq"}')
		for (const [name, content] of Object.entries(files)) {
			fs.writeFileSync(path.join(root, 'l10n', name), content)
		}
		const res = spawnSync(process.execPath, [SCRIPT], {
			cwd: root,
			encoding: 'utf8',
			env: { ...process.env, L10N_REQUIRED_LOCALES: 'nl' },
		})
		return { status: res.status, out: res.stdout + res.stderr }
	} finally {
		fs.rmSync(root, { recursive: true, force: true })
	}
}

const EN_JSON = JSON.stringify({
	translations: { Save: 'Save', 'All worlds': 'All settings' },
})
const NL_JSON = JSON.stringify({
	translations: { Save: 'Opslaan', 'All worlds': 'Alle werelden' },
})

describe('check-l10n-parity source keys', () => {
	it('fails a locale .js that misses a key en.js leaves out as identity', () => {
		const res = run({
			'en.json': EN_JSON,
			'en.js': js({ 'All worlds': 'All settings' }),
			'nl.json': NL_JSON,
			'nl.js': js({ 'All worlds': 'Alle werelden' }),
		})
		expect(res.status).toBe(1)
		expect(res.out).toContain('frontend (.js) nl: 1 missing key(s), 0 empty value(s) of 2')
		expect(res.out).toContain('missing: "Save"')
	})

	it('passes when the locale .js carries every en.json key', () => {
		const res = run({
			'en.json': EN_JSON,
			'en.js': js({ 'All worlds': 'All settings' }),
			'nl.json': NL_JSON,
			'nl.js': js({ Save: 'Opslaan', 'All worlds': 'Alle werelden' }),
		})
		expect(res.status).toBe(0)
		expect(res.out).toContain('every required locale is at full parity')
	})
})
