/* eslint-disable no-console */
import { Player } from './player'
import { mockPlayer } from './player.mock'

describe('Directory Store', () => {
	it('create Player entity with full data', () => {
		const player = new Player(mockPlayer()[0])

		expect(player).toBeInstanceOf(Player)
		expect(player).toEqual(mockPlayer()[0])

		expect(player.validate().success).toBe(true)
	})

	it('create Player entity with partial data', () => {
		const player = new Player(mockPlayer()[1])

		expect(player).toBeInstanceOf(Player)
		expect(player).toEqual(mockPlayer()[1])
		expect(player.name).toBe(mockPlayer()[1].name)

		expect(player.validate().success).toBe(true)
	})

	it('create Player entity with falsy data', () => {
		const player = new Player(mockPlayer()[2])

		expect(player).toBeInstanceOf(Player)
		expect(player).toEqual(mockPlayer()[2])

		expect(player.validate().success).toBe(false)
	})
})
