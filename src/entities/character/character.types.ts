export type TCharacter = {
    id?: string
    name: string
    ocName: string
    description?: string
    background?: string
    itemsAndMoney?: string
    notice?: string
    faith?: string
    slNotesPublic?: string
    slNotesPrivate?: string
    card?: string
    stats?: string[] // Assuming stats are represented by UUIDs
    gold?: number
    silver?: number
    copper?: number
    events?: string[] // Array of Event UUIDs
    skills?: string[] // Array of Skill UUIDs
    conditions?: string[] // Array of Condition UUIDs
    type: 'player' | 'npc' | 'other'
    approved: 'no' | 'approved'
}
