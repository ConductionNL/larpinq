<template>
	<div>
		<NcSettingsSection :name="'Larping App'" description="A central place for managing your LARP characters and game elements" doc-url="https://conduction.gitbook.io/larpingapp-nextcloud/users" />
		<NcSettingsSection :name="'Data storage'" description="Configure where to store your LARP data - either in Nextcloud's database or in external Open Registers">
			<div v-if="!loading">
				<div v-if="!openRegisterInstalled">
					<NcNoteCard type="info">
						You haven't installed Open Registers yet. We recommend installing it for additional storage options.
					</NcNoteCard>

					<NcButton
						type="primary"
						@click="openLink('/index.php/settings/apps/organization/openregister', '_blank')">
						<template #icon>
							<NcLoadingIcon v-if="loading || saving" :size="20" />
							<Restart v-if="!loading && !saving" :size="20" />
						</template>
						Install Open Registers
					</NcButton>
				</div>

				<div v-if="!openRegisterInstalled && (settingsData.ability_source === 'openregister' || settingsData.character_source === 'openregister' || settingsData.condition_source === 'openregister' || settingsData.effect_source === 'openregister' || settingsData.event_source === 'openregister' || settingsData.item_source === 'openregister' || settingsData.player_source === 'openregister' || settingsData.setting_source === 'openregister' || settingsData.skill_source === 'openregister' || settingsData.template_source === 'openregister')">
					<NcNoteCard type="warning">
						It appears you've selected Open Register as a storage option but haven't installed it yet. This may cause issues. Would you like to reset the settings?
					</NcNoteCard>
					<NcButton
						type="primary"
						@click="resetConfig()">
						<template #icon>
							<NcLoadingIcon v-if="loading || saving" :size="20" />
							<Restart v-if="!loading && !saving" :size="20" />
						</template>
						Reset
					</NcButton>
				</div>

				<div v-for="objectType in objectTypesList" :key="objectType.id">
					<h3>{{ objectType.title }}</h3>
					<p>{{ objectType.description }}</p>
					<NcButton v-if="objectType.helpLink" @click="openLink(objectType.helpLink, '_blank')">
						More information
					</NcButton>
					<div class="selectionContainer">
						<NcSelect v-bind="labelOptions"
							v-model="getDataProperty(objectType.id).selectedSource"
							required
							input-label="Source"
							:loading="getDataProperty(objectType.id).loading"
							:disabled="loading || getDataProperty(objectType.id).loading" />

						<NcSelect v-if="getDataProperty(objectType.id).selectedSource?.value === 'openregister'"
							v-bind="availableRegistersOptions"
							v-model="getDataProperty(objectType.id).selectedRegister"
							input-label="Register"
							:loading="getDataProperty(objectType.id).loading"
							:disabled="loading || getDataProperty(objectType.id).loading" />

						<NcSelect v-if="getDataProperty(objectType.id).selectedSource?.value === 'openregister' && getDataProperty(objectType.id).selectedRegister?.value"
							v-bind="getDataProperty(objectType.id).availableSchemas"
							v-model="getDataProperty(objectType.id).selectedSchema"
							input-label="Schema"
							:loading="getDataProperty(objectType.id).loading"
							:disabled="loading || getDataProperty(objectType.id).loading" />

						<NcButton
							type="primary"
							:disabled="loading || saving || getDataProperty(objectType.id).loading || !getDataProperty(objectType.id).selectedSource?.value || getDataProperty(objectType.id).selectedSource?.value === 'openregister' && (!getDataProperty(objectType.id).selectedRegister?.value || !getDataProperty(objectType.id).selectedSchema?.value)"
							@click="saveConfig(objectType.id)">
							<template #icon>
								<NcLoadingIcon v-if="loading || getDataProperty(objectType.id).loading" :size="20" />
								<Plus v-if="!loading && !getDataProperty(objectType.id).loading" :size="20" />
							</template>
							Save
						</NcButton>
					</div>
				</div>
				<NcButton
					type="primary"
					:disabled="saving"
					@click="saveAll()">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
						<Plus v-if="!saving" :size="20" />
					</template>
					Save All
				</NcButton>
			</div>
			<NcLoadingIcon v-if="loading"
				class="loadingIcon"
				:size="64"
				appearance="dark"
				name="Loading settings" />
		</NcSettingsSection>
	</div>
</template>

