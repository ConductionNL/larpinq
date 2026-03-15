import Vue from 'vue'
import Router from 'vue-router'
import Dashboard from '../views/dashboard/Dashboard.vue'
import CharacterList from '../views/characters/CharacterList.vue'
import CharacterDetail from '../views/characters/CharacterDetail.vue'
import PlayerList from '../views/players/PlayerList.vue'
import PlayerDetail from '../views/players/PlayerDetail.vue'
import SkillList from '../views/skills/SkillList.vue'
import SkillDetail from '../views/skills/SkillDetail.vue'
import ItemList from '../views/items/ItemList.vue'
import ItemDetail from '../views/items/ItemDetail.vue'
import EventList from '../views/events/EventList.vue'
import EventDetail from '../views/events/EventDetail.vue'
import AbilityList from '../views/abilities/AbilityList.vue'
import AbilityDetail from '../views/abilities/AbilityDetail.vue'
import ConditionList from '../views/conditions/ConditionList.vue'
import ConditionDetail from '../views/conditions/ConditionDetail.vue'
import EffectList from '../views/effects/EffectList.vue'
import EffectDetail from '../views/effects/EffectDetail.vue'
import TemplateList from '../views/templates/TemplateList.vue'
import TemplateDetail from '../views/templates/TemplateDetail.vue'
import Search from '../views/search/Search.vue'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	routes: [
		{ path: '/', name: 'Dashboard', component: Dashboard },
		{ path: '/characters', name: 'Characters', component: CharacterList },
		{ path: '/characters/:id', name: 'CharacterDetail', component: CharacterDetail, props: route => ({ characterId: route.params.id }) },
		{ path: '/players', name: 'Players', component: PlayerList },
		{ path: '/players/:id', name: 'PlayerDetail', component: PlayerDetail, props: route => ({ playerId: route.params.id }) },
		{ path: '/skills', name: 'Skills', component: SkillList },
		{ path: '/skills/:id', name: 'SkillDetail', component: SkillDetail, props: route => ({ skillId: route.params.id }) },
		{ path: '/items', name: 'Items', component: ItemList },
		{ path: '/items/:id', name: 'ItemDetail', component: ItemDetail, props: route => ({ itemId: route.params.id }) },
		{ path: '/events', name: 'Events', component: EventList },
		{ path: '/events/:id', name: 'EventDetail', component: EventDetail, props: route => ({ eventId: route.params.id }) },
		{ path: '/abilities', name: 'Abilities', component: AbilityList },
		{ path: '/abilities/:id', name: 'AbilityDetail', component: AbilityDetail, props: route => ({ abilityId: route.params.id }) },
		{ path: '/conditions', name: 'Conditions', component: ConditionList },
		{ path: '/conditions/:id', name: 'ConditionDetail', component: ConditionDetail, props: route => ({ conditionId: route.params.id }) },
		{ path: '/effects', name: 'Effects', component: EffectList },
		{ path: '/effects/:id', name: 'EffectDetail', component: EffectDetail, props: route => ({ effectId: route.params.id }) },
		{ path: '/templates', name: 'Templates', component: TemplateList },
		{ path: '/templates/:id', name: 'TemplateDetail', component: TemplateDetail, props: route => ({ templateId: route.params.id }) },
		{ path: '/search', name: 'Search', component: Search },
		{ path: '*', redirect: '/' },
	],
})
