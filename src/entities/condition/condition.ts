import { SafeParseReturnType, z } from 'zod'
import { TCondition } from './condition.types'

export class Condition implements TCondition {

	public id: string
	public name: string
	public description: string
	public effect: string
	public effects: string[]
	public unique: boolean

	constructor(condition: TCondition) {
		this.id = condition.id || ''
		this.name = condition.name || ''
		this.description = condition.description || ''
		this.effect = condition.effect || ''
		this.effects = condition.effects || []
		this.unique = condition.unique || false
	}

	/* istanbul ignore next */
	private hydrate(data: TCondition) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
		this.effect = data?.effect || ''
		this.effects = data?.effects || []
		this.unique = data?.unique || false
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TCondition, unknown> {
		const schema = z.object({
			name: z.string().min(1),
		})

		return schema.safeParse({ ...this })
	}

}
