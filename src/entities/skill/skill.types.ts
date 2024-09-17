export type TSkill = {
    id?: string
    name: string
    description?: string
    effect?: string
    effects?: string[] // Array of Effect UUIDs
    requiredSkills?: string[] // Array of Skill UUIDs
    requiredStats?: string[] // Array of Stat UUIDs
    requiredConditions?: string[] // Array of Condition UUIDs
    requiredEffects?: string[] // Array of Effect UUIDs
    requiredScore?: number
}
