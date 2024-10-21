/* eslint-disable no-console */
import { Item } from './item'
import { mockItem } from './item.mock'

describe('Item Store', () => {
	it('create Item entity with full data', () => {
		const item = new Item(mockItem()[0])

		expect(item).toBeInstanceOf(Item)
		expect(item).toEqual(mockItem()[0])

		expect(item.validate().success).toBe(true)
	})

	it('create Item entity with partial data', () => {
		const item = new Item(mockItem()[1])

		expect(item).toBeInstanceOf(Item)
		expect(item.id).toBe(mockItem()[1].id)
		expect(item.name).toBe(mockItem()[1].name)

		expect(item.validate().success).toBe(true)
	})

	it('create Item entity with falsy data', () => {
		const item = new Item(mockItem()[2])

		expect(item).toBeInstanceOf(Item)
		expect(item).toEqual(mockItem()[2])

		expect(item.validate().success).toBe(false)
	})
})
