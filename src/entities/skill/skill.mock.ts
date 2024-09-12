import { Skill } from './skill'
import { TSkill } from './skill.types'

export const mockSkillData = (): TSkill[] => [
	{
		id: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		name: 'Healing LvL 1',
		description: 'Healers are what keeps the party going, when someone goes down a healer makes sure they get back up again.',
		effect: 'Character has access to level 1 healing spells',
		effects: [],
		requiredSkills: [],
		requiredStats: [],
		requiredConditions: [],
		requiredEffects: [],
		requiredScore: 10,
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		name: 'Swordsmanship',
		description: 'Masters of the blade, swordsmen are deadly in close combat and can turn the tide of battle.',
		effect: 'Character gains +2 to attack rolls with swords',
		effects: [],
		requiredSkills: [],
		requiredStats: [],
		requiredConditions: [],
		requiredEffects: [],
		requiredScore: 12,
	},
	{
		id: '15551d6f-44e3-43f3-a9d2-59e583c91eb0',
		name: 'Arcane Knowledge',
		description: 'Those versed in arcane knowledge can unravel the mysteries of magic and harness its power.',
		effect: 'Character can identify magical items and effects',
		effects: [],
		requiredSkills: [],
		requiredStats: [],
		requiredConditions: [],
		requiredEffects: [],
		requiredScore: 14,
	},
	{
		id: '0a3a0ffb-dc03-4aae-b207-0ed1502e60da',
		name: 'Stealth',
		description: 'Masters of stealth can move unseen and unheard, perfect for scouting or ambush.',
		effect: 'Character gains advantage on stealth checks',
		effects: [],
		requiredSkills: [],
		requiredStats: [],
		requiredConditions: [],
		requiredEffects: [],
		requiredScore: 13,
	},
]

export const mockSkill = (data: TSkill[] = mockSkillData()): TSkill[] => data.map(item => new Skill(item))
