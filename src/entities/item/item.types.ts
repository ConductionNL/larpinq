export type TItem = {
    id: string
    name: string
    description?: string
    effect?: string
    effects?: string[] // Array of Effect UUIDs
    unique: boolean
    characters?: string[] // Array of Character UUIDs
}