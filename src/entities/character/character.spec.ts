/* eslint-disable no-console */
import { Character } from './character'
import { mockCharacter } from './character.mock'

describe('Character Store', () => {
	it('create Character entity with full data', () => {
		const character = new Character(mockCharacter()[0])

		expect(character).toBeInstanceOf(Character)
		expect(character).toEqual(mockCharacter()[0])

		expect(character.validate().success).toBe(true)
	})

	it('create Character entity with partial data', () => {
		const character = new Character(mockCharacter()[1])

		expect(character).toBeInstanceOf(Character)
		expect(character).toEqual(mockCharacter()[1])

		expect(character.validate().success).toBe(true)
	})

	it('create Character entity with falsy data', () => {
		const character = new Character(mockCharacter()[2])

		expect(character).toBeInstanceOf(Character)
		expect(character).toEqual(mockCharacter()[2])

		expect(character.validate().success).toBe(false)
	})
})
