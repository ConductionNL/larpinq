export type TEffect = {
    id?: string
    name: string
    description?: string
    modifier?: number
    modification: 'positive' | 'negative'
    cumulative: 'cumulative' | 'non-cumulative'
	abilities: string[]
}
