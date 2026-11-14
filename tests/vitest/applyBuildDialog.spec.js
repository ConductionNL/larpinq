/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/dialogs/ApplyBuildDialog.vue: loading the build report,
 * the change rows it shows, and applying the build to the character. Only
 * fetch is faked; the real characterBuilds service runs.
 *
 * @spec openspec/specs/character-builds/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import ApplyBuildDialog from '../../src/dialogs/ApplyBuildDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))

const REPORT = {
	character: { id: 'char-1' },
	lists: { skills: ['s-1', 's-2'], items: ['i-1'] },
	changes: {
		skills: { added: [{ name: 'Archery' }], removed: [{ name: 'Brawling' }] },
		items: { added: [], removed: [] },
		conditions: { added: [{ name: 'Blessed' }] },
	},
}

/**
 * Fake fetch answering each request in turn.
 *
 * @param {Array<{status: number, body: object}>} answers The answers, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({ url, method: options.method ?? 'GET', body: options.body ? JSON.parse(options.body) : undefined })
		const answer = answers.shift()
		return { ok: answer.status < 400, status: answer.status, json: async () => answer.body }
	}
	return calls
}

afterEach(() => {
	delete globalThis.fetch
})

describe('ApplyBuildDialog', () => {
	it.each([
		['the prop', { buildId: 'b-1', $route: { params: { id: 'b-2' } } }, 'b-1'],
		['the route', { buildId: '', $route: { params: { id: 'b-2' } } }, 'b-2'],
		['a placeholder skipped', { buildId: '@new', $route: { params: { id: 'b-2' } } }, 'b-2'],
	])('reads the build id from %s', (name, self, id) => {
		expect(ApplyBuildDialog.computed.id.call(self)).toBe(id)
	})

	it('loads the report and is ready', async () => {
		const calls = fakeFetch([{ status: 200, body: REPORT }])
		const self = { ...ApplyBuildDialog.data(), id: 'b-1' }

		await ApplyBuildDialog.mounted.call(self)

		expect(calls[0].url).toBe('/index.php/apps/larpinq/api/builds/b-1/report')
		expect(self.status).toBe('ready')
		expect(self.result).toEqual(REPORT)
	})

	it('fails when the report cannot be read', async () => {
		fakeFetch([{ status: 404, body: {} }])
		const self = { ...ApplyBuildDialog.data(), id: 'b-1' }

		await ApplyBuildDialog.mounted.call(self)

		expect(self.status).toBe('failed')
	})

	it('lists only the lists that change, with names added and removed', () => {
		expect(ApplyBuildDialog.computed.rows.call({ result: REPORT })).toEqual([
			{ list: 'skills', label: 'Skills', added: ['Archery'], removed: ['Brawling'] },
			{ list: 'conditions', label: 'Conditions', added: ['Blessed'], removed: [] },
		])
		expect(ApplyBuildDialog.computed.rows.call({ result: null })).toEqual([])
		expect(ApplyBuildDialog.computed.changed.call({ result: REPORT })).toBe(true)
		expect(ApplyBuildDialog.computed.changed.call({ result: { changes: {} } })).toBe(false)
	})

	it('applies the build lists to the character', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }])
		const self = { ...ApplyBuildDialog.data(), result: REPORT, refusal: 'old' }

		await ApplyBuildDialog.methods.apply.call(self)

		expect(calls).toEqual([
			{
				url: '/index.php/apps/openregister/api/objects/larpinq/character/char-1',
				method: 'PATCH',
				body: { skills: ['s-1', 's-2'], items: ['i-1'], conditions: [] },
			},
		])
		expect(self).toMatchObject({ status: 'applied', busy: false, refusal: '' })
	})

	it.each([
		['an XP shortfall', { errors: { budget: { shortfall: 3 } } }, 'The character sheet was not changed: short by 3 XP.'],
		['a refusal message', { message: 'Locked during the event.' }, 'The character sheet was not changed: Locked during the event.'],
		['no reason', {}, 'The character sheet was not changed: unknown error'],
	])('explains %s', async (name, body, refusal) => {
		fakeFetch([{ status: 422, body }])
		const self = { ...ApplyBuildDialog.data(), status: 'ready', result: REPORT }

		await ApplyBuildDialog.methods.apply.call(self)

		expect(self).toMatchObject({ status: 'ready', busy: false, refusal })
	})
})
