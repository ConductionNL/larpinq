/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useEffectStore } from './effect.js'
import { Effect, mockEffect } from '../../entities/index.js'

describe(
	'Effect Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets effect item correctly', () => {
				const store = useEffectStore()

				store.setEffectItem(mockEffect()[0])

				expect(store.effectItem).toBeInstanceOf(Effect)
				expect(store.effectItem).toEqual(mockEffect()[0])

				expect(store.effectItem.validate().success).toBe(true)
			},
		)

		it(
			'sets effect item with string "properties" property', () => {
				const store = useEffectStore()

				// stringify json data
				const mockData = mockEffect()[0]
				mockData.properties = JSON.stringify(mockData.properties)

				store.setEffectItem(mockData)

				expect(store.effectItem).toBeInstanceOf(Effect)
				expect(store.effectItem).toEqual(mockData)

				expect(store.effectItem.validate().success).toBe(true)
			},
		)

		it(
			'sets effect list correctly', () => {
				const store = useEffectStore()

				store.setEffectList(mockEffect())

				expect(store.effectList[0]).toBeInstanceOf(Effect)
				expect(store.effectList[0]).toEqual(mockEffect()[0])

				expect(store.effectList[0].validate().success).toBe(true)
			},
		)

		it(
			'get effect property from key', () => {
				const store = useEffectStore()

				store.setEffectItem(mockEffect()[0])
				store.setEffectDataKey('test')

				expect(store.effectItem).toEqual(mockEffect()[0])
				expect(store.effectDataKey).toBe('test')
			},
		)
	},
)
