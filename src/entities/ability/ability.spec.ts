/* eslint-disable no-console */
import { Ability } from './ability'
import { mockAbility } from './ability.mock'

describe('Ability Store', () => {
	it('create Ability entity with full data', () => {
		const ability = new Ability(mockAbility()[0])

		expect(ability).toBeInstanceOf(Ability)
		expect(ability).toEqual(mockAbility()[0])

		expect(ability.validate().success).toBe(true)
	})

	it('create Ability entity with partial data', () => {
		const ability = new Ability(mockAbility()[1])

		expect(ability).toBeInstanceOf(Ability)
		expect(ability).toEqual(mockAbility()[1])

		expect(ability.validate().success).toBe(true)
	})

	it('create Ability entity with falsy data', () => {
		const ability = new Ability(mockAbility()[2])

		expect(ability).toBeInstanceOf(Ability)
		expect(ability).toEqual(mockAbility()[2])

		expect(ability.validate().success).toBe(false)
	})
})
