/* eslint-disable no-console */
import { Effect } from './effect'
import { mockEffect } from './effect.mock'

describe('Effect Store', () => {
	it('create Effect entity with full data', () => {
		const effect = new Effect(mockEffect()[0])

		expect(effect).toBeInstanceOf(Effect)
		expect(effect).toEqual(mockEffect()[0])

		expect(effect.validate().success).toBe(true)
	})

	it('create Effect entity with partial data', () => {
		const effect = new Effect(mockEffect()[1])

		expect(effect).toBeInstanceOf(Effect)
		expect(effect).toEqual(mockEffect()[1])

		expect(effect.validate().success).toBe(true)
	})

	it('create Effect entity with falsy data', () => {
		const effect = new Effect(mockEffect()[2])

		expect(effect).toBeInstanceOf(Effect)
		expect(effect).toEqual(mockEffect()[2])

		expect(effect.validate().success).toBe(false)
	})
})
