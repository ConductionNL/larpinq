/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/characterBuilds.js and the manifest wiring of
 * characters-multiple-builds (REQ-CMB-001 to REQ-CMB-003): the check request,
 * the apply write and its refusal, the build page with its Check tab and
 * Apply action, and the Builds list on the character page.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import fragment from '../../src/manifest.d/characters-multiple-builds.json'
import manifest from '../../src/manifest.json'
import {
	applyBuild,
	fetchBuildReport,
	firstId,
	hasChanges,
} from '../../src/services/characterBuilds.js'

const here = path.dirname(fileURLToPath(import.meta.url))
// registry.js and icons.js import .vue files, which this node environment
// cannot load, so they are read as source text.
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ICONS = fs.readFileSync(path.resolve(here, '../../src/icons.js'), 'utf8')

describe('fetchBuildReport (REQ-CMB-002)', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('reads the check from the report endpoint', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: true,
			json: async () => ({ report: { valid: true } }),
		})
		expect(await fetchBuildReport('b1')).toEqual({ report: { valid: true } })
		expect(globalThis.fetch.mock.calls[0][0]).toBe(
			'/index.php/apps/larpinq/api/builds/b1/report',
		)
		expect(globalThis.fetch.mock.calls[0][1].method).toBeUndefined()
	})

	it('returns null for a build the user cannot read', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: false, status: 404 })
		expect(await fetchBuildReport('b1')).toBeNull()
	})

	it('does not ask without an id', async () => {
		globalThis.fetch = vi.fn()
		expect(await fetchBuildReport('')).toBeNull()
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('applyBuild (REQ-CMB-003)', () => {
	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('replaces the three lists of the character in one PATCH', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({ ok: true })
		const result = await applyBuild('c1', { skills: ['s1', 's2'], items: [] })
		expect(result.ok).toBe(true)
		const [url, init] = globalThis.fetch.mock.calls[0]
		expect(url).toBe(
			'/index.php/apps/openregister/api/objects/larpinq/character/c1',
		)
		expect(init.method).toBe('PATCH')
		expect(JSON.parse(init.body)).toEqual({
			skills: ['s1', 's2'],
			items: [],
			conditions: [],
		})
	})

	it('passes on the XP shortfall of a refused write', async () => {
		globalThis.fetch = vi.fn().mockResolvedValueOnce({
			ok: false,
			status: 400,
			json: async () => ({
				errors: {
					code: 'requirements_not_met',
					message: 'Insufficient XP and/or unmet skill requirements.',
					budget: { shortfall: 5 },
				},
			}),
		})
		expect(await applyBuild('c1', {})).toEqual({
			ok: false,
			message: 'Insufficient XP and/or unmet skill requirements.',
			shortfall: 5,
		})
	})
})

describe('helpers', () => {
	it('skips an unresolved @objectId prop', () => {
		expect(firstId('@objectId', '', 'b9')).toBe('b9')
		expect(firstId('', undefined)).toBe('')
	})

	it('knows when applying changes nothing', () => {
		const same = {
			skills: { added: [], removed: [] },
			items: { added: [], removed: [] },
		}
		expect(hasChanges(same)).toBe(false)
		expect(
			hasChanges({
				...same,
				conditions: { added: [], removed: [{ id: 'x' }] },
			}),
		).toBe(true)
	})
})

describe('the build page (REQ-CMB-001 to REQ-CMB-003)', () => {
	const detail = fragment.pages.find((p) => p.id === 'BuildDetail')

	it('opens a build with its fields and lists', () => {
		expect(detail.type).toBe('detail')
		expect(detail.route).toBe('/builds/:id')
		expect(detail.config.schema).toBe('larping_character_build')
		const ids = detail.config.widgets.map((w) => w.id)
		expect(detail.config.layout.map((cell) => cell.widgetId).sort()).toEqual(
			[...ids].sort(),
		)
	})

	it('shows the check in a Check tab', () => {
		const tab = detail.config.sidebar.tabs.find((t) => t.id === 'check')
		expect(tab.component).toBe('BuildReport')
		expect(REGISTRY).toMatch(
			/BuildReport: \{ kind: 'section', component: BuildReport \}/,
		)
	})

	it('offers Apply to game masters only', () => {
		const action = detail.config.headerActions.find(
			(a) => a.id === 'apply-build',
		)
		expect(action.type).toBe('open-modal')
		expect(action.target).toBe('ApplyBuildDialog')
		expect(action.visibleWhen).toEqual({
			endpoint: '/apps/larpinq/api/builds/apply-access',
			field: 'allowed',
			op: 'eq',
			value: true,
		})
		expect(REGISTRY).toMatch(
			/ApplyBuildDialog: \{\s*kind: 'modal',\s*component: ApplyBuildDialog/,
		)
	})

	it('uses registered icons only', () => {
		for (const icon of ['SourceBranch', 'CheckAll', 'ClipboardCheckOutline']) {
			expect(ICONS).toMatch(new RegExp(`^\\t${icon},$`, 'm'))
		}
	})
})

describe('the character page (REQ-CMB-001)', () => {
	it('lists the builds of the character', () => {
		const character = manifest.pages.find((p) => p.id === 'CharacterDetail')
		const builds = character.config.widgets.find((w) => w.id === 'char-builds')
		expect(builds.type).toBe('object-list')
		expect(builds.content.schema).toBe('larping_character_build')
		expect(builds.content.filter).toEqual({ character: '@objectId' })
		expect(builds.content.allowCreate).not.toBe(false)
		expect(
			character.config.layout.filter(
				(cell) => cell.widgetId === 'char-builds',
			),
		).toHaveLength(1)
	})
})
