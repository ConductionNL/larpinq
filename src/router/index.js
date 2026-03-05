import Vue from 'vue'
import Router from 'vue-router'
import Dashboard from '../views/dashboard/DashboardIndex.vue'
import ObjectList from '../views/ObjectList.vue'
import ObjectDetail from '../views/ObjectDetail.vue'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	routes: [
		{ path: '/', name: 'Dashboard', component: Dashboard },
		{ path: '/characters', name: 'Characters', component: ObjectList, props: { objectType: 'character' } },
		{ path: '/characters/:id', name: 'CharacterDetail', component: ObjectDetail, props: route => ({ objectType: 'character', objectId: route.params.id }) },
		{ path: '/players', name: 'Players', component: ObjectList, props: { objectType: 'player' } },
		{ path: '/players/:id', name: 'PlayerDetail', component: ObjectDetail, props: route => ({ objectType: 'player', objectId: route.params.id }) },
		{ path: '/abilities', name: 'Abilities', component: ObjectList, props: { objectType: 'ability' } },
		{ path: '/abilities/:id', name: 'AbilityDetail', component: ObjectDetail, props: route => ({ objectType: 'ability', objectId: route.params.id }) },
		{ path: '/skills', name: 'Skills', component: ObjectList, props: { objectType: 'skill' } },
		{ path: '/skills/:id', name: 'SkillDetail', component: ObjectDetail, props: route => ({ objectType: 'skill', objectId: route.params.id }) },
		{ path: '/items', name: 'Items', component: ObjectList, props: { objectType: 'item' } },
		{ path: '/items/:id', name: 'ItemDetail', component: ObjectDetail, props: route => ({ objectType: 'item', objectId: route.params.id }) },
		{ path: '/conditions', name: 'Conditions', component: ObjectList, props: { objectType: 'condition' } },
		{ path: '/conditions/:id', name: 'ConditionDetail', component: ObjectDetail, props: route => ({ objectType: 'condition', objectId: route.params.id }) },
		{ path: '/effects', name: 'Effects', component: ObjectList, props: { objectType: 'effect' } },
		{ path: '/effects/:id', name: 'EffectDetail', component: ObjectDetail, props: route => ({ objectType: 'effect', objectId: route.params.id }) },
		{ path: '/events', name: 'Events', component: ObjectList, props: { objectType: 'event' } },
		{ path: '/events/:id', name: 'EventDetail', component: ObjectDetail, props: route => ({ objectType: 'event', objectId: route.params.id }) },
		{ path: '/game-settings', name: 'GameSettings', component: ObjectList, props: { objectType: 'setting' } },
		{ path: '/game-settings/:id', name: 'SettingDetail', component: ObjectDetail, props: route => ({ objectType: 'setting', objectId: route.params.id }) },
		{ path: '*', redirect: '/' },
	],
})
