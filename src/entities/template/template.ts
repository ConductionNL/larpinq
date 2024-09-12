import { SafeParseReturnType, z } from 'zod'
import { TTemplate } from './template.types'

export class Template implements TTemplate {

	public id: string
	public name: string
	public description: string
	public template: string

	constructor(template: TTemplate) {
		this.id = template.id || ''
		this.name = template.name || ''
		this.description = template.description || ''
		this.template = template.template || ''
	}

	/* istanbul ignore next */
	private hydrate(data: TTemplate) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.description = data?.description || ''
		this.template = data?.template || ''
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TTemplate, unknown> {
		const schema = z.object({
			name: z.string().min(1),
		})

		return schema.safeParse({ ...this })
	}

}
