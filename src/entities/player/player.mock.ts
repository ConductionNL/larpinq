import { Player } from './player'
import { TPlayer } from './player.types'

export const mockPlayerData = (): TPlayer[] => [
	{
		id: '1',
		name: 'Decat',
		description: 'fdsfsdf',
	},
	{
		id: '2',
		name: 'Woo',
		description: 'hfgujhrtjdfadzf',
	},
	{
		id: '3',
		name: 'Foo',
		description: 'jhgrtsfdfujgrujgd',
	},
]

export const mockPlayer = (data: TPlayer[] = mockPlayerData()): TPlayer[] => data.map(item => new Player(item))
