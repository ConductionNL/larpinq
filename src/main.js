import Vue from 'vue'
import { PiniaVuePlugin } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { registerIcons } from '@conduction/nextcloud-vue'
import pinia from './pinia.js'
import router from './router/index.js'
import App from './App.vue'

// Library CSS — must be explicit import (webpack tree-shakes side-effect imports from aliased packages)
import '@conduction/nextcloud-vue/css/index.css'

// Register MDI icons used by LarpingApp schemas so CnIcon / CnIndexSidebar can render them
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

registerIcons({
	BriefcaseAccountOutline,
	AccountGroupOutline,
	Sword,
	SwordCross,
	ShieldSwordOutline,
	MagicStaff,
	CalendarMonthOutline,
	EmoticonSickOutline,
	Cog,
})

Vue.mixin({ methods: { t, n } })
Vue.use(PiniaVuePlugin)

new Vue({
	pinia,
	router,
	render: h => h(App),
}).$mount('#content')
