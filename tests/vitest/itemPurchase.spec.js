/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for buying an item with in-game money from the character page
 * (capability-rows-missing-requirements 3.2, REQ-RPG-001): the price and purse
 * rules in src/services/itemPurchase.js, the items the dialog offers, the
 * purchase request and its refusals, BuyItemDialog, and the manifest wiring.
 * Only fetch is faked; the real service runs under the dialog.
 *
 * @spec openspec/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import BuyItemDialog from '../../src/dialogs/BuyItemDialog.vue'
import manifest from '../../src/manifest.json'
import { buyItem, canPay, fetchPurchaseOptions, priceOf, purseOf } from '../../src/services/itemPurchase.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))

const here = path.dirname(fileURLToPath(import.meta.url))
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ICONS = fs.readFileSync(path.resolve(here, '../../src/icons.js'), 'utf8')

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

const MIRELA = { id: 'char-1', name: 'Mirela the Wanderer', setting: 'w-1', gold: 3, silver: 5, copper: 0, items: ['i-owned'] }
const ITEMS = [
	{ id: 'i-crown', name: 'Crown of Aldmoor', price: { gold: 500 } },
	{ id: 'i-rope', name: 'Rope', price: { silver: 2 } },
	{ id: 'i-owned', name: 'Lantern', price: { copper: 4 } },
	{ id: 'i-free', name: 'Pebble', price: { gold: 0, silver: 0, copper: 0 } },
	{ id: 'i-none', name: 'Map' },
	{ '@self': { id: 'i-bread' }, name: 'Bread', price: { copper: 3 } },
]

describe('prices and purses', () => {
	it.each([
		['the three coins', { price: { gold: 1, silver: 2, copper: 3 } }, { gold: 1, silver: 2, copper: 3 }],
		['missing coins as none', { price: { silver: 2 } }, { gold: 0, silver: 2, copper: 0 }],
		['no price', {}, null],
		['a price of nothing', { price: { gold: 0 } }, null],
		['a negative coin', { price: { gold: -1, silver: 3 } }, null],
		['a fraction', { price: { silver: 1.5 } }, null],
		['a list', { price: [1, 2] }, null],
	])('reads %s', (name, item, price) => {
		expect(priceOf(item)).toEqual(price)
	})

	it('reads a purse, treating missing, negative and non-numbers as none', () => {
		expect(purseOf({ gold: 3, silver: '5', copper: -2 })).toEqual({ gold: 3, silver: 5, copper: 0 })
		expect(purseOf(null)).toEqual({ gold: 0, silver: 0, copper: 0 })
	})

	it('pays each coin in its own kind', () => {
		const purse = { gold: 3, silver: 5, copper: 0 }
		expect(canPay(purse, { gold: 0, silver: 2, copper: 0 })).toBe(true)
		expect(canPay(purse, { gold: 0, silver: 5, copper: 0 })).toBe(true)
		expect(canPay(purse, { gold: 500, silver: 0, copper: 0 })).toBe(false)
		expect(canPay(purse, { gold: 0, silver: 0, copper: 1 })).toBe(false)
		expect(canPay(purse, null)).toBe(false)
	})
})

describe('fetchPurchaseOptions', () => {
	it('offers the priced items of the character\'s world it does not carry, cheapest first', async () => {
		const calls = fakeFetch([{ status: 200, body: MIRELA }, { status: 200, body: { results: ITEMS } }])

		const options = await fetchPurchaseOptions('char-1')

		expect(calls.map((call) => call.url)).toEqual([
			'/index.php/apps/openregister/api/objects/larpinq/character/char-1',
			'/index.php/apps/openregister/api/objects/larpinq/larping_item?_limit=500&setting=w-1',
		])
		expect(options.character).toEqual(MIRELA)
		expect(options.items.map((item) => item.name)).toEqual(['Bread', 'Rope', 'Crown of Aldmoor'])
		expect(options.items[0]).toEqual({ id: 'i-bread', name: 'Bread', price: { gold: 0, silver: 0, copper: 3 } })
	})

	it('reads every item when the character has no world', async () => {
		const calls = fakeFetch([{ status: 200, body: { id: 'char-2' } }, { status: 200, body: { results: [] } }])

		await fetchPurchaseOptions('char-2')

		expect(calls[1].url).toBe('/index.php/apps/openregister/api/objects/larpinq/larping_item?_limit=500')
	})

	it('offers nothing when the items cannot be read', async () => {
		fakeFetch([{ status: 200, body: MIRELA }, { status: 500, body: {} }])

		expect((await fetchPurchaseOptions('char-1')).items).toEqual([])
	})

	it('answers null without a character id or when the character cannot be read', async () => {
		expect(await fetchPurchaseOptions('')).toBeNull()
		fakeFetch([{ status: 404, body: {} }])
		expect(await fetchPurchaseOptions('char-x')).toBeNull()
	})
})

describe('buyItem', () => {
	it('posts the item to the character\'s purchases and returns the saved character', async () => {
		const calls = fakeFetch([{ status: 200, body: { ...MIRELA, silver: 3 } }])

		const outcome = await buyItem('char-1', 'i-rope')

		expect(calls).toEqual([{ url: '/index.php/apps/larpinq/api/characters/char-1/purchases', method: 'POST', body: { itemId: 'i-rope' } }])
		expect(outcome).toEqual({ ok: true, status: 200, message: '', character: { ...MIRELA, silver: 3 } })
	})

	it('passes the server\'s refusal on', async () => {
		fakeFetch([{ status: 409, body: { error: 'The character cannot pay this item' } }])

		expect(await buyItem('char-1', 'i-crown')).toEqual({ ok: false, status: 409, message: 'The character cannot pay this item', character: null })
	})
})

