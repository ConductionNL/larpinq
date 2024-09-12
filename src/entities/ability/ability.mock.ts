import { Ability } from './ability'
import { TAbility } from './ability.types'

export const mockAbilityData = (): TAbility[] => [
	{
		id: '56cf6db0-7c37-41a5-968b-d322c3f0da28',
		name: 'Experience points',
		description: "An experience point is a unit of measurement used in some tabletop role-playing games and role-playing video games to quantify a player character's life experience and progression through the game. Experience points are generally awarded for the completion of missions, overcoming obstacles and opponents, and for successful role-playing.",
		base: 0,
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		name: 'Strength',
		description: 'Represents the physical power and muscle of a character. It affects melee attack rolls, damage rolls for melee weapons, and various strength-based skill checks.',
		base: 10,
	},
	{
		id: '15551d6f-44e3-43f3-a9d2-59e583c91eb0',
		name: 'Mana',
		description: 'Represents the magical energy or power that a character can use to cast spells or perform magical abilities. It is often depleted when using magic and regenerates over time or through rest.',
		base: 20,
	},
	{
		id: '0a3a0ffb-dc03-4aae-b207-0ed1502e60da',
		name: 'Hit Points',
		description: "Represents the amount of damage a character can sustain before falling unconscious or dying. It's often calculated based on other stats like Constitution.",
		base: 15,
	},
]

export const mockAbility = (data: TAbility[] = mockAbilityData()): TAbility[] => data.map(item => new Ability(item))
