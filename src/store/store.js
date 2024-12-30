/* eslint-disable no-console */
// The store script handles app wide variables (or state), for the use of these variables and there governing concepts read the design.md
import pinia from '../pinia.js'
import { useObjectStore } from './modules/object.js'
import { useNavigationStore } from './modules/navigation.js'
import { useSearchStore } from './modules/search.js'
import { useAbilityStore } from './modules/ability.js'
import { useCharacterStore } from './modules/character.js'
import { useConditionStore } from './modules/condition.js'
import { useEffectStore } from './modules/effect.js'
import { useEventStore } from './modules/event.js'
import { useItemStore } from './modules/item.js'
import { usePlayerStore } from './modules/player.js'
import { useSkillStore } from './modules/skill.js'
import { useTemplateStore } from './modules/template.js'

const objectStore = useObjectStore(pinia)
const navigationStore = useNavigationStore(pinia)
const searchStore = useSearchStore(pinia)
const abilityStore = useAbilityStore(pinia)
const characterStore = useCharacterStore(pinia)
const conditionStore = useConditionStore(pinia)
const effectStore = useEffectStore(pinia)
const eventStore = useEventStore(pinia)
const itemStore = useItemStore(pinia)
const playerStore = usePlayerStore(pinia)
const skillStore = useSkillStore(pinia)
const templateStore = useTemplateStore(pinia)

export {
	// generic
	navigationStore,
	searchStore,
	objectStore,
	// feature-specific
	abilityStore,
	characterStore,
	conditionStore,
	effectStore,
	eventStore,
	itemStore,
	playerStore,
	skillStore,
	templateStore,
}
