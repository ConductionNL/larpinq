export type TEffect = {
    id?: string
    name: string
    description?: string
    stat?: string // UUID of the stat
    modifier?: number
    modification: 'positive' | 'negative'
    cumulative: 'cumulative' | 'non-cumulative'
}
