/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Tier-4 graduation (larpinq-manifest-tier-4): app components are resolved
 * only from the kind-tagged `src/registry.js` (ADR-036), and the empty
 * `src/customComponents.js` bridge is gone.
 *
 * The registry is read as source text: importing it pulls in every view and
 * dialog with their library imports, which this node suite does not load.
 *
 * @spec openspec/changes/larpinq-manifest-tier-4/specs/larpinq-manifest-tier-4/spec.md
 */

import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const SRC = resolve(__dirname, '../../src')

/** The kinds the ADR-036 resolver knows, plus `handler` for action functions. */
const KINDS = ['page', 'widget', 'tab', 'header', 'actions', 'section', 'modal', 'form-field', 'cell-renderer', 'handler', 'create-override']

/**
 * The top-level entries of the registry's default export, by key, with their source text.
 *
 * @return {Map<string, string>} key → entry source
 */
function registryEntries() {
	const source = readFileSync(resolve(SRC, 'registry.js'), 'utf8')
	const body = source.slice(source.indexOf('export default {'))
	const entries = new Map()
	const pattern = /^\t([A-Za-z][A-Za-z0-9]*): \{([^\n]*\}|[\s\S]*?\n\t\}),?$/gm
	let match
	while ((match = pattern.exec(body)) !== null) {
		entries.set(match[1], match[2])
	}
	return entries
}

describe('larpinq Tier-4 registry', () => {
	it('has no customComponents bridge file', () => {
		expect(existsSync(resolve(SRC, 'customComponents.js'))).toBe(false)
	})

	it('reads every entry of the registry', () => {
		const entries = registryEntries()

		expect(entries.size).toBeGreaterThan(15)
		expect([...entries.keys()]).toContain('WorldSwitcher')
		expect([...entries.keys()]).toContain('larpinqImportCampaign')
	})

	it('tags every entry with a known kind', () => {
		const untagged = []
		for (const [key, entry] of registryEntries()) {
			const kind = entry.match(/\bkind: '([a-z-]+)'/)
			if (kind === null || !KINDS.includes(kind[1])) {
				untagged.push(key)
			}
		}

		expect(untagged).toEqual([])
	})

	it('gives every entry a component or a handler to resolve', () => {
		const empty = []
		for (const [key, entry] of registryEntries()) {
			if (!/\b(component|handler): /.test(entry)) {
				empty.push(key)
			}
		}

		expect(empty).toEqual([])
	})

	it('registers no app component globally', () => {
		const main = readFileSync(resolve(SRC, 'main.js'), 'utf8')

		expect(main).not.toMatch(/\bapp\.component\(|Vue\.component\(/)
	})
})
