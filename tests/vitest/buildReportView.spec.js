/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/BuildReport.vue: which build it reports on, which
 * requirements it lists as unmet, and how each one reads. Only fetch is
 * faked; the real characterBuilds service runs.
 *
 * @spec openspec/specs/character-builds/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import BuildReport from '../../src/views/BuildReport.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))

afterEach(() => {
	delete globalThis.fetch
})

describe('BuildReport buildId', () => {
	it.each([
		['the prop first', { objectId: 'b-prop', cnObjectContext: { objectId: 'b-ctx' }, $route: { params: { id: 'b-route' } } }, 'b-prop'],
		['then the object context', { objectId: '', cnObjectContext: { objectId: 'b-ctx' }, $route: { params: { id: 'b-route' } } }, 'b-ctx'],
		['then the route', { objectId: '', cnObjectContext: {}, $route: { params: { id: 'b-route' } } }, 'b-route'],
		['skipping a placeholder', { objectId: '@new', cnObjectContext: {}, $route: { params: { id: 'b-route' } } }, 'b-route'],
		['or nothing', { objectId: '', cnObjectContext: undefined, $route: undefined }, ''],
	])('%s', (name, self, id) => {
		expect(BuildReport.computed.buildId.call(self)).toBe(id)
	})
})

describe('BuildReport unmet', () => {
	it('lists only the unmet and unresolvable requirements', () => {
		const result = {
			report: {
				requirements: [
					{ skill: 'a', status: 'met' },
					{ skill: 'b', status: 'unmet' },
					{ skill: 'c', status: 'unresolvable' },
					{ skill: 'd', status: 'waived' },
				],
			},
		}

		expect(BuildReport.computed.unmet.call({ result }).map((entry) => entry.skill)).toEqual(['b', 'c'])
	})

	it('is empty without a report', () => {
		expect(BuildReport.computed.unmet.call({ result: null })).toEqual([])
		expect(BuildReport.computed.unmet.call({ result: { report: {} } })).toEqual([])
	})
})

describe('BuildReport load', () => {
	it('reads the report of the build', async () => {
		const urls = []
		globalThis.fetch = async (url) => {
			urls.push(url)
			return { ok: true, json: async () => ({ report: { requirements: [] } }) }
		}
		const self = { ...BuildReport.data(), buildId: 'b-1' }

		await BuildReport.methods.load.call(self)

		expect(urls).toEqual(['/index.php/apps/larpinq/api/builds/b-1/report'])
		expect(self.result).toEqual({ report: { requirements: [] } })
		expect(self.loading).toBe(false)
	})

	it('has no result when the report cannot be read', async () => {
		globalThis.fetch = async () => ({ ok: false, json: async () => ({}) })
		const self = { ...BuildReport.data(), buildId: 'b-1' }

		await BuildReport.methods.load.call(self)

		expect(self.result).toBeNull()
		expect(self.loading).toBe(false)
	})
})

describe('BuildReport requirementText', () => {
	it.each([
		[{ type: 'requiredStat', targetName: 'Strength', required: 4, current: 2 }, 'Strength must be 4, the build gives 2'],
		[{ type: 'skill', targetName: 'Archery' }, 'Needs Archery'],
		[{ type: 'skill', target: 'skill-uuid' }, 'Needs skill-uuid'],
		[{ skill: 'Herbalism' }, 'Needs Herbalism'],
	])('%j reads %s', (entry, text) => {
		expect(BuildReport.methods.requirementText(entry)).toBe(text)
	})
})
