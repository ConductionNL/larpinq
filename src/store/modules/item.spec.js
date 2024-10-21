/* eslint-disable no-console */
import { createPinia, setActivePinia } from 'pinia'

import { mockItem, Item } from '../../entities/index.js'
import { useItemStore } from './item.js'

describe(
	'Item Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets item item correctly', () => {
				const store = useItemStore()

				store.setItemItem(mockItem()[0])

				expect(store.itemItem).toBeInstanceOf(Item)
				expect(store.itemItem).toEqual(mockItem()[0])
				expect(store.itemItem.validate().success).toBe(true)

				store.setItemItem(mockItem()[1])

				expect(store.itemItem).toBeInstanceOf(Item)
				expect(store.itemItem).toEqual(mockItem()[1])
				expect(store.itemItem.validate().success).toBe(true)

				store.setItemItem(mockItem()[2])

				expect(store.itemItem).toBeInstanceOf(Item)
				expect(store.itemItem).toEqual(mockItem()[2])
				expect(store.itemItem.validate().success).toBe(false)
			},
		)

		it(
			'sets item list correctly', () => {
				const store = useItemStore()

				store.setItemList(mockItem())

				expect(store.itemList).toHaveLength(mockItem().length)

				expect(store.itemList[0]).toBeInstanceOf(Item)
				expect(store.itemList[0]).toEqual(mockItem()[0])
				expect(store.itemList[0].validate().success).toBe(true)

				expect(store.itemList[1]).toBeInstanceOf(Item)
				expect(store.itemList[1]).toEqual(mockItem()[1])
				expect(store.itemList[1].validate().success).toBe(true)

				expect(store.itemList[2]).toBeInstanceOf(Item)
				expect(store.itemList[2]).toEqual(mockItem()[2])
				expect(store.itemList[2].validate().success).toBe(false)
			},
		)
	},
)
