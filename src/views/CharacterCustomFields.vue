<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  The Extra fields tab on a character (characters-custom-fields): one input
  per field definition of the character's world, saved with one PATCH.
  OpenRegister strips private definitions and values for a player, so a
  player sees and saves only the fields meant for them.
-->
<template>
	<div class="character-custom-fields" data-testid="character-custom-fields">
		<p v-if="loading">
			{{ t('larpinq', 'Loading extra fields') }}
		</p>
		<p v-else-if="sheet === null" role="alert">
			{{ t('larpinq', 'Could not load the extra fields.') }}
		</p>
		<p v-else-if="sheet.rows.length === 0 && sheet.orphans.length === 0">
			{{
				t(
					'larpinq',
					'This world has no extra fields yet. Game masters add them under Character fields.',
				)
			}}
		</p>
		<form v-else @submit.prevent="save">
			<div
				v-for="row in sheet.rows"
				:key="row.key"
				class="character-custom-fields__field"
				:data-testid="`custom-field-${row.key}`">
				<NcSelect
					v-if="row.fieldType === 'choice'"
					v-model="edits[row.key]"
					:inputLabel="labelOf(row)"
					:options="row.choices"
					:clearable="true" />
				<NcCheckboxRadioSwitch
					v-else-if="row.fieldType === 'yes-no'"
					v-model="edits[row.key]"
					type="switch">
					{{ labelOf(row) }}
				</NcCheckboxRadioSwitch>
				<NcTextField
					v-else
					v-model="edits[row.key]"
					:label="labelOf(row)"
					:type="row.fieldType === 'number' ? 'number' : 'text'" />
				<p v-if="row.help" class="character-custom-fields__help">
					{{ row.help }}
				</p>
				<p
					v-if="errors[row.key]"
					class="character-custom-fields__error"
					role="alert">
					{{ errors[row.key] }}
				</p>
			</div>

			<div
				v-if="sheet.canEditPrivate && sheet.orphans.length > 0"
				data-testid="custom-field-orphans">
				<p>{{ t('larpinq', 'Values of fields that no longer exist:') }}</p>
				<ul>
					<li v-for="orphan in sheet.orphans" :key="orphan.key">
						{{ orphan.key }}: {{ String(orphan.value) }}
					</li>
				</ul>
			</div>

			<NcButton
				type="submit"
				variant="primary"
				data-testid="custom-fields-save"
				:disabled="saving">
				{{
					saving
						? t('larpinq', 'Saving')
						: t('larpinq', 'Save extra fields')
				}}
			</NcButton>
			<p v-if="saved" role="status" data-testid="custom-fields-saved">
				{{ t('larpinq', 'The extra fields are saved.') }}
			</p>
		</form>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	fetchCustomFields,
	inputValue,
	saveCustomFields,
	valuesFrom,
} from '../services/characterCustomFields.js'

export default {
	name: 'CharacterCustomFields',

	components: { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextField },

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The character UUID. Falls back to the injected object context or the route.
		 *
		 * @spec openspec/specs/character-custom-fields/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			loading: true,
			sheet: null,
			edits: {},
			errors: {},
			saving: false,
			saved: false,
		}
	},

	computed: {
		/**
		 * The character id from prop, injected context or route.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/character-custom-fields/spec.md
		 */
		characterId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| String(this.$route?.params?.id ?? '')
			)
		},
	},

	/**
	 * Load the sheet.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/character-custom-fields/spec.md
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		t,

		/**
		 * Load the definitions and values, and fill the form.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/character-custom-fields/spec.md
		 */
		async load() {
			this.loading = true
			this.sheet = await fetchCustomFields(this.characterId)
			const edits = {}
			for (const row of this.sheet?.rows ?? []) {
				edits[row.key] =
					row.fieldType === 'yes-no' ? row.value === true : row.value
			}
			this.edits = edits
			this.loading = false
		},

		/**
		 * The input label, marking fields for game masters only.
		 *
		 * @param {object} row The sheet row.
		 * @return {string} The label.
		 *
		 * @spec openspec/specs/character-custom-fields/spec.md
		 */
		labelOf(row) {
			return row.private
				? t('larpinq', '{label} (game masters only)', { label: row.label })
				: row.label
		},

		/**
		 * Save the values, and show the error per refused field.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/character-custom-fields/spec.md
		 */
		async save() {
			const values = {}
			for (const row of this.sheet.rows) {
				values[row.key] = inputValue(row.fieldType, this.edits[row.key])
			}
			this.saving = true
			this.saved = false
			const result = await saveCustomFields(
				this.characterId,
				valuesFrom(this.sheet, values),
			)
			this.saving = false
			this.errors = result.errors
			if (result.ok) {
				this.saved = true
				await this.load()
			}
		},
	},
}
</script>

<style scoped>
.character-custom-fields__field {
	margin-block-end: calc(var(--default-grid-baseline, 4px) * 3);
}

.character-custom-fields__help {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.character-custom-fields__error {
	color: var(--color-error-text);
	margin: 0;
}
</style>
