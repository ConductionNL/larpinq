import { Effect } from './effect'
import { TEffect } from './effect.types'

export const mockEffectData = (): TEffect[] => [
	{
		id: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		name: '+ 5 Healing Mana',
		description: 'The character has additional healing mana',
		modifier: 5,
		modification: 'positive',
		cumulative: 'non-cumulative',
		abilities: ['123', '456'],
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		name: '- 2 Strength',
		description: "The character's strength is temporarily reduced",
		modifier: -2,
		modification: 'negative',
		cumulative: 'non-cumulative',
		abilities: ['123', '456'],
	},
	{
		id: '15551d6f-44e3-43f3-a9d2-59e583c91eb0',
		name: '+ 3 Agility',
		description: 'The character gains increased agility',
		modifier: 3,
		modification: 'positive',
		cumulative: 'cumulative',
		abilities: ['123', '456'],
	},
	{
		id: '0a3a0ffb-dc03-4aae-b207-0ed1502e60da',
		name: '+ 1 Intelligence',
		description: "The character's intelligence is slightly enhanced",
		modifier: 1,
		modification: 'positive',
		cumulative: 'non-cumulative',
		abilities: ['123', '456'],
	},
]

export const mockEffect = (data: TEffect[] = mockEffectData()): TEffect[] => data.map(item => new Effect(item))
