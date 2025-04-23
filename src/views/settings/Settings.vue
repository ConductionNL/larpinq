<template>
	<div>
		<NcSettingsSection
			name="Larping App"
			description="A central place for managing your LARP events and characters"
			doc-url="https://docs.larpingapp.com" />

		<NcSettingsSection
			name="Data storage"
			description="Configure where to store your publication data">
			<div v-if="!loading">
				<!-- Warning if OpenRegister is not installed -->
				<NcNoteCard v-if="!settings.openRegisters" type="warning">
					Open Register is not installed. Please install it to use the Open Catalogi app with full functionality.
				</NcNoteCard>

				<!-- Register Selection -->
				<div class="register-selection">
					<h3>Register</h3>
					<p>Select the register to store all your LARP data</p>

					<NcSelect
						v-model="selectedRegister"
						:options="registerOptions"
						input-label="Register"
						:disabled="loading || !settings.larpRegisters"
						@change="handleRegisterChange" />
				</div>

				<!-- Warning if selected register has no schemas -->
				<NcNoteCard v-if="selectedRegister && !hasSchemas" type="warning">
					The selected register has no schemas. Please create schemas in this register or select a different register.
				</NcNoteCard>

				<!-- Object Type Schema Configuration -->
				<div v-if="selectedRegister && hasSchemas" class="schema-configuration">
					<h3>Schema Configuration</h3>
					<p>Select which schema to use for each object type</p>

					<div v-for="objectType in settings.objectTypes" :key="objectType" class="object-type-section">
						<div class="object-type-header">
							<h4>{{ formatTitle(objectType) }}</h4>
						</div>

						<NcSelect
							v-model="configuration[objectType].schema"
							:options="schemaOptions"
							input-label="Schema"
							:disabled="loading" />
					</div>
				</div>

				<!-- Save Buttons -->
				<div class="button-container">
					<NcButton
						type="primary"
						:disabled="loading || saving || !selectedRegister || !hasSchemas"
						@click="saveAll">
						<template #icon>
							<NcLoadingIcon v-if="saving" :size="20" />
							<Save v-else :size="20" />
						</template>
						Save Configuration
					</NcButton>
				</div>
			</div>

			<!-- Loading State -->
			<NcLoadingIcon v-else
				class="loading-icon"
				:size="64"
				appearance="dark" />
		</NcSettingsSection>
	</div>
</template>

<script>
import { defineComponent } from 'vue'
import {
	NcSettingsSection,
	NcNoteCard,
	NcSelect,
	NcButton,
	NcLoadingIcon,
} from '@nextcloud/vue'
import Save from 'vue-material-design-icons/ContentSave.vue'
import Download from 'vue-material-design-icons/Download.vue'

/**
 * @class Settings
 * @module Components
 * @package
 * @category LarpingApp
 * @package LarpingApp
 * @version 1.0.0
 * @license EUPL-1.2
 * @author Claude AI
 * @copyright 2023 Conduction
 * @link https://github.com/LarpingApp/larpingapp
 *
 * Settings component for the Larping App that allows users to configure
 * data storage options for different object types using Larp Registers.
 */
