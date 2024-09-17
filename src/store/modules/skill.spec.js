/* eslint-disable no-console */
import { createPinia, setActivePinia } from 'pinia'

import { useSkillStore } from './skill.js'
import { Skill, mockSkills } from '../../entities/index.js'

describe(
	'Skill Store', () => {
		beforeEach(
			() => {
				setActivePinia(createPinia())
			},
		)

		it(
			'sets skill item correctly', () => {
				const store = useSkillStore()

				store.setSkillItem(mockSkills()[0])

				expect(store.skillItem).toBeInstanceOf(Skill)
				expect(store.skillItem).toEqual(mockSkills()[0])
				expect(store.skillItem.validate().success).toBe(true)
			},
		)

		it(
			'sets skill list correctly', () => {
				const store = useSkillStore()

				store.setSkillList(mockSkills())

				expect(store.skillList).toHaveLength(mockSkills().length)

				expect(store.skillList[0]).toBeInstanceOf(Skill)
				expect(store.skillList[0]).toEqual(mockSkills()[0])
				expect(store.skillList[0].validate().success).toBe(true)

				expect(store.skillList[1]).toBeInstanceOf(Skill)
				expect(store.skillList[1]).toEqual(mockSkills()[1])
				expect(store.skillList[1].validate().success).toBe(true)

				expect(store.skillList[2]).toBeInstanceOf(Skill)
				expect(store.skillList[2]).toEqual(mockSkills()[2])
				expect(store.skillList[2].validate().success).toBe(false)
			},
		)
	},
)
