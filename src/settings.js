import Vue from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import AdminSettings from './views/settings/Settings.vue'

Vue.mixin({ methods: { t, n } })

new Vue({
	render: h => h(AdminSettings),
}).$mount('#settings')
