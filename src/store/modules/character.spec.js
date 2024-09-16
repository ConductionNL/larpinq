/* eslint-disable no-console */
import { createPinia, setActivePinia } from 'pinia'
import { Character, mockCharacter } from '../../entities/index.js'
import { useCharacterStore } from './character.js'

describe(
	'Character Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets character item correctly', () => {
				const store = useCharacterStore()

				store.setCharacterItem(mockCharacter()[0])

				expect(store.characterItem).toBeInstanceOf(Character)
				expect(store.characterItem).toEqual(mockCharacter()[0])
				expect(store.characterItem.validate().success).toBe(true)

				store.setCharacterItem(mockCharacter()[1])

				expect(store.characterItem).toBeInstanceOf(Character)
				expect(store.characterItem).toEqual(mockCharacter()[1])
				expect(store.characterItem.validate().success).toBe(true)

				store.setCharacterItem(mockCharacter()[2])

				expect(store.characterItem).toBeInstanceOf(Character)
				expect(store.characterItem).toEqual(mockCharacter()[2])
				expect(store.characterItem.validate().success).toBe(false)
			},
		)
	},
)
