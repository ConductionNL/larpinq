<template>
	<!-- Board LrBeheerinstellingen: the shell in the board look draws the
	     title, the path line and the Documentatie button; the version card and
	     the storage form are its two cards. -->
	<CnAdminSettingsShell
		appId="larpinq"
		appName="Larpinq"
		look="board"
		title="Larpinq"
		docUrl="https://conduction.gitbook.io/larpingapp-nextcloud/users"
		:breadcrumbs="breadcrumbs"
		:breadcrumbNote="t('larpinq', 'administrators only; you set the game rules and worlds in the app itself')"
		:versionDescription="t('larpinq', 'About this Larpinq installation.')"
		:showUpdateButton="false"
		reimportUrl="/index.php/apps/larpinq/api/settings/reimport"
		@reimported="onReimported"
		@reimportError="onReimportError">
		<div class="larpinq-admin">
			<NcNoteCard v-if="message" :type="messageType">
				{{ message }}
			</NcNoteCard>

			<CnSettingsSection
				:name="t('larpinq', 'Data storage')"
				:description="t('larpinq', 'Where Larpinq keeps the data of your game. Pick a source per kind, and for Open Register the register and schema.')"
				wide
				data-testid="larpinq-admin-storage">
				<div v-if="!loading">
					<NcNoteCard v-if="!settings.openRegisters" type="warning">
						{{ t('larpinq', 'Open Register is not installed. Some features might be unavailable.') }}
					</NcNoteCard>

					<div
						v-for="objectType in orderedObjectTypes"
						:key="objectType"
						class="larpinq-admin__row"
						:data-type="objectType">
						<!--
						  `@update:modelValue` merges with the v-model handler and runs
						  after the assignment, so the handler sees the new value.
						-->
						<NcSelect
							v-model="configuration[objectType].source"
							:options="sourceOptions"
							:inputLabel="typeLabel(objectType)"
							:clearable="false"
							:disabled="loading"
							@update:modelValue="handleSourceChange(objectType)" />
						<NcSelect
							v-if="configuration[objectType].source?.value === 'openregister'"
							v-model="configuration[objectType].register"
							:options="registerOptions"
							:inputLabel="t('larpinq', 'Register')"
							:clearable="false"
							:disabled="loading"
							@update:modelValue="handleRegisterChange(objectType)" />
						<NcSelect
							v-if="configuration[objectType].source?.value === 'openregister'"
							v-model="configuration[objectType].schema"
							:options="getSchemaOptions(configuration[objectType].register?.value)"
							:inputLabel="t('larpinq', 'Schema')"
							:clearable="false"
							:disabled="loading || !configuration[objectType].register" />
					</div>

					<div class="larpinq-admin__actions">
						<NcButton
							variant="primary"
							:disabled="loading || saving"
							@click="saveAll">
							<template #icon>
								<NcLoadingIcon v-if="saving" :size="20" />
								<Save v-else :size="20" />
							</template>
							{{ t('larpinq', 'Save All') }}
						</NcButton>
					</div>
				</div>

				<NcLoadingIcon
					v-else
					class="larpinq-admin__loading"
					:size="64"
					appearance="dark" />
			</CnSettingsSection>
		</div>
	</CnAdminSettingsShell>
</template>

<script>
import { CnAdminSettingsShell, CnSettingsSection } from '@conduction/nextcloud-vue'
import { generateUrl } from '@nextcloud/router'
import {
	NcButton,
	NcLoadingIcon,
	NcNoteCard,
	NcSelect,
} from '@nextcloud/vue'
import { defineComponent } from 'vue'
import Save from 'vue-material-design-icons/ContentSave.vue'
import logger from '../../logger.js'
import { buildStorageSettingsPayload } from '../../services/storageSettingsPayload.js'

const BOARD_ORDER = ['character', 'player', 'event', 'skill', 'ability', 'item', 'condition', 'effect', 'setting']

/**
 * @class Settings
 * @module Components
 * @category LarpingApp
 * @package LarpingApp
 * @version 1.0.0
 * @license EUPL-1.2
 * @author Claude AI
 * @copyright 2023 Conduction
 * @see https://github.com/LarpingApp/larpingapp
 *
 * Settings component for the Larping App that allows users to configure
 * data storage options for different object types using Larp Registers.
 */
export default defineComponent({
	// `vue/multi-word-component-names` (from vue/recommended, which
	// @nextcloud/eslint-config@9 extends) rejects a single-word name because it
	// can collide with a current or future HTML element. Nothing referenced this
	// component by name — it is mounted through the settings entry point — so
	// renaming it is inert at runtime and only affects devtools/warning output.
	name: 'AdminSettings',
	components: {
		CnAdminSettingsShell,
		CnSettingsSection,
		NcNoteCard,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		Save,
	},

	/**
	 * Component data
	 *
	 * @return {object} Component data
	 */
	data() {
		return {
			message: '',
			messageType: 'success',
			loading: true,
			saving: false,
			loadingConfiguration: false,
			configurationResults: null,
			settings: {
				objectTypes: [],
				larpRegisters: false,
				availableRegisters: [],
				configuration: {},
			},

			configuration: {},
			sourceOptions: [
				{ label: t('larpinq', 'Internal'), value: 'internal' },
				{ label: t('larpinq', 'Open Register'), value: 'openregister' },
			],
		}
	},

	computed: {
		/**
		 * The path line under the title (board LrBeheerinstellingen).
		 *
		 * @return {Array<{label: string, href?: string}>}
		 * @spec openspec/specs/admin-settings/spec.md
		 */
		breadcrumbs() {
			return [
				{ label: t('larpinq', 'Admin settings'), href: generateUrl('/settings/admin') },
				{ label: 'Larpinq' },
			]
		},

		/**
		 * The object types in the board's order; a type the server adds later
		 * follows at the end.
		 *
		 * @return {string[]}
		 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
		 */
		orderedObjectTypes() {
			const types = this.settings.objectTypes || []
			const known = BOARD_ORDER.filter((type) => types.includes(type))
			return [...known, ...types.filter((type) => !BOARD_ORDER.includes(type))]
		},

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

	/**
	 * Lifecycle hook that loads settings when component is created
	 */
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
			this.message = t('larpinq', 'Configuration re-imported successfully')
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
			this.message = error?.message || t('larpinq', 'Re-import failed')
			this.messageType = 'error'
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-8
		 */
		async loadSettings() {
			try {
				const response = await fetch('/index.php/apps/larpinq/api/settings')
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
		 * The label of an object type, as the app names it in its menu.
		 *
		 * @param {string} objectType The object-type slug (e.g. 'character').
		 * @return {string} The translated label, or the slug capitalised for an unknown type.
		 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
		 */
		typeLabel(objectType) {
			const labels = {
				character: t('larpinq', 'Characters'),
				player: t('larpinq', 'Players'),
				event: t('larpinq', 'Events'),
				skill: t('larpinq', 'Skills'),
				ability: t('larpinq', 'Abilities'),
				item: t('larpinq', 'Items'),
				condition: t('larpinq', 'Conditions'),
				effect: t('larpinq', 'Effects'),
				setting: t('larpinq', 'Worlds'),
			}
			return labels[objectType] || objectType.charAt(0).toUpperCase() + objectType.slice(1)
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
				const configToSave = buildStorageSettingsPayload(this.configuration)

				const response = await fetch(
					'/index.php/apps/larpinq/api/settings',
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
					this.message = t('larpinq', 'Settings saved successfully')
					this.messageType = 'success'
				} else {
					this.message = t('larpinq', 'Failed to save settings')
					this.messageType = 'error'
				}
			} catch (error) {
				logger.error('Failed to save settings', { error })
				this.message =
					error.message || t('larpinq', 'Failed to save settings')
				this.messageType = 'error'
			} finally {
				this.saving = false
			}
		},

		/**
		 * Loads configuration from the backend API
		 *
		 * @async
		 * @return {Promise<void>}
		 */
		async loadConfiguration() {
			this.loadingConfiguration = true
			this.configurationResults = null

			try {
				const response = await fetch(
					'/index.php/apps/larpingapp/api/settings/load',
				)
				const data = await response.json()

				if (data.error) {
					this.configurationResults = { error: data.error }
				} else {
					this.configurationResults = { success: true }
					// Reload settings to reflect any changes
					await this.loadSettings()
				}
			} catch (error) {
				this.configurationResults = {
					error: 'Failed to load configuration: ' + error.message,
				}
			} finally {
				this.loadingConfiguration = false
			}
		},
	},
})
</script>

<style scoped>
.larpinq-admin__row {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-bottom: 12px;
}

.larpinq-admin__row :deep(.v-select.select) {
	width: 100%;
	min-width: 0;
}

.larpinq-admin__actions {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
}

.larpinq-admin__loading {
	display: flex;
	justify-content: center;
	margin: 2rem 0;
}

@media (max-width: 600px) {
	.larpinq-admin__row {
		grid-template-columns: 1fr;
	}
}
</style>
