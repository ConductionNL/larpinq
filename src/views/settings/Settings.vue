<template>
	<CnAdminSettingsShell
		appId="larpingapp"
		appName="LarpingApp"
		docUrl="https://conduction.gitbook.io/larpingapp-nextcloud/users"
		reimportUrl="/index.php/apps/larpingapp/api/settings/reimport"
		@reimported="onReimported"
		@reimportError="onReimportError">
		<!-- Re-import Status -->
		<div v-if="message" class="actions-section">
			<NcNoteCard :type="messageType">
				{{ message }}
			</NcNoteCard>
		</div>

		<NcSettingsSection
			:name="t('larpingapp', 'Data storage')"
			:description="
				t('larpingapp', 'Configure where to store your LARP data')
			">
			<div v-if="!loading">
				<!-- Warning if OpenRegister is not installed but selected -->
				<NcNoteCard v-if="!settings.openRegisters" type="warning">
					{{
						t(
							'larpingapp',
							'Open Register is not installed. Some features might be unavailable.',
						)
					}}
				</NcNoteCard>

				<!-- Object Type Configuration -->
				<div
					v-for="objectType in settings.objectTypes"
					:key="objectType"
					class="object-type-section">
					<h3>{{ formatTitle(objectType) }}</h3>

					<div class="selection-container">
						<!--
						  `@change` is NOT an event NcSelect emits under
						  @nextcloud/vue v9 — its emits list is open / close /
						  update:modelValue / search* / option:*. The Vue-2
						  spelling silently never fired, so switching a source to
						  "Internal" would have left a stale register + schema
						  attached. The compiler merges this listener with the
						  v-model handler into an array and runs the v-model
						  assignment first, so the handler sees the NEW value.
						-->
						<NcSelect
							v-model="configuration[objectType].source"
							:options="sourceOptions"
							:inputLabel="t('larpingapp', 'Source')"
							:disabled="loading"
							@update:modelValue="handleSourceChange(objectType)" />

						<!-- Register Selection (only if OpenRegister is selected) -->
						<NcSelect
							v-if="
								configuration[objectType].source?.value
								=== 'openregister'
							"
							v-model="configuration[objectType].register"
							:options="registerOptions"
							:inputLabel="t('larpingapp', 'Register')"
							:disabled="loading"
							@update:modelValue="handleRegisterChange(objectType)" />

						<!-- Schema Selection (only if Register is selected) -->
						<NcSelect
							v-if="
								configuration[objectType].source?.value
									=== 'openregister'
								&& configuration[objectType].register
							"
							v-model="configuration[objectType].schema"
							:options="
								getSchemaOptions(
									configuration[objectType].register?.value,
								)
							"
							:inputLabel="t('larpingapp', 'Schema')"
							:disabled="loading" />
					</div>
				</div>

				<!-- Save Buttons -->
				<div class="button-container">
					<!--
					  @nextcloud/vue v9 repurposed `type` as the NATIVE button
					  type; the visual style moved to `variant`.
					-->
					<NcButton
						variant="primary"
						:disabled="loading || saving"
						@click="saveAll">
						<template #icon>
							<NcLoadingIcon v-if="saving" :size="20" />
							<Save v-else :size="20" />
						</template>
						{{ t('larpingapp', 'Save All') }}
					</NcButton>
				</div>
			</div>

			<!-- Loading State -->
			<NcLoadingIcon
				v-else
				class="loading-icon"
				:size="64"
				appearance="dark" />
		</NcSettingsSection>
	</CnAdminSettingsShell>
</template>

<script>
import { CnAdminSettingsShell } from '@conduction/nextcloud-vue'
import {
	NcButton,
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
	NcSettingsSection,
} from '@nextcloud/vue'
import { defineComponent } from 'vue'
import Save from 'vue-material-design-icons/ContentSave.vue'
import logger from '../../logger.js'

