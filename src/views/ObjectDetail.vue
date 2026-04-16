<template>
	<div class="object-detail">
		<div class="object-detail__header">
			<NcButton @click="$router.push({ name: listRoute })">
				{{ t('larpingapp', 'Back to list') }}
			</NcButton>
			<h2 v-if="isNew">
				{{ t('larpingapp', newLabel) }}
			</h2>
			<h2 v-else>
				{{ objectData.name || objectData.title || t('larpingapp', singularLabel) }}
			</h2>
		</div>

		<NcLoadingIcon v-if="loading" />

		<!-- Edit / Create mode -->
		<div v-else-if="editing || isNew" class="object-detail__form">
			<div v-for="field in editableFields" :key="field.key" class="form-field">
				<label :for="'field-' + field.key">{{ field.label }}</label>
				<NcSelect
					v-if="field.enum"
					:id="'field-' + field.key"
					:value="formData[field.key]"
					:options="field.enum"
					@input="formData[field.key] = $event" />
				<NcCheckboxRadioSwitch
					v-else-if="field.type === 'boolean'"
					:checked.sync="formData[field.key]">
					{{ field.label }}
				</NcCheckboxRadioSwitch>
				<input
					v-else-if="field.type === 'integer' || field.type === 'number'"
					:id="'field-' + field.key"
					v-model.number="formData[field.key]"
					type="number">
				<textarea
					v-else-if="field.maxLength > 255"
					:id="'field-' + field.key"
					v-model="formData[field.key]"
					rows="4" />
				<input
					v-else
					:id="'field-' + field.key"
					v-model="formData[field.key]"
					type="text">
			</div>
			<div class="object-detail__actions">
				<NcButton type="primary" @click="onSave">
					{{ t('larpingapp', 'Save') }}
				</NcButton>
				<NcButton @click="onCancel">
					{{ t('larpingapp', 'Cancel') }}
				</NcButton>
			</div>
		</div>

		<!-- View mode -->
		<div v-else class="object-detail__info">
			<div class="object-detail__actions">
				<NcButton type="primary" @click="editing = true">
					{{ t('larpingapp', 'Edit') }}
				</NcButton>
				<NcButton type="error" @click="showDelete = true">
					{{ t('larpingapp', 'Delete') }}
				</NcButton>
			</div>

			<div class="info-grid">
				<div v-for="field in visibleFields" :key="field.key" class="info-field">
					<label>{{ field.label }}</label>
					<span>{{ formatValue(objectData[field.key], field) }}</span>
				</div>
			</div>
		</div>

		<!-- Delete warning dialog -->
		<NcDialog
			v-if="showDelete"
			:name="t('larpingapp', 'Delete')"
			@closing="showDelete = false">
			<p>{{ t('larpingapp', 'Are you sure you want to delete this?') }}</p>
			<template #actions>
				<NcButton @click="showDelete = false">
					{{ t('larpingapp', 'Cancel') }}
				</NcButton>
				<NcButton type="error" @click="confirmDelete">
					{{ t('larpingapp', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon, NcDialog, NcSelect, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { useObjectStore } from '../store/modules/object.js'

const SINGULAR_MAP = {
	character: 'Character',
	player: 'Player',
	ability: 'Ability',
	skill: 'Skill',
	item: 'Item',
	condition: 'Condition',
	effect: 'Effect',
	event: 'Event',
	setting: 'Setting',
}

const LIST_ROUTE_MAP = {
	character: 'Characters',
	player: 'Players',
	ability: 'Abilities',
	skill: 'Skills',
	item: 'Items',
	condition: 'Conditions',
	effect: 'Effects',
	event: 'Events',
	setting: 'GameSettings',
}

export default {
	name: 'ObjectDetail',
	components: {
		NcButton,
		NcLoadingIcon,
		NcDialog,
		NcSelect,
		NcCheckboxRadioSwitch,
	},
	props: {
		objectType: {
			type: String,
			required: true,
		},
		objectId: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
			editing: false,
			showDelete: false,
			formData: {},
			schema: null,
		}
	},
	computed: {
		objectStore() {
			return useObjectStore()
		},
		isNew() {
			return !this.objectId || this.objectId === 'new'
		},
		loading() {
			return this.objectStore.loading[this.objectType] || false
		},
		objectData() {
			if (this.isNew) return {}
			return this.objectStore.getObject(this.objectType, this.objectId) || {}
		},
		singularLabel() {
			return SINGULAR_MAP[this.objectType] || this.objectType
		},
		newLabel() {
			const singular = SINGULAR_MAP[this.objectType] || this.objectType
			return `New ${singular.toLowerCase()}`
		},
		listRoute() {
			return LIST_ROUTE_MAP[this.objectType] || 'Dashboard'
		},
		schemaProperties() {
			if (!this.schema?.properties) return []
			return Object.entries(this.schema.properties)
				.filter(([key]) => !['id', 'uuid', '_id'].includes(key))
				.map(([key, prop]) => ({
					key,
					label: prop.title || key.charAt(0).toUpperCase() + key.slice(1),
					type: prop.type,
					enum: prop.enum || null,
					maxLength: prop.maxLength || 255,
					visible: prop.visible !== false,
				}))
		},
		visibleFields() {
			return this.schemaProperties.filter(f => f.visible && f.type !== 'array')
		},
		editableFields() {
			return this.schemaProperties.filter(f => f.type !== 'array')
		},
	},
	async mounted() {
		this.schema = await this.objectStore.fetchSchema(this.objectType)
		if (!this.isNew) {
			await this.objectStore.fetchObject(this.objectType, this.objectId)
			this.formData = { ...this.objectData }
		}
	},
	methods: {
		formatValue(value, field) {
			if (value === undefined || value === null) return '-'
			if (field.type === 'boolean') return value ? t('larpingapp', 'Yes') : t('larpingapp', 'No')
			return String(value) || '-'
		},
		async onSave() {
			const result = await this.objectStore.saveObject(this.objectType, this.formData)
			if (result) {
				if (this.isNew) {
					const detailRoute = this.objectType.charAt(0).toUpperCase() + this.objectType.slice(1) + 'Detail'
					this.$router.push({ name: detailRoute, params: { id: result.id } })
				} else {
					await this.objectStore.fetchObject(this.objectType, this.objectId)
					this.editing = false
				}
			} else {
				const error = this.objectStore.getError(this.objectType)
				showError(error?.message || t('larpingapp', 'Failed to save. Please try again.'))
			}
		},
		onCancel() {
			if (this.isNew) {
				this.$router.push({ name: this.listRoute })
			} else {
				this.formData = { ...this.objectData }
				this.editing = false
			}
		},
		async confirmDelete() {
			this.showDelete = false
			const success = await this.objectStore.deleteObject(this.objectType, this.objectId)
			if (success) {
				this.$router.push({ name: this.listRoute })
			} else {
				const error = this.objectStore.getError(this.objectType)
				showError(error?.message || t('larpingapp', 'Failed to delete.'))
			}
		},
	},
}
</script>

<style scoped>
.object-detail {
	padding: 20px;
	max-width: 800px;
}

.object-detail__header {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-bottom: 20px;
}

.object-detail__actions {
	display: flex;
	gap: 12px;
	margin-bottom: 20px;
}

.object-detail__form .form-field {
	margin-bottom: 16px;
}

.object-detail__form label {
	display: block;
	font-weight: bold;
	margin-bottom: 4px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.object-detail__form input,
.object-detail__form textarea {
	width: 100%;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

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

.info-field span {
	margin: 0;
}
</style>
