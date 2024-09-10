import { SafeParseReturnType, z } from 'zod'
import { TItem } from './item.types'

export class Item implements TItem {
	public id: string
	public name: string
	public description: string
	public effect: string
	public effects: string[]
	public unique: boolean

	constructor(item: TItem) {
		this.id = item.id || ''
		this.name = item.name || ''
		this.description = item.description || ''
		this.effect = item.effect || ''
		this.effects = item.effects || []
		this.unique = item.unique || false
	}

	/* istanbul ignore next */
	private hydrate(data: TItem) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
		this.effect = data?.effect || ''
		this.effects = data?.effects || []
		this.unique = data?.unique || false
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TItem, unknown> {
		const schema = z.object({
			name: z.string().min(1),
		})

		return schema.safeParse({ ...this })
	}
}