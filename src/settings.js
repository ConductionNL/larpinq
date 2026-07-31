import { createApp, h } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import AdminSettings from './views/settings/Settings.vue'

// Vue 3: `new Vue().$mount()` is `createApp().mount()`, and a global mixin is
// installed on the app instance rather than on the Vue constructor.
const app = createApp({
	render: () => h(AdminSettings),
})

app.mixin({ methods: { t, n } })
app.mount('#settings')