export default defineComponent({
	// `vue/multi-word-component-names` (from vue/recommended, which
	// @nextcloud/eslint-config@9 extends) rejects a single-word name because it
	// can collide with a current or future HTML element. Nothing referenced this
	// component by name — it is mounted through the settings entry point — so
	// renaming it is inert at runtime and only affects devtools/warning output.
	name: 'AdminSettings',
	components: {
		CnAdminSettingsShell,
		NcSettingsSection,
		NcNoteCard,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		Save,
	},

	data() {
		return {
			message: '',
			messageType: 'success',
			loading: true,
			saving: false,
			settings: {
				objectTypes: [],
				openRegisters: false,
				availableRegisters: [],
				configuration: {},
			},

			configuration: {},
			sourceOptions: [
				{ label: t('larpingapp', 'Internal'), value: 'internal' },
				{ label: t('larpingapp', 'Open Register'), value: 'openregister' },
			],
		}
	},

	computed: {
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-9
		 */
		registerOptions() {
			return (this.settings.availableRegisters || []).map((register) => ({
				label: register.title,
				value: register.id.toString(),
			}))
		},
	},

	async created() {
		await this.loadSettings()
	},

	methods: {
		/**
		 * Refresh the data-storage settings after the shell completes a re-import.
		 *
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-10
		 */
		async onReimported() {
			this.message = t('larpingapp', 'Configuration re-imported successfully')
			this.messageType = 'success'
			await this.loadSettings()
		},

		/**
		 * Surface a failed configuration re-import to the user.
		 *
		 * @param {Error|{message?: string}} error The failure reported by the shell.
		 * @return {void}
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-10
		 */
		onReimportError(error) {
			this.message = error?.message || t('larpingapp', 'Re-import failed')
			this.messageType = 'error'
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-8
		 */
		async loadSettings() {
			try {
				const response = await fetch(
					'/index.php/apps/larpingapp/api/settings',
				)
				const data = await response.json()
				this.settings = data

				// Initialize configuration for each object type based on existing config
				this.settings.objectTypes.forEach((type) => {
					const source =
						this.settings.configuration[`${type}_source`] || 'internal'
					const registerId =
						this.settings.configuration[`${type}_register`]
					const schemaId = this.settings.configuration[`${type}_schema`]

					this.configuration[type] = {
						source: this.sourceOptions.find(
							(option) => option.value === source,
						),
						register: registerId
							? {
									label: this.getRegisterLabel(registerId),
									value: registerId,
								}
							: null,
						schema: schemaId
							? {
									label: this.getSchemaLabel(registerId, schemaId),
									value: schemaId,
								}
							: null,
					}
				})

				this.loading = false
			} catch (error) {
				logger.error('Failed to load settings', { error })
			}
		},

		/**
		 * Resolve a register id to its human-readable title.
		 *
		 * @param {string} registerId The OpenRegister register id.
		 * @return {string} The register title, or '' when unknown.
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-9
		 */
		getRegisterLabel(registerId) {
			const register = this.settings.availableRegisters.find(
				(r) => r.id.toString() === registerId,
			)
			return register?.title || ''
		},

		/**
		 * Resolve a (register, schema) pair to the schema's human-readable title.
		 *
		 * @param {string} registerId The OpenRegister register id.
		 * @param {string} schemaId The schema id within that register.
		 * @return {string} The schema title, or '' when unknown.
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-9
		 */
		getSchemaLabel(registerId, schemaId) {
			const register = this.settings.availableRegisters.find(
				(r) => r.id.toString() === registerId,
			)
			const schema = register?.schemas.find(
				(s) => s.id.toString() === schemaId,
			)
			return schema?.title || ''
		},

		/**
		 * Capitalise an object-type slug for its section header.
		 *
		 * @param {string} objectType The object-type slug (e.g. 'character').
		 * @return {string} The slug with its first letter capitalised.
		 * @spec exclude Trivial capitalize-first-letter formatter for the
		 * object-type section header — display-only, no business logic.
		 */
		formatTitle(objectType) {
			return objectType.charAt(0).toUpperCase() + objectType.slice(1)
		},

		/**
		 * Build the NcSelect options for the schemas of one register.
		 *
		 * @param {string|undefined} registerId The selected register id.
		 * @return {Array<{label: string, value: string}>} Schema options, empty when no register is selected.
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-9
		 */
		getSchemaOptions(registerId) {
			if (!registerId) return []
			const register = this.settings.availableRegisters.find(
				(r) => r.id.toString() === registerId,
			)
			return (
				register?.schemas.map((schema) => ({
					label: schema.title,
					value: schema.id.toString(),
				})) || []
			)
		},

		/**
		 * Clear the register + schema selections when a type falls back to
		 * internal storage.
		 *
		 * @param {string} objectType The object-type slug whose source changed.
		 * @return {void}
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-9
		 */
		handleSourceChange(objectType) {
			const config = this.configuration[objectType]
			if (config.source.value === 'internal') {
				config.register = null
				config.schema = null
			}
		},

		/**
		 * Drop the schema selection when its register changes, so a schema from
		 * the previous register can never be saved against the new one.
		 *
		 * @param {string} objectType The object-type slug whose register changed.
		 * @return {void}
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-9
		 */
		handleRegisterChange(objectType) {
			this.configuration[objectType].schema = null
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-8
		 */
		async saveAll() {
			this.saving = true
			this.message = ''
			try {
				const configToSave = {}

				// Convert configuration to flat structure
				Object.entries(this.configuration).forEach(([type, config]) => {
					configToSave[`${type}_source`] = config.source.value
					if (config.source.value === 'openregister') {
						if (config.register) {
							configToSave[`${type}_register`] = config.register.value
						}
						if (config.schema) {
							configToSave[`${type}_schema`] = config.schema.value
						}
					}
				})

				const response = await fetch(
					'/index.php/apps/larpingapp/api/settings',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							requesttoken: OC.requestToken,
							'OCS-APIREQUEST': 'true',
						},
						body: JSON.stringify(configToSave),
					},
				)

				if (response.ok) {
					this.message = t('larpingapp', 'Settings saved successfully')
					this.messageType = 'success'
				} else {
					this.message = t('larpingapp', 'Failed to save settings')
					this.messageType = 'error'
				}
			} catch (error) {
				logger.error('Failed to save settings', { error })
				this.message =
					error.message || t('larpingapp', 'Failed to save settings')
				this.messageType = 'error'
			} finally {
				this.saving = false
			}
		},
	},
})
</script>

<style scoped>
.object-type-section {
	margin-bottom: 2rem;
}

.selection-container {
	display: flex;
	gap: 1rem;
	align-items: flex-start;
	margin-top: 0.5rem;
}

.button-container {
	margin-top: 2rem;
}

.loading-icon {
	display: flex;
	justify-content: center;
	margin: 2rem 0;
}
</style>
