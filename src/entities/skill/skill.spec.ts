/* eslint-disable no-console */
import { Skill } from './skill'
import { mockSkill } from './skill.mock'

describe('Skill Store', () => {
	it('create Skill entity with full data', () => {
		const skill = new Skill(mockSkill()[0])

		expect(skill).toBeInstanceOf(Skill)
		expect(skill).toEqual(mockSkill()[0])

		expect(skill.validate().success).toBe(true)
	})

	it('create Skill entity with partial data', () => {
		const skill = new Skill(mockSkill()[1])

		expect(skill).toBeInstanceOf(Skill)
		expect(skill.id).toBe(mockSkill()[1].id)
		expect(skill.name).toBe(mockSkill()[1].name)

		expect(skill.validate().success).toBe(true)
	})

	it('create Skill entity with falsy data', () => {
		const skill = new Skill(mockSkill()[2])

		expect(skill).toBeInstanceOf(Skill)
		expect(skill).toEqual(mockSkill()[2])

		expect(skill.validate().success).toBe(false)
	})
})