<script>
// Components
import { NcSettingsSection, NcNoteCard, NcSelect, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Restart from 'vue-material-design-icons/Restart.vue'

export default {
	name: 'Settings',
	components: {
		NcSettingsSection,
		NcNoteCard,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		Plus,
		Restart,
	},
	data() {
		return {
			loading: false,
			openRegisterInstalled: false,
			initialization: false,
			saving: false,
			settingsData: {},
			availableRegisters: [],
			availableRegistersOptions: [],
			objectTypes: [],
			ability: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			character: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			condition: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			effect: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			event: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			item: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			player: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			setting: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			skill: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			template: {
				selectedSource: '',
				selectedRegister: '',
				selectedSchema: '',
				availableSchemas: [],
				loading: false,
			},
			labelOptions: {
				options: [
					{ label: 'Internal', value: 'internal' },
					{ label: 'OpenRegister', value: 'openregister' },
				],
			},
			objectTypesList: [
				{ id: 'ability', title: 'Abilities', description: 'Configure storage for character abilities', helpLink: 'https://example.com/help/abilities' },
				{ id: 'character', title: 'Characters', description: 'Configure storage for character data', helpLink: 'https://example.com/help/characters' },
				{ id: 'condition', title: 'Conditions', description: 'Configure storage for character conditions', helpLink: 'https://example.com/help/conditions' },
				{ id: 'effect', title: 'Effects', description: 'Configure storage for game effects', helpLink: 'https://example.com/help/effects' },
				{ id: 'event', title: 'Events', description: 'Configure storage for game events', helpLink: 'https://example.com/help/events' },
				{ id: 'item', title: 'Items', description: 'Configure storage for game items', helpLink: 'https://example.com/help/items' },
				{ id: 'player', title: 'Players', description: 'Configure storage for player data', helpLink: 'https://example.com/help/players' },
				{ id: 'setting', title: 'Settings', description: 'Configure storage for game settings', helpLink: 'https://example.com/help/settings' },
				{ id: 'skill', title: 'Skills', description: 'Configure storage for character skills', helpLink: 'https://example.com/help/skills' },
				{ id: 'template', title: 'Templates', description: 'Configure storage for character templates', helpLink: 'https://example.com/help/templates' },
			],
		}
	},

	watch: {
		'ability.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.ability.selectedRegister = ''
					this.ability.selectedSchema = ''
				}
			},
			deep: true,
		},
		'ability.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'ability')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.ability.selectedSchema = '')
				}
			},
			deep: true,
		},
		'character.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.character.selectedRegister = ''
					this.character.selectedSchema = ''
				}
			},
			deep: true,
		},
		'character.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'character')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.character.selectedSchema = '')
				}
			},
			deep: true,
		},
		'condition.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.condition.selectedRegister = ''
					this.condition.selectedSchema = ''
				}
			},
			deep: true,
		},
		'condition.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'condition')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.condition.selectedSchema = '')
				}
			},
			deep: true,
		},
		'effect.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.effect.selectedRegister = ''
					this.effect.selectedSchema = ''
				}
			},
			deep: true,
		},
		'effect.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'effect')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.effect.selectedSchema = '')
				}
			},
			deep: true,
		},
		'event.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.event.selectedRegister = ''
					this.event.selectedSchema = ''
				}
			},
			deep: true,
		},
		'event.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'event')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.event.selectedSchema = '')
				}
			},
			deep: true,
		},
		'item.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.item.selectedRegister = ''
					this.item.selectedSchema = ''
				}
			},
			deep: true,
		},
		'item.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'item')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.item.selectedSchema = '')
				}
			},
			deep: true,
		},
		'player.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.player.selectedRegister = ''
					this.player.selectedSchema = ''
				}
			},
			deep: true,
		},
		'player.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'player')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.player.selectedSchema = '')
				}
			},
			deep: true,
		},
		'setting.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.setting.selectedRegister = ''
					this.setting.selectedSchema = ''
				}
			},
			deep: true,
		},
		'setting.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'setting')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.setting.selectedSchema = '')
				}
			},
			deep: true,
		},
		'skill.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.skill.selectedRegister = ''
					this.skill.selectedSchema = ''
				}
			},
			deep: true,
		},
		'skill.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'skill')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.skill.selectedSchema = '')
				}
			},
			deep: true,
		},
		'template.selectedSource': {
			handler(newValue) {
				if (newValue?.value === 'internal') {
					this.template.selectedRegister = ''
					this.template.selectedSchema = ''
				}
			},
			deep: true,
		},
		'template.selectedRegister': {
			handler(newValue, oldValue) {
				if (this.initialization === true && oldValue === '') return
				if (newValue) {
					this.setRegisterSchemaOptions(newValue?.value, 'template')
					oldValue !== '' && newValue?.value !== oldValue.value && (this.template.selectedSchema = '')
				}
			},
			deep: true,
		},
	},
	mounted() {
		this.fetchAll()
	},
	methods: {
		getDataProperty(name) {
			return this[name]
		},
		setRegisterSchemaOptions(registerId, property) {
			const selectedRegister = this.availableRegisters.find((register) => register.id.toString() === registerId)

			this[property].availableSchemas = {
				options: selectedRegister?.schemas?.map((schema) => ({
					value: schema.id.toString(),
					label: schema.title,
				})),
			}
		},
		fetchAll() {
			this.loading = true

			fetch('/index.php/apps/larpingapp/settings',
				{
					method: 'GET',
				},
			)
				.then((response) => {
					this.initialization = true
					response.json().then((data) => {
						this.openRegisterInstalled = data.openRegisters
						this.settingsData = data
						this.availableRegisters = data.availableRegisters
						this.objectTypes = data.objectTypes

						this.availableRegistersOptions = {
							options: data.availableRegisters.map((register) => ({
								value: register.id.toString(),
								label: register.title,
							})),
						}

						data.objectTypes.forEach((objectType) => {
							if (data[objectType + '_register']) {
								this.setRegisterSchemaOptions(data[objectType + '_register'], objectType)
							}

							this[objectType] = {
								selectedSource: this.labelOptions.options.find((option) => option.value === data[objectType + '_source'] ?? data[objectType + '_source']),
								selectedRegister: this.availableRegistersOptions.options.find((option) => option.value === data[objectType + '_register']),
								selectedSchema: this[objectType]?.availableSchemas?.options?.find((option) => option.value === data[objectType + '_schema']),
							}
						})

						this.initialization = false
						this.loading = false
					})
				})
				.catch((err) => {
					console.error(err)
					this.initialization = false
					this.loading = false
					return err
				})
		},
		saveConfig(configId) {
			this[configId].loading = true
			this.saving = true
			console.info(`Saving ${configId} config`)

			const settingsDataCopy = this.settingsData

			delete settingsDataCopy.objectTypes
			delete settingsDataCopy.openRegisters
			delete settingsDataCopy.availableRegisters

			fetch('/index.php/apps/larpingapp/settings',
				{
					method: 'POST',
					body: JSON.stringify({
						...settingsDataCopy,
						[configId + '_register']: this[configId].selectedRegister?.value ?? '',
						[configId + '_schema']: this[configId].selectedSchema?.value ?? '',
						[configId + '_source']: this[configId].selectedSource?.value ?? 'internal',
					}),
					headers: {
						'Content-Type': 'application/json',
					},
				},
			)
				.then((response) => {
					response.json().then((data) => {
						this[configId].loading = false
						this.saving = false

						this.settingsData = {
							...this.settingsData,
							[configId + '_register']: data[configId + '_register'],
							[configId + '_schema']: data[configId + '_schema'],
							[configId + '_source']: data[configId + '_source'],
						}
					})
				})
				.catch((err) => {
					console.error(err)
					this[configId].loading = false
					this.saving = false
					return err
				})
		},
		saveAll() {
			this.saving = true
			this.objectTypes.forEach((objectType) => {
				this[objectType].loading = true
			})

			console.info('Saving all config')

			const settingsDataCopy = this.settingsData

			delete settingsDataCopy.objectTypes
			delete settingsDataCopy.openRegisters
			delete settingsDataCopy.availableRegisters

			fetch('/index.php/apps/larpingapp/settings',
				{
					method: 'POST',
					body: JSON.stringify({
						...settingsDataCopy,
						ability_register: this.ability.selectedRegister?.value ?? '',
						ability_schema: this.ability.selectedSchema?.value ?? '',
						ability_source: this.ability.selectedSource?.value ?? 'internal',
						character_register: this.character.selectedRegister?.value ?? '',
						character_schema: this.character.selectedSchema?.value ?? '',
						character_source: this.character.selectedSource?.value ?? 'internal',
						condition_register: this.condition.selectedRegister?.value ?? '',
						condition_schema: this.condition.selectedSchema?.value ?? '',
						condition_source: this.condition.selectedSource?.value ?? 'internal',
						effect_register: this.effect.selectedRegister?.value ?? '',
						effect_schema: this.effect.selectedSchema?.value ?? '',
						effect_source: this.effect.selectedSource?.value ?? 'internal',
						event_register: this.event.selectedRegister?.value ?? '',
						event_schema: this.event.selectedSchema?.value ?? '',
						event_source: this.event.selectedSource?.value ?? 'internal',
						item_register: this.item.selectedRegister?.value ?? '',
						item_schema: this.item.selectedSchema?.value ?? '',
						item_source: this.item.selectedSource?.value ?? 'internal',
						player_register: this.player.selectedRegister?.value ?? '',
						player_schema: this.player.selectedSchema?.value ?? '',
						player_source: this.player.selectedSource?.value ?? 'internal',
						setting_register: this.setting.selectedRegister?.value ?? '',
						setting_schema: this.setting.selectedSchema?.value ?? '',
						setting_source: this.setting.selectedSource?.value ?? 'internal',
						skill_register: this.skill.selectedRegister?.value ?? '',
						skill_schema: this.skill.selectedSchema?.value ?? '',
						skill_source: this.skill.selectedSource?.value ?? 'internal',
						template_register: this.template.selectedRegister?.value ?? '',
						template_schema: this.template.selectedSchema?.value ?? '',
						template_source: this.template.selectedSource?.value ?? 'internal',
					}),
					headers: {
						'Content-Type': 'application/json',
					},
				},
			)
				.then((response) => {
					response.json().then((data) => {
						this.saving = false
						this.objectTypes.forEach((objectType) => {
							this[objectType].loading = false
						})
						this.settingsData = {
							...this.settingsData,
							ability_register: data.ability_register,
							ability_schema: data.ability_schema,
							ability_source: data.ability_source,
							character_register: data.character_register,
							character_schema: data.character_schema,
							character_source: data.character_source,
							condition_register: data.condition_register,
							condition_schema: data.condition_schema,
							condition_source: data.condition_source,
							effect_register: data.effect_register,
							effect_schema: data.effect_schema,
							effect_source: data.effect_source,
							event_register: data.event_register,
							event_schema: data.event_schema,
							event_source: data.event_source,
							item_register: data.item_register,
							item_schema: data.item_schema,
							item_source: data.item_source,
							player_register: data.player_register,
							player_schema: data.player_schema,
							player_source: data.player_source,
							setting_register: data.setting_register,
							setting_schema: data.setting_schema,
							setting_source: data.setting_source,
							skill_register: data.skill_register,
							skill_schema: data.skill_schema,
							skill_source: data.skill_source,
							template_register: data.template_register,
							template_schema: data.template_schema,
							template_source: data.template_source,
						}
					})
				})
				.catch((err) => {
					console.error(err)
					this.saving = false
					this.objectTypes.forEach((objectType) => {
						this[objectType].loading = false
					})
					return err
				})
		},
		resetConfig() {
			this.saving = true

			const settingsDataCopy = this.settingsData

			delete settingsDataCopy.objectTypes
			delete settingsDataCopy.openRegisters
			delete settingsDataCopy.availableRegisters

			fetch('/index.php/apps/larpingapp/settings',
				{
					method: 'POST',
					body: JSON.stringify({
						...settingsDataCopy,
						ability_register: '',
						ability_schema: '',
						ability_source: 'internal',
						character_register: '',
						character_schema: '',
						character_source: 'internal',
						condition_register: '',
						condition_schema: '',
						condition_source: 'internal',
						effect_register: '',
						effect_schema: '',
						effect_source: 'internal',
						event_register: '',
						event_schema: '',
						event_source: 'internal',
						item_register: '',
						item_schema: '',
						item_source: 'internal',
						player_register: '',
						player_schema: '',
						player_source: 'internal',
						setting_register: '',
						setting_schema: '',
						setting_source: 'internal',
						skill_register: '',
						skill_schema: '',
						skill_source: 'internal',
						template_register: '',
						template_schema: '',
						template_source: 'internal',
					}),
					headers: {
						'Content-Type': 'application/json',
					},
				},
			)
				.then((response) => {
					response.json().then((data) => {
						this.saving = false
						this.fetchAll()
					})
				})
				.catch((err) => {
					console.error(err)
					this.saving = false
					return err
				})
		},
		openLink(url, type = '') {
			window.open(url, type)
		},
	},
}
</script>
<style>
.selectionContainer {
	display: grid;
	grid-gap: 5px;
	grid-template-columns: 1fr;
}

.selectionContainer > * {
	margin-block-end: 10px;
}
</style>
