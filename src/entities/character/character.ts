import { SafeParseReturnType, z } from 'zod'
import { TCharacter } from './character.types'

export class Character implements TCharacter {

	public id: string
	public name: string
	public ocName: string
	public description: string
	public background: string
	public itemsAndMoney: string
	public notice: string
	public faith: string
	public slNotesPublic: string
	public slNotesPrivate: string
	public card: string
	public stats: string[]
	public gold: number
	public silver: number
	public copper: number
	public events: string[]
	public skills: string[]
	public conditions: string[]
	public type: 'player' | 'npc' | 'other'
	public approved: 'no' | 'approved'

	constructor(character: TCharacter) {
		this.id = character.id || ''
		this.name = character.name || ''
		this.ocName = character.ocName || ''
		this.description = character.description || ''
		this.background = character.background || ''
		this.itemsAndMoney = character.itemsAndMoney || ''
		this.notice = character.notice || ''
		this.faith = character.faith || ''
		this.slNotesPublic = character.slNotesPublic || ''
		this.slNotesPrivate = character.slNotesPrivate || ''
		this.card = character.card || ''
		this.stats = character.stats || []
		this.gold = character.gold || 0
		this.silver = character.silver || 0
		this.copper = character.copper || 0
		this.events = character.events || []
		this.skills = character.skills || []
		this.conditions = character.conditions || []
		this.type = character.type || 'player'
		this.approved = character.approved || 'no'
	}

	/* istanbul ignore next */ // Jest does not recognize the code coverage of these 2 methods
	private hydrate(data: TCharacter) {
		this.id = data?.id?.toString() || ''
		this.name = data?.name || ''
		this.ocName = data?.ocName || ''
		this.description = data?.description || ''
		this.background = data?.background || ''
		this.itemsAndMoney = data?.itemsAndMoney || ''
		this.notice = data?.notice || ''
		this.faith = data?.faith || ''
		this.slNotesPublic = data?.slNotesPublic || ''
		this.slNotesPrivate = data?.slNotesPrivate || ''
		this.card = data?.card || ''
		this.stats = data?.stats || []
		this.gold = data?.gold || 0
		this.silver = data?.silver || 0
		this.copper = data?.copper || 0
		this.events = data?.events || []
		this.skills = data?.skills || []
		this.conditions = data?.conditions || []
		this.type = data?.type || 'player'
		this.approved = data?.approved || 'no'
	}

	/* istanbul ignore next */
	public validate(): SafeParseReturnType<TCharacter, unknown> {
		// https://conduction.stoplight.io/docs/open-catalogi/hpksgr0u1cwj8-theme
		const schema = z.object({
			name: z.string().min(1),
		})

		const result = schema.safeParse({
			...this,
		})

		return result
	}

}