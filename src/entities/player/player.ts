import { SafeParseReturnType, z } from 'zod'
import { TPlayer } from './player.types'

export class Player implements TPlayer {

	public id: string
	public name: string
	public description: string

	constructor(player: TPlayer) {
		this.hydrate(player)
	}

	/* istanbul ignore next */
	private hydrate(data: TPlayer) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TPlayer, unknown> {
		const schema = z.object({
			name: z.string().min(1),
			description: z.string(),
		})

		return schema.safeParse({ ...this })
	}

}
