import { SafeParseReturnType, z } from 'zod'
import { TEvent } from './event.types'

export class Event implements TEvent {

	public id: string
	public name: string
	public description: string
	public players: string[]
	public effects: string[]
	public startDate: string
	public endDate: string
	public location: string

	constructor(event: TEvent) {
		this.id = event.id || ''
		this.name = event.name || ''
		this.description = event.description || ''
		this.players = event.players || []
		this.effects = event.effects || []
		this.startDate = event.startDate || ''
		this.endDate = event.endDate || ''
		this.location = event.location || ''
	}

	/* istanbul ignore next */
	private hydrate(data: TEvent) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
		this.players = data?.players || []
		this.effects = data?.effects || []
		this.startDate = data?.startDate || ''
		this.endDate = data?.endDate || ''
		this.location = data?.location || ''
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TEvent, unknown> {
		const schema = z.object({
			name: z.string().min(1),
		})

		return schema.safeParse({ ...this })
	}

}
