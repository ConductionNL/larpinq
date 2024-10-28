/* eslint-disable no-console */
import { Condition } from './condition'
import { mockCondition } from './condition.mock'

describe('Condition Store', () => {
	it('create Condition entity with full data', () => {
		const condition = new Condition(mockCondition()[0])

		expect(condition).toBeInstanceOf(Condition)
		expect(condition).toEqual(mockCondition()[0])

		expect(condition.validate().success).toBe(true)
	})

	it('create Condition entity with partial data', () => {
		const condition = new Condition(mockCondition()[1])

		expect(condition).toBeInstanceOf(Condition)
		expect(condition.name).toBe(mockCondition()[1].name)

		expect(condition.validate().success).toBe(true)
	})

	it('create Condition entity with falsy data', () => {
		const condition = new Condition(mockCondition()[2])

		expect(condition).toBeInstanceOf(Condition)
		expect(condition).toEqual(mockCondition()[2])

		expect(condition.validate().success).toBe(false)
	})
})
