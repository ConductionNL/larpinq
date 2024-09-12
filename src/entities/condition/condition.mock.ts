import { Condition } from './condition'
import { TCondition } from './condition.types'

export const mockConditionData = (): TCondition[] => [
	{
		id: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		name: 'Vampiric Demeanor',
		description: 'You crave the blood of other humanoid beings and feel powerful when drinking it',
		effect: 'Character may drink the blood of other humanoid beings, dealing 1 HP damage per 10 seconds of drinking. Character gains 1 Spiritual Mana per damage dealt. Character sustains 1 HP damage cumulative for each 24 hours in which character has not used this ability. E.g., 1 HP for day one, 2 HP on day 3',
		effects: [],
		unique: false,
		characters: [],
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		name: 'Lycanthropy',
		description: 'You transform into a werewolf during full moons, gaining enhanced strength but losing control',
		effect: 'During full moons, character gains +5 to Strength but must make a Will save (DC 15) every hour or attack the nearest creature. Transformation lasts for 8 hours.',
		effects: [],
		unique: false,
		characters: [],
	},
	{
		id: '15551d6f-44e3-43f3-a9d2-59e583c91eb0',
		name: 'Cursed Knowledge',
		description: 'You possess forbidden knowledge that slowly corrupts your mind',
		effect: 'Character gains +3 to all Knowledge checks but must make a Wisdom save (DC 12) daily or lose 1 Sanity point. If Sanity reaches 0, character becomes an NPC.',
		effects: [],
		unique: true,
		characters: [],
	},
	{
		id: '0a3a0ffb-dc03-4aae-b207-0ed1502e60da',
		name: 'Fey Touched',
		description: "You've been blessed (or cursed) by the fey, granting you magical abilities but making you vulnerable to iron",
		effect: 'Character can cast Charm Person once per day without using a spell slot, but takes 1d4 damage when touching iron objects.',
		effects: [],
		unique: false,
		characters: [],
	},
]

export const mockCondition = (data: TCondition[] = mockConditionData()): TCondition[] => data.map(item => new Condition(item))
