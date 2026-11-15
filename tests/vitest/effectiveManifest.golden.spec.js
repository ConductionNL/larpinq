/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the effective manifest that src/main.js builds: the shared
 * buildManifest() over src/manifest.json, every src/manifest.d fragment in
 * sorted filename order, and src/menu-layout.json. The fixture was recorded
 * with the inline pipeline main.js carried before it adopted buildManifest().
 * It holds the full menu, each page as its id and the SHA-256 of its JSON, and
 * the top-level keys (the inline pipeline also copied the last fragment's
 * _meta description block onto the manifest, which nothing reads, so _meta is
 * left out).
 */

import { createHash } from 'node:crypto'
import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const ROOT = resolve(__dirname, '../..')
const read = (rel) => JSON.parse(readFileSync(resolve(ROOT, rel), 'utf8'))
const FIXTURE = read('tests/vitest/fixtures/effectiveManifest.golden.json')

/**
 * JSON with object keys sorted, so a hash does not depend on key order.
 *
 * @param {unknown} value The value.
 * @return {string} The JSON text.
 */
function stable(value) {
	if (Array.isArray(value)) {
		return '[' + value.map(stable).join(',') + ']'
	}
	if (value && typeof value === 'object') {
		return (
			'{'
			+ Object.keys(value)
				.sort()
				.map((key) => JSON.stringify(key) + ':' + stable(value[key]))
				.join(',')
			+ '}'
		)
	}
	return JSON.stringify(value)
}

const sha = (value) => createHash('sha256').update(stable(value)).digest('hex')

/**
 * The effective manifest, built the way src/main.js builds it.
 *
 * @return {Promise<object>} The manifest.
 */
async function effectiveManifest() {
	const { buildManifest } = await import(
		resolve(
			ROOT,
			'node_modules/@conduction/nextcloud-vue/dist/esm/utils/buildManifest.js',
		)
	)
	const fragments = readdirSync(resolve(ROOT, 'src/manifest.d'))
		.filter((name) => name.endsWith('.json'))
		.map((name) => './' + name)
		.sort()
		.map((key) => read('src/manifest.d/' + key.slice(2)))
	return buildManifest(
		read('src/manifest.json'),
		fragments,
		read('src/menu-layout.json'),
	)
}

describe('effective manifest', () => {
	it('has the menu the inline pipeline built', async () => {
		expect((await effectiveManifest()).menu).toStrictEqual(FIXTURE.menu)
	})

	it('has the pages the inline pipeline built, in the same order', async () => {
		const pages = (await effectiveManifest()).pages.map((page) => [
			page.id,
			sha(page),
		])
		expect(pages).toStrictEqual(FIXTURE.pages)
	})

	it('has the same top-level keys and values', async () => {
		const manifest = await effectiveManifest()
		const rest = { ...manifest }
		delete rest.menu
		delete rest.pages
		expect(Object.keys(manifest).sort()).toStrictEqual(FIXTURE.keys)
		expect(sha(rest)).toBe(FIXTURE.rest)
	})
})
