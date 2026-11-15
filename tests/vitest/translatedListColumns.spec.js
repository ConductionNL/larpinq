/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Events and Items lists show the "(translated from {language})" badge
 * beside the name (larpinq-adopt-or-abstractions, task 3.5). Both lists keep
 * their schema-derived columns: the page sets the `translatedName` widget on
 * the `name` column only, through `columnOverrides`, which the library merges
 * into the column it derives from the schema.
 *
 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md#requirement-larpinq-lists-must-display-translated-from-lang-badge-when-served-language-differs-from-source
 */

import { readdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const ROOT = resolve(__dirname, '../..')
const read = (rel) => JSON.parse(readFileSync(resolve(ROOT, rel), 'utf8'))
const LIB = 'node_modules/@conduction/nextcloud-vue/dist/esm/utils/'

/**
 * The effective manifest, built the way src/main.js builds it.
 *
 * @return {Promise<object>} The manifest.
 */
async function effectiveManifest() {
	const { buildManifest } = await import(resolve(ROOT, LIB + 'buildManifest.js'))
	const fragments = readdirSync(resolve(ROOT, 'src/manifest.d'))
		.filter((name) => name.endsWith('.json'))
		.sort()
		.map((name) => read('src/manifest.d/' + name))
	return buildManifest(read('src/manifest.json'), fragments, read('src/menu-layout.json'))
}

const schemas = read('lib/Settings/larpinq_register.json').components.schemas

describe.each([
	['Events', 'event'],
	['Items', 'item'],
])('the %s list', (pageId, schemaKey) => {
	it('sets the translatedName widget on the name column and declares no columns of its own', async () => {
		const page = (await effectiveManifest()).pages.find((candidate) => candidate.id === pageId)

		expect(page.type).toBe('index')
		expect(page.config.columns).toBeUndefined()
		expect(page.config.columnOverrides).toStrictEqual({ name: { widget: 'translatedName' } })
	})

	it('keeps every schema-derived column, with the widget on name only', async () => {
		const { columnsFromSchema } = await import(resolve(ROOT, LIB + 'schema.js'))
		const page = (await effectiveManifest()).pages.find((candidate) => candidate.id === pageId)

		const plain = columnsFromSchema(schemas[schemaKey])
		const shown = columnsFromSchema(schemas[schemaKey], { overrides: page.config.columnOverrides })

		expect(shown.map((column) => column.key)).toStrictEqual(plain.map((column) => column.key))
		expect(shown.filter((column) => column.widget).map((column) => [column.key, column.widget])).toStrictEqual([['name', 'translatedName']])
	})
})

describe('the translatedName widget', () => {
	it('is registered as an app cell widget', () => {
		const app = readFileSync(resolve(ROOT, 'src/App.vue'), 'utf8')

		expect(app).toContain('cellWidgets: { translatedName: TranslatedNameCell }')
	})
})
