/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pins the object-tab mixin: the computed it adds reads the object id from
 * the prop, then the injected object context, then the route, and is empty
 * without any of them.
 */

import { describe, expect, it } from 'vitest'
import { objectTab, objectTabSource } from '../../src/mixins/objectTab.js'

/**
 * Evaluate the named computed of objectTab(name) on a fake instance.
 *
 * @param {string} name The computed's name.
 * @param {object} instance The instance fields.
 * @return {string} The id.
 */
function idFor(name, instance) {
	return objectTab(name).computed[name].call(instance)
}

describe('objectTab', () => {
	it('names the computed as asked and builds on the shared source', () => {
		const mixin = objectTab('characterId')
		expect(Object.keys(mixin.computed)).toEqual(['characterId'])
		expect(mixin.mixins).toEqual([objectTabSource])
	})

	it('prefers the prop', () => {
		expect(idFor('characterId', {
			objectId: 'prop-id',
			cnObjectContext: { objectId: 'ctx-id' },
			$route: { params: { id: 'route-id' } },
		})).toBe('prop-id')
	})

	it('falls back to the injected object context', () => {
		expect(idFor('eventId', {
			objectId: '',
			cnObjectContext: { objectId: 'ctx-id' },
			$route: { params: { id: 'route-id' } },
		})).toBe('ctx-id')
	})

	it('falls back to the route id, as a string', () => {
		expect(idFor('eventId', { objectId: '', cnObjectContext: {}, $route: { params: { id: 42 } } })).toBe('42')
	})

	it('is empty without a prop, context or route', () => {
		expect(idFor('eventId', { objectId: '', cnObjectContext: undefined })).toBe('')
	})
})

describe('objectTabSource', () => {
	it('injects the object context with an empty default and takes an objectId prop', () => {
		expect(objectTabSource.inject.cnObjectContext.default()).toEqual({})
		expect(objectTabSource.props.objectId).toEqual({ type: String, default: '' })
	})
})
