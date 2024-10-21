/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useConditionStore } from './condition.js'
import { Condition, mockConditions } from '../../entities/index.js'

describe(
	'Condition Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets condition item correctly', () => {
				const store = useConditionStore()

				store.setConditionItem(mockConditions()[0])

				expect(store.conditionItem).toBeInstanceOf(Condition)
				expect(store.conditionItem).toEqual(mockConditions()[0])

				expect(store.conditionItem.validate().success).toBe(true)
			})

		it(
			'sets conditions list correctly', () => {
				const store = useConditionStore()

				store.setConditionList(mockConditions())

				expect(store.conditionList).toHaveLength(mockConditions().length)

				// list item 1
				expect(store.conditionList[0]).toBeInstanceOf(Condition)
				expect(store.conditionList[0]).toEqual(mockConditions()[0])

				expect(store.conditionList[0].validate().success).toBe(true)

				// list item 2
				expect(store.conditionList[1]).toBeInstanceOf(Condition)
				expect(store.conditionList[1]).toEqual(mockConditions()[1])

				expect(store.conditionList[1].validate().success).toBe(true)

				// list item 3
				expect(store.conditionList[2]).toBeInstanceOf(Condition)
				expect(store.conditionList[2]).toEqual(mockConditions()[2])

				expect(store.conditionList[2].validate().success).toBe(false)
			})
	})