export default defineComponent({
	name: 'Settings',
	components: {
		NcSettingsSection,
		NcNoteCard,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		Save,
		Download,
	},

	/**
	 * Component data
	 *
	 * @return {object} Component data
	 */
	data() {
		return {
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
			selectedRegister: null,
			configuration: {},
			schemaOptions: [],
		}
	},

	computed: {
		/**
		 * Generates options for register selection dropdown
		 *
		 * @return {Array<object>} Array of register options with label and value
		 */
		registerOptions() {
			return this.settings.availableRegisters.map(register => ({
				label: register.title,
				value: register.id.toString(),
			}))
		},

		/**
		 * Determines if the selected register has schemas
		 *
		 * @return {boolean} True if the selected register has schemas, false otherwise
		 */
		hasSchemas() {
			if (!this.selectedRegister) return false

			const register = this.settings.availableRegisters.find(
				r => r.id.toString() === this.selectedRegister.value,
			)

			return register && Array.isArray(register.schemas) && register.schemas.length > 0
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
		 * Loads settings from the backend API and initializes the configuration
		 *
		 * @async
		 * @return {Promise<void>}
		 */
		async loadSettings() {
			try {
				const response = await fetch('/index.php/apps/larpingapp/api/settings')
				const data = await response.json()
				this.settings = data

				// Initialize configuration object
				this.initializeConfiguration()

				// Find and select the LARP register if it exists
				this.autoSelectLarpingAppRegister()

				this.loading = false
			} catch (error) {
				console.error('Failed to load settings:', error)
			}
		},

		/**
		 * Initializes the configuration object based on existing settings
		 */
		initializeConfiguration() {
			// Create empty configuration for each object type
			this.settings.objectTypes.forEach(type => {
				const registerId = this.settings.configuration[`${type}_register`] || ''
				const schemaId = this.settings.configuration[`${type}_schema`] || ''

				this.configuration[type] = {
					schema: null,
				}

				// If we have existing configuration, use it to set the selected register
				if (registerId && !this.selectedRegister) {
					const register = this.settings.availableRegisters.find(r => r.id.toString() === registerId)
					if (register) {
						this.selectedRegister = {
							label: register.title,
							value: register.id.toString(),
						}
						this.updateSchemaOptions(register.id.toString())
					}
				}

				// If we have a schema configured, set it
				if (schemaId && this.selectedRegister) {
					const register = this.settings.availableRegisters.find(
						r => r.id.toString() === this.selectedRegister.value,
					)
					if (register && Array.isArray(register.schemas)) {
						const schema = register.schemas.find(s => s.id.toString() === schemaId)
						if (schema) {
							this.configuration[type].schema = {
								label: schema.title,
								value: schema.id.toString(),
							}
						}
					}
				}
			})
		},

		/**
		 * Automatically selects the larpingapp register if it exists
		 */
		autoSelectLarpingAppRegister() {
			// Look for a register with "larpingapp" in the name
			const larpingAppRegister = this.settings.availableRegisters.find(
				register => register.title.toLowerCase().includes('larp'),
			)

			if (larpingAppRegister) {
				this.selectedRegister = {
					label: larpingAppRegister.title,
					value: larpingAppRegister.id.toString(),
				}
				this.updateSchemaOptions(larpingAppRegister.id.toString())

				// Only try to auto-select schemas if the register has schemas
				if (Array.isArray(larpingAppRegister.schemas)) {
					this.autoSelectMatchingSchemas(larpingAppRegister)
				}
			} else if (this.settings.availableRegisters.length > 0 && !this.selectedRegister) {
				// If no Larping App register but we have registers, select the first one
				const firstRegister = this.settings.availableRegisters[0]
				this.selectedRegister = {
					label: firstRegister.title,
					value: firstRegister.id.toString(),
				}
				this.updateSchemaOptions(firstRegister.id.toString())

				// Only try to auto-select schemas if the register has schemas
				if (Array.isArray(firstRegister.schemas)) {
					this.autoSelectMatchingSchemas(firstRegister)
				}
			}
		},

		/**
		 * Auto-selects schemas that match object type names
		 *
		 * @param {object} register - The selected register object
		 */
		autoSelectMatchingSchemas(register) {
			// Only proceed if register has schemas array
			if (!register || !Array.isArray(register.schemas)) {
				return
			}

			this.settings.objectTypes.forEach(type => {
				// Look for a schema with the same name as the object type
				const matchingSchema = register.schemas.find(
					schema => schema.title.toLowerCase() === type.toLowerCase(),
				)

				if (matchingSchema) {
					this.configuration[type].schema = {
						label: matchingSchema.title,
						value: matchingSchema.id.toString(),
					}
				}
			})
		},

		/**
		 * Updates schema options based on the selected register
		 *
		 * @param {string} registerId - The ID of the selected register
		 */
		updateSchemaOptions(registerId) {
			const register = this.settings.availableRegisters.find(r => r.id.toString() === registerId)
			if (register && Array.isArray(register.schemas)) {
				this.schemaOptions = register.schemas.map(schema => ({
					label: schema.title,
					value: schema.id.toString(),
				}))
			} else {
				this.schemaOptions = []
			}
		},

		/**
		 * Formats an object type string to title case
		 *
		 * @param {string} objectType - The object type to format
		 * @return {string} The formatted title
		 */
		formatTitle(objectType) {
			return objectType.charAt(0).toUpperCase() + objectType.slice(1)
		},

		/**
		 * Handles register change event
		 */
		handleRegisterChange() {
			if (this.selectedRegister) {
				// Update schema options for the new register
				this.updateSchemaOptions(this.selectedRegister.value)

				// Reset all schema selections
				this.settings.objectTypes.forEach(type => {
					this.configuration[type].schema = null
				})

				// Auto-select matching schemas
				const register = this.settings.availableRegisters.find(
					r => r.id.toString() === this.selectedRegister.value,
				)
				if (register && Array.isArray(register.schemas)) {
					this.autoSelectMatchingSchemas(register)
				}
			}
		},

		/**
		 * Saves all configuration settings to the backend
		 *
		 * @async
		 * @return {Promise<void>}
		 */
		async saveAll() {
			if (!this.selectedRegister || !this.hasSchemas) {
				return
			}

			this.saving = true
			try {
				const configToSave = {}

				// Set all object types to use larpregister as source
				Object.entries(this.configuration).forEach(([type, config]) => {
					// Always use openregister as source
					configToSave[`${type}_source`] = 'openregister'

					// Set the register ID for all object types
					configToSave[`${type}_register`] = this.selectedRegister.value

					// Set the schema ID if selected
					configToSave[`${type}_schema`] = config.schema ? config.schema.value : ''
				})

				// Send configuration to backend
				await fetch('/index.php/apps/larpingapp/api/settings', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify(configToSave),
				})
			} catch (error) {
				console.error('Failed to save settings:', error)
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
				const response = await fetch('/index.php/apps/larpingapp/api/settings/load')
				const data = await response.json()

				if (data.error) {
					this.configurationResults = { error: data.error }
				} else {
					this.configurationResults = { success: true }
					// Reload settings to reflect any changes
					await this.loadSettings()
				}
			} catch (error) {
				this.configurationResults = { error: 'Failed to load configuration: ' + error.message }
			} finally {
				this.loadingConfiguration = false
			}
		},
	},
})
</script>

<style scoped>
.load-configuration {
	margin-bottom: 2rem;
}

.configuration-results {
	margin-top: 1rem;
}

.register-selection {
	margin-bottom: 2rem;
	max-width: 400px;
}

.schema-configuration {
	margin-top: 2rem;
}

.object-type-section {
	margin-bottom: 1.5rem;
	display: flex;
	align-items: center;
	gap: 1rem;
}

.object-type-header {
	min-width: 150px;
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
