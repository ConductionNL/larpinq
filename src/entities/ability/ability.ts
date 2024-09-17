import { SafeParseReturnType, z } from 'zod'
import { TAbility } from './ability.types'

export class Ability implements TAbility {

	public id: string
	public name: string
	public description: string
	public base: number

	constructor(ability: TAbility) {
		this.id = ability.id || ''
		this.name = ability.name || ''
		this.description = ability.description || ''
		this.base = ability.base || 0
	}

	/* istanbul ignore next */
	private hydrate(data: TAbility) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
		this.base = data?.base || 0
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TAbility, unknown> {
		const schema = z.object({
			name: z.string().min(1),
		})

		return schema.safeParse({ ...this })
	}

}
