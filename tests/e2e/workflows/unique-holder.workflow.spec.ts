/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Workflow: a unique item or condition keeps one holder.
 *
 * Seeds a unique item held by one character and a unique condition on another
 * through the OpenRegister object API, then tries to give each to a second
 * character. The UniqueHolderListener vetoes the write inside OpenRegister's
 * save path, so the API answers 422 and the message names the current holder.
 * This is the live check the unit tests cannot give: that the listener is
 * registered, receives the real event and that OpenRegister turns the veto
 * into a refusal.
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	cleanupLedger,
	createObject,
	FixtureLedger,
	fixtureName,
	newApi,
	OR_BASE,
	REGISTER_ID,
	resolveSchemaIds,
	SCHEMA_IDS,
} from './fixtures.ts'

let api: APIRequestContext
const ledger = new FixtureLedger()

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
})

/**
 * PATCH one object and return the response.
 *
 * @param {string} type Object type.
 * @param {string} id Object UUID.
 * @param {Record<string, unknown>} body Fields to change.
 * @return {Promise<import('@playwright/test').APIResponse>} The response.
 */
async function patch(type: string, id: string, body: Record<string, unknown>) {
	return api.patch(`${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS[type]}/${id}`, {
		headers: { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
		data: body,
	})
}

test.describe('rules-unique-holder-enforcement', () => {
	test('a held unique item cannot go to a second character, and the holder is named', async () => {
		const crown = ledger.track(
			'item',
			await createObject(api, 'item', {
				name: fixtureName('Crown of Aldmoor'),
				unique: true,
			}),
		)
		const queenName = fixtureName('Queen Isolde')
		ledger.track(
			'character',
			await createObject(api, 'character', {
				name: queenName,
				items: [crown],
			}),
		)
		const bertram = ledger.track(
			'character',
			await createObject(api, 'character', {
				name: fixtureName('Sir Bertram'),
			}),
		)

		const res = await patch('character', bertram, { items: [crown] })

		expect(res.status()).toBe(422)
		const body = await res.json()
		expect(body.error).toContain(queenName)
		expect(body.errors.items[0].code).toBe('unique_item_held')
	})

	test('a non-unique item goes to many characters', async () => {
		const potion = ledger.track(
			'item',
			await createObject(api, 'item', {
				name: fixtureName('Healing potion'),
				unique: false,
			}),
		)
		ledger.track(
			'character',
			await createObject(api, 'character', {
				name: fixtureName('Queen'),
				items: [potion],
			}),
		)
		const bertram = ledger.track(
			'character',
			await createObject(api, 'character', { name: fixtureName('Bertram') }),
		)

		const res = await patch('character', bertram, { items: [potion] })

		expect(res.ok()).toBe(true)
	})

	test('a unique condition rests on one character', async () => {
		const curse = ledger.track(
			'condition',
			await createObject(api, 'condition', {
				name: fixtureName('Curse of the Ashen King'),
				unique: true,
			}),
		)
		const mirelaName = fixtureName('Mirela')
		ledger.track(
			'character',
			await createObject(api, 'character', {
				name: mirelaName,
				conditions: [curse],
			}),
		)
		const tomas = ledger.track(
			'character',
			await createObject(api, 'character', { name: fixtureName('Tomas') }),
		)

		const res = await patch('character', tomas, { conditions: [curse] })

		expect(res.status()).toBe(422)
		const body = await res.json()
		expect(body.errors.conditions[0].heldBy).toBe(mirelaName)
	})
})
