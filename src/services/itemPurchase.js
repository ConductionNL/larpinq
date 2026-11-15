/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Buying an item for a character with in-game money (REQ-RPG-001): the
 * character's purse, the items of its world that carry a price, and the
 * purchase itself. The server adds the item and takes the money in one write
 * and refuses a purchase the character cannot pay, so this module only reads
 * what the dialog shows and passes the server's answer on.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */

import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'
import { bodyOf } from './responseBody.js'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/** The coins, largest first, as the character and the item price store them. */
export const COINS = ['gold', 'silver', 'copper']

/**
 * A coin amount: a whole number of zero or more, anything else zero.
 *
 * @param {unknown} value The stored amount.
 * @return {number} The amount.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */
function amount(value) {
	const number = Number(value)
	return Number.isInteger(number) && number > 0 ? number : 0
}

/**
 * An item's price as the three coins, or null when it has none.
 *
 * The server sells only items whose price is an object with whole, non-negative
 * coins that add up to more than nothing; an item without that is not for sale.
 *
 * @param {object} item The item.
 * @return {{gold: number, silver: number, copper: number}|null} The price.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */
export function priceOf(item) {
	const raw = item?.price
	if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
		return null
	}
	const price = {}
	for (const coin of COINS) {
		const value = raw[coin] ?? 0
		if (!Number.isInteger(value) || value < 0) {
			return null
		}
		price[coin] = value
	}
	return COINS.some((coin) => price[coin] > 0) ? price : null
}

/**
 * The money a character holds.
 *
 * @param {object} character The character.
 * @return {{gold: number, silver: number, copper: number}} The purse.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */
export function purseOf(character) {
	return Object.fromEntries(COINS.map((coin) => [coin, amount(character?.[coin])]))
}

/**
 * Whether a purse pays a price. Each coin is paid in its own kind, as the
 * server pays it: silver does not stand in for missing gold.
 *
 * @param {{gold: number, silver: number, copper: number}} purse The money held.
 * @param {{gold: number, silver: number, copper: number}|null} price The price.
 * @return {boolean} True when every coin of the price is held.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */
export function canPay(purse, price) {
	return price !== null && COINS.every((coin) => (purse?.[coin] ?? 0) >= price[coin])
}

/**
 * The ids of the items a character carries.
 *
 * @param {object} character The character.
 * @return {Set<string>} The ids.
 */
function carried(character) {
	const items = Array.isArray(character?.items) ? character.items : []
	return new Set(items.map((entry) => String(entry && typeof entry === 'object' ? entry.id : entry)))
}

/**
 * The character and the items it can be offered: those of its world with a
 * price, that it does not carry yet, cheapest first by gold, silver, copper.
 *
 * @param {string} characterId The character.
 * @return {Promise<{character: object, items: Array<{id: string, name: string, price: object}>}|null>} Null when the character cannot be read.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */
export async function fetchPurchaseOptions(characterId) {
	if (!characterId) {
		return null
	}
	const characterResponse = await fetch(
		generateUrl(`${OBJECTS}/character/${encodeURIComponent(characterId)}`),
		{ headers: jsonHeaders() },
	)
	if (!characterResponse.ok) {
		return null
	}
	const character = await characterResponse.json()
	const query = new URLSearchParams({ _limit: '500' })
	if (character?.setting) {
		query.set('setting', String(character.setting))
	}
	const itemsResponse = await fetch(generateUrl(`${OBJECTS}/larping_item?${query.toString()}`), {
		headers: jsonHeaders(),
	})
	const body = itemsResponse.ok ? await itemsResponse.json() : {}
	const held = carried(character)
	const items = (Array.isArray(body?.results) ? body.results : [])
		.map((item) => ({ id: String(item?.id ?? item?.['@self']?.id ?? ''), name: String(item?.name ?? ''), price: priceOf(item) }))
		.filter((item) => item.id !== '' && item.price !== null && !held.has(item.id))
		.sort((a, b) => COINS.reduce((order, coin) => order || a.price[coin] - b.price[coin], 0) || a.name.localeCompare(b.name))
	return { character, items }
}

/**
 * Buy an item for a character.
 *
 * @param {string} characterId The character.
 * @param {string} itemId The item.
 * @return {Promise<{ok: boolean, status: number, message: string, character: object|null}>} The outcome; `message` is the server's refusal, in English.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
 */
export async function buyItem(characterId, itemId) {
	const response = await fetch(
		generateUrl(`/apps/larpinq/api/characters/${encodeURIComponent(characterId)}/purchases`),
		{ method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ itemId }) },
	)
	const body = await bodyOf(response)
	if (response.ok) {
		return { ok: true, status: response.status, message: '', character: body }
	}
	return { ok: false, status: response.status, message: String(body.error ?? body.message ?? ''), character: null }
}
