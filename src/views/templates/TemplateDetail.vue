<template>
	<CnDetailPage
		:title="templateData.name || t('larpingapp', 'Template')"
		:subtitle="t('larpingapp', 'Template')"
		:back-route="{ name: 'Templates' }"
		:back-label="t('larpingapp', 'Terug naar lijst')"
		:loading="loading"
		:sidebar="!isNew && !loading"
		object-type="larpingapp_template"
		:object-id="templateId || ''"
		:sidebar-props="sidebarProps">
		<template #header-actions>
			<NcActions :primary="true" :menu-name="t('larpingapp', 'Acties')">
				<template #icon>
					<DotsHorizontal :size="20" />
				</template>
				<NcActionButton @click="showDelete = true">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					{{ t('larpingapp', 'Verwijderen') }}
				</NcActionButton>
			</NcActions>
		</template>

		<CnDetailCard :title="t('larpingapp', 'Details')">
			<div class="info-grid">
				<div class="info-field">
					<label>{{ t('larpingapp', 'Beschrijving') }}</label>
					<span>{{ templateData.description || '-' }}</span>
				</div>
			</div>
		</CnDetailCard>

		<CnDetailCard v-if="templateData.template" :title="t('larpingapp', 'Content')">
			<NcGuestContent>
				<NcRichText
					:text="sanitizedTemplate"
					:autolink="true"
					:use-markdown="true" />
			</NcGuestContent>
		</CnDetailCard>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Template verwijderen')"
			@closing="showDelete = false">
			<p>
				{{ t('larpingapp', 'Weet je zeker dat je "{name}" wilt verwijderen?', { name: templateData.name }) }}
			</p>
			<template #actions>
				<NcButton @click="showDelete = false">
					{{ t('larpingapp', 'Annuleren') }}
				</NcButton>
				<NcButton type="error" @click="confirmDelete">
					{{ t('larpingapp', 'Verwijderen') }}
				</NcButton>
			</template>
		</NcDialog>
	</CnDetailPage>
</template>

<script>
import { NcActions, NcActionButton, NcButton, NcDialog, NcGuestContent, NcRichText } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { CnDetailPage, CnDetailCard } from '@conduction/nextcloud-vue'
import DOMPurify from 'dompurify'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { useObjectStore } from '../../store/modules/object.js'

export default {
	name: 'TemplateDetail',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		NcGuestContent,
		NcRichText,
		CnDetailPage,
		CnDetailCard,
		DotsHorizontal,
		TrashCanOutline,
	},
	props: {
		templateId: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
			showDelete: false,
		}
	},
	computed: {
		objectStore() {
			return useObjectStore()
		},
		isNew() {
			return !this.templateId || this.templateId === 'new'
		},
		loading() {
			return this.objectStore.loading?.template || false
		},
		templateData() {
			if (this.isNew) return {}
			return this.objectStore.getObject('template', this.templateId) || {}
		},
		sanitizedTemplate() {
			return DOMPurify.sanitize(this.templateData.template || '')
		},
		sidebarProps() {
			const config = this.objectStore.objectTypeRegistry?.template || {}
			return {
				register: config.register || '',
				schema: config.schema || '',
			}
		},
	},
	async mounted() {
		if (!this.isNew) {
			await this.objectStore.fetchObject('template', this.templateId)
		}
	},
	methods: {
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject('template', this.templateId)
			if (success) {
				this.$router.push({ name: 'Templates' })
			} else {
				const error = this.objectStore.getError('template')
				showError(error?.message || t('larpingapp', 'Template verwijderen mislukt.'))
			}
		},
	},
}
</script>

<style scoped>
.info-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.info-field {
	margin-bottom: 8px;
}

.info-field label {
	display: block;
	font-weight: bold;
	margin-bottom: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

:deep(#guest-content-vue) {
	margin: 20px 5px;
}
</style>
