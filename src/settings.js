import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { createApp, h } from 'vue'
import AdminSettings from './views/settings/Settings.vue'

// Library CSS. This entry is a separate bundle from main.js, so the import in
// main.js does not reach the admin page: without this line CnAdminSettingsShell
// renders unstyled on /settings/admin/larpinq.
import '@conduction/nextcloud-vue/css/index.css'

// Vue 3: `new Vue().$mount()` is `createApp().mount()`, and a global mixin is
// installed on the app instance rather than on the Vue constructor.
const app = createApp({
	render: () => h(AdminSettings),
})

app.mixin({ methods: { t, n } })
// The admin page has no CnAppRoot, which normally provides the look. Without
// it the shell draws the board look but its version card stays in the
// Nextcloud look.
app.provide('cnLook', 'board')
app.mount('#settings')
