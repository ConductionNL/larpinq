/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/LoreArticle.vue: loading the article in the route
 * and its world's lore tree, and opening another page. Only fetch is faked;
 * the real loreArticle service runs.
 *
 * @spec openspec/specs/world-lore/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import LoreArticle from '../../src/views/LoreArticle.vue'

vi.mock('@conduction/nextcloud-vue', () => ({ CnWikiPage: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))

const LORE = '/index.php/apps/openregister/api/objects/larpinq/larping_lore_page'

/**
 * Fake fetch answering by url, recording each url.
 *
 * @param {Record<string, {ok: boolean, body: object}>} answers Answers by url.
 * @return {Array<string>} The urls fetched.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url) => {
		calls.push(url)
		const answer = answers[url] ?? { ok: false, body: {} }
		return { ok: answer.ok, json: async () => answer.body }
	}
	return calls
}

afterEach(() => {
	delete globalThis.fetch
})

describe('LoreArticle load', () => {
	it('loads the article and the tree of its world', async () => {
		const calls = fakeFetch({
			[`${LORE}/p-1`]: { ok: true, body: { id: 'p-1', title: 'The Crown', setting: 'w-1' } },
			[`${LORE}?_limit=500&setting=w-1`]: {
				ok: true,
				body: {
					results: [
						{ id: 'p-2', title: 'Kings', parent: 'p-1' },
						{ id: 'p-1', title: 'The Crown' },
					],
				},
			},
		})
		const self = { ...LoreArticle.data(), $route: { params: { id: 'p-1' } } }

		await LoreArticle.methods.load.call(self)

		expect(calls).toEqual([`${LORE}/p-1`, `${LORE}?_limit=500&setting=w-1`])
		expect(self.article).toEqual({ id: 'p-1', title: 'The Crown', setting: 'w-1' })
		expect(self.tree).toEqual([
			{ id: 'p-1', title: 'The Crown', children: [{ id: 'p-2', title: 'Kings', children: [] }] },
		])
		expect(self.loading).toBe(false)
	})

	it('reads the whole tree for an article without a world', async () => {
		const calls = fakeFetch({
			[`${LORE}/p-1`]: { ok: true, body: { id: 'p-1', title: 'Loose page' } },
			[`${LORE}?_limit=500`]: { ok: true, body: { results: [] } },
		})
		const self = { ...LoreArticle.data(), $route: { params: { id: 'p-1' } } }

		await LoreArticle.methods.load.call(self)

		expect(calls[1]).toBe(`${LORE}?_limit=500`)
		expect(self.tree).toEqual([])
	})

	it('shows nothing for a missing article and asks for no tree', async () => {
		const calls = fakeFetch({})
		const self = { ...LoreArticle.data(), $route: { params: { id: 'gone' } } }

		await LoreArticle.methods.load.call(self)

		expect(calls).toEqual([`${LORE}/gone`])
		expect(self.article).toBeNull()
		expect(self.tree).toEqual([])
		expect(self.loading).toBe(false)
	})

	it('fetches nothing without an id in the route', async () => {
		const calls = fakeFetch({})
		const self = { ...LoreArticle.data(), $route: { params: {} } }

		await LoreArticle.methods.load.call(self)

		expect(calls).toEqual([])
		expect(self.article).toBeNull()
		expect(self.loading).toBe(false)
	})

	it('reloads whenever the route id changes', () => {
		const load = vi.fn()
		const watcher = LoreArticle.watch['$route.params.id']

		watcher.handler.call({ load })

		expect(watcher.immediate).toBe(true)
		expect(load).toHaveBeenCalledOnce()
	})
})

describe('LoreArticle openPage', () => {
	it.each([
		['another page', { id: 'p-2' }, [{ name: 'LoreArticle', params: { id: 'p-2' } }]],
		['the current page', { id: 'p-1' }, []],
		['a node without id', {}, []],
		['no node', null, []],
	])('%s', (name, node, pushed) => {
		const push = vi.fn()

		LoreArticle.methods.openPage.call({ $route: { params: { id: 'p-1' } }, $router: { push } }, node)

		expect(push.mock.calls.map((call) => call[0])).toEqual(pushed)
	})
})
