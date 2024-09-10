import { SafeParseReturnType, z } from 'zod'
import { TSkill } from './skill.types'

export class Skill implements TSkill {
	public id: string
	public name: string
	public description: string
	public effect: string
	public effects: string[]
	public requiredSkills: string[]
	public requiredStats: string[]
	public requiredConditions: string[]
	public requiredEffects: string[]
	public requiredScore: number

	constructor(skill: TSkill) {
		this.id = skill.id || ''
		this.name = skill.name || ''
		this.description = skill.description || ''
		this.effect = skill.effect || ''
		this.effects = skill.effects || []
		this.requiredSkills = skill.requiredSkills || []
		this.requiredStats = skill.requiredStats || []
		this.requiredConditions = skill.requiredConditions || []
		this.requiredEffects = skill.requiredEffects || []
		this.requiredScore = skill.requiredScore || 0
	}

	/* istanbul ignore next */
	private hydrate(data: TSkill) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
		this.effect = data?.effect || ''
		this.effects = data?.effects || []
		this.requiredSkills = data?.requiredSkills || []
		this.requiredStats = data?.requiredStats || []
		this.requiredConditions = data?.requiredConditions || []
		this.requiredEffects = data?.requiredEffects || []
		this.requiredScore = data?.requiredScore || 0
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TSkill, unknown> {
		const schema = z.object({
			name: z.string().min(1),
		})

		return schema.safeParse({ ...this })
	}
}