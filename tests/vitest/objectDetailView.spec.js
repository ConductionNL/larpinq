/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/views/ObjectDetail.vue: which integration leaves an
 * object's page offers, given what OpenRegister has registered.
 */

import { afterEach, describe, expect, it } from 'vitest'
import ObjectDetail from '../../src/views/ObjectDetail.vue'

/**
 * The available leaves for an object type under a given registry.
 *
 * @param {string} objectType The object type.
 * @param {object|undefined} integrations The registry, or none.
 * @return {Array<string>} The leaves.
 */
function available(objectType, integrations) {
	globalThis.window = integrations === undefined ? {} : { OCA: { OpenRegister: { integrations } } }
	const candidateLeaves = ObjectDetail.computed.candidateLeaves.call({ objectType })
	return ObjectDetail.computed.availableLeaves.call({ candidateLeaves })
}

afterEach(() => {
	delete globalThis.window
})

describe('ObjectDetail candidateLeaves', () => {
	it.each([
		['character', ['photos']],
		['event', ['calendar', 'maps', 'forms']],
		['player', ['contacts']],
		['skill', []],
	])('%s', (objectType, leaves) => {
		expect(ObjectDetail.computed.candidateLeaves.call({ objectType })).toEqual(leaves)
	})

	it('defaults to a character', () => {
		expect(ObjectDetail.props.objectType.default).toBe('character')
		expect(ObjectDetail.props.config.default()).toEqual({})
	})
})

describe('ObjectDetail availableLeaves', () => {
	it('offers nothing without the registry', () => {
		expect(available('event', undefined)).toEqual([])
	})

	it('asks isRegistered() when the registry has it', () => {
		const asked = []
		const registry = {
			isRegistered: (leaf) => {
				asked.push(leaf)
				return leaf !== 'maps'
			},
			list: [{ id: 'maps' }],
		}

		expect(available('event', registry)).toEqual(['calendar', 'forms'])
		expect(asked).toEqual(['calendar', 'maps', 'forms'])
	})

	it('reads the list when there is no isRegistered()', () => {
		expect(available('event', { list: [{ id: 'maps' }, { id: 'deck' }] })).toEqual(['maps'])
	})

	it('offers nothing from a registry without either', () => {
		expect(available('character', {})).toEqual([])
	})
})
