/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useAbilityStore } from './ability.js'
import { Ability, mockAbility } from '../../entities/index.js'

describe(
	'Ability Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets ability item correctly', () => {
				const store = useAbilityStore()

				store.setAbilityItem(mockAbility()[0])

				expect(store.abilityItem).toBeInstanceOf(Ability)
				expect(store.abilityItem).toEqual(mockAbility()[0])

				expect(store.abilityItem.validate().success).toBe(true)
			})

		it(
			'sets ability list correctly', () => {
				const store = useAbilityStore()

				store.setAbilityList(mockAbility())

				expect(store.abilityList).toHaveLength(mockAbility().length)

				// list item 1
				expect(store.abilityList[0]).toBeInstanceOf(Ability)
				expect(store.abilityList[0]).toEqual(mockAbility()[0])

				expect(store.abilityList[0].validate().success).toBe(true)

				// list item 2
				expect(store.abilityList[1]).toBeInstanceOf(Ability)
				expect(store.abilityList[1]).toEqual(mockAbility()[1])

				expect(store.abilityList[1].validate().success).toBe(true)

				// list item 3
				expect(store.abilityList[2]).toBeInstanceOf(Ability)
				expect(store.abilityList[2]).toEqual(mockAbility()[2])

				expect(store.abilityList[2].validate().success).toBe(false)
			})
	})
