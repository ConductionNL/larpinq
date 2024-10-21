/* eslint-disable no-console */
import { createPinia, setActivePinia } from 'pinia'

import { usePlayerStore } from './player.js'
import { Player, mockPlayers } from '../../entities/index.js'

describe(
	'Player Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets player item correctly', () => {
				const store = usePlayerStore()

				store.setPlayerItem(mockPlayers()[0])

				expect(store.playerItem).toBeInstanceOf(Player)
				expect(store.playerItem).toEqual(mockPlayers()[0])
				expect(store.playerItem.validate().success).toBe(true)
			},
		)

		it(
			'sets player list correctly', () => {
				const store = usePlayerStore()

				store.setPlayerList(mockPlayers())

				expect(store.playerList).toHaveLength(mockPlayers().length)

				expect(store.playerList[0]).toBeInstanceOf(Player)
				expect(store.playerList[0]).toEqual(mockPlayers()[0])
				expect(store.playerList[0].validate().success).toBe(true)

				expect(store.playerList[1]).toBeInstanceOf(Player)
				expect(store.playerList[1]).toEqual(mockPlayers()[1])
				expect(store.playerList[1].validate().success).toBe(true)

				expect(store.playerList[2]).toBeInstanceOf(Player)
				expect(store.playerList[2]).toEqual(mockPlayers()[2])
				expect(store.playerList[2].validate().success).toBe(false)
			},
		)
	},
)
