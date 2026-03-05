<template>
	<NcAppSettingsDialog
		:open="open"
		:show-navigation="true"
		:name="t('larpingapp', 'Settings')"
		@update:open="$emit('update:open', $event)">
		<NcAppSettingsSection
			id="configuration"
			:name="t('larpingapp', 'Configuration')">
			<template #icon>
				<Cog :size="20" />
			</template>

			<p class="section-description">
				{{ t('larpingapp', 'Re-import configuration') }}
			</p>

			<NcButton
				:disabled="reimporting"
				@click="reimport">
				{{ reimporting ? t('larpingapp', 'Loading...') : t('larpingapp', 'Re-import configuration') }}
			</NcButton>

			<p v-if="reimportResult" class="reimport-result">
				{{ reimportResult }}
			</p>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { NcAppSettingsDialog, NcAppSettingsSection, NcButton } from '@nextcloud/vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'UserSettings',
	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcButton,
		Cog,
	},
	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			reimporting: false,
			reimportResult: null,
		}
	},
	methods: {
		async reimport() {
			this.reimporting = true
			this.reimportResult = null

			const settingsStore = useSettingsStore()
			const result = await settingsStore.reimportConfiguration()

			if (result && result.success) {
				this.reimportResult = t('larpingapp', 'Configuration re-imported successfully')
			} else {
				this.reimportResult = result?.message || 'Failed to reimport configuration'
			}

			this.reimporting = false
		},
	},
}
</script>

<style scoped>
.section-description {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.reimport-result {
	margin-top: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