describe('BuyItemDialog', () => {
	it.each([
		['the prop', { characterId: 'char-1', $route: { params: { id: 'char-2' } } }, 'char-1'],
		['the route', { characterId: '', $route: { params: { id: 'char-2' } } }, 'char-2'],
		['a placeholder skipped', { characterId: '@objectId', $route: { params: { id: 'char-2' } } }, 'char-2'],
	])('reads the character id from %s', (name, self, id) => {
		expect(BuyItemDialog.computed.id.call(self)).toBe(id)
	})

	it('loads the character and the items for sale', async () => {
		fakeFetch([{ status: 200, body: MIRELA }, { status: 200, body: { results: ITEMS } }])
		const self = { ...BuyItemDialog.data(), id: 'char-1' }

		await BuyItemDialog.mounted.call(self)

		expect(self.status).toBe('ready')
		expect(self.character).toEqual(MIRELA)
		expect(self.items).toHaveLength(3)
	})

	it('fails when the character cannot be read', async () => {
		fakeFetch([{ status: 403, body: {} }])
		const self = { ...BuyItemDialog.data(), id: 'char-1' }

		await BuyItemDialog.mounted.call(self)

		expect(self.status).toBe('failed')
	})

	it('writes money in words without the coins there are none of', () => {
		const money = BuyItemDialog.methods.moneyText
		expect(money({ gold: 2, silver: 0, copper: 5 })).toBe('2 gold, 5 copper')
		expect(money({ gold: 0, silver: 3, copper: 0 })).toBe('3 silver')
		expect(money({ gold: 0, silver: 0, copper: 0 })).toBe('no money')
	})

	it('labels each item with its price and allows Buy only for an affordable choice', () => {
		const self = { items: [{ id: 'i-rope', name: 'Rope', price: { gold: 0, silver: 2, copper: 0 } }], moneyText: BuyItemDialog.methods.moneyText }
		expect(BuyItemDialog.computed.options.call(self)[0].label).toBe('Rope (2 silver)')

		const purse = { gold: 3, silver: 5, copper: 0 }
		expect(BuyItemDialog.computed.affordable.call({ purse, selected: { price: { gold: 0, silver: 2, copper: 0 } } })).toBe(true)
		expect(BuyItemDialog.computed.affordable.call({ purse, selected: { price: { gold: 500, silver: 0, copper: 0 } } })).toBe(false)
		expect(BuyItemDialog.computed.affordable.call({ purse, selected: null })).toBe(false)
		expect(BuyItemDialog.computed.buyable.call({ status: 'ready', affordable: true, busy: false })).toBe(true)
		expect(BuyItemDialog.computed.buyable.call({ status: 'ready', affordable: true, busy: true })).toBe(false)
		expect(BuyItemDialog.computed.buyable.call({ status: 'loading', affordable: true, busy: false })).toBe(false)
	})

	it('buys the rope: the item leaves the offer and the purse shows 3 silver', async () => {
		const calls = fakeFetch([{ status: 200, body: { ...MIRELA, silver: 3, items: ['i-owned', 'i-rope'] } }])
		const rope = { id: 'i-rope', name: 'Rope', price: { gold: 0, silver: 2, copper: 0 } }
		const self = { ...BuyItemDialog.data(), ...BuyItemDialog.methods, id: 'char-1', buyable: true, status: 'ready', character: MIRELA, items: [rope], selected: rope }

		await BuyItemDialog.methods.buy.call(self)

		expect(calls[0].body).toEqual({ itemId: 'i-rope' })
		expect(self.character.silver).toBe(3)
		expect(BuyItemDialog.computed.purse.call(self)).toEqual({ gold: 3, silver: 3, copper: 0 })
		expect(self).toMatchObject({ items: [], selected: null, bought: 'Rope', refusal: '', busy: false })
	})

	it.each([
		['the cannot-pay refusal', 'The character cannot pay this item', 'The character cannot pay this item.'],
		['the ownership refusal', 'Only the owner and game masters buy for this character', 'Only the owner and game masters buy for this character.'],
		['an unknown refusal', 'Storage is down', 'Nothing was bought: Storage is down'],
		['no reason', '', 'Nothing was bought: unknown error'],
	])('says %s and keeps the offer', async (name, error, refusal) => {
		fakeFetch([{ status: 409, body: { error } }])
		const crown = { id: 'i-crown', name: 'Crown of Aldmoor', price: { gold: 500, silver: 0, copper: 0 } }
		const self = { ...BuyItemDialog.data(), ...BuyItemDialog.methods, id: 'char-1', buyable: true, status: 'ready', character: MIRELA, items: [crown], selected: crown }

		await BuyItemDialog.methods.buy.call(self)

		expect(self).toMatchObject({ refusal, bought: '', busy: false, character: MIRELA, items: [crown], selected: crown })
	})

	it('posts nothing when Buy is not allowed', async () => {
		const calls = fakeFetch([])
		await BuyItemDialog.methods.buy.call({ buyable: false })
		expect(calls).toEqual([])
	})
})

describe('the Buy item action', () => {
	it('opens BuyItemDialog from the character page header, with a registered icon', () => {
		const page = manifest.pages.find((candidate) => candidate.id === 'CharacterDetail')
		const action = page.config.headerActions.find((candidate) => candidate.id === 'buy-item')

		expect(action).toEqual({ id: 'buy-item', type: 'open-modal', target: 'BuyItemDialog', label: 'Buy item', icon: 'CartOutline' })
		expect(REGISTRY).toMatch(/BuyItemDialog: \{\s*kind: 'modal',\s*component: BuyItemDialog,/)
		expect(ICONS).toMatch(/^\tCartOutline,$/m)
	})
})
