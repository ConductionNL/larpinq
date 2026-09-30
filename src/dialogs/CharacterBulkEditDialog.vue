<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Edit selected characters (characters-status-and-bulk-edit REQ-CSB-003,
  REQ-CSB-004): choose a status, type or world, confirm, and every selected
  character is written on its own. Characters that could not be changed are
  listed by name with the reason. Opened by the Characters index bulk action
  "Edit selected".
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Edit selected characters')"
		data-testid="character-bulk-edit-dialog"
		@closing="close">
		<p>
			{{
				n(
					'larpinq',
					'%n character selected',
					'%n characters selected',
					ids.length,
				)
			}}
		</p>
		<template v-if="!done">
			<NcSelect
				v-model="status"
				:options="statusOptions"
				:inputLabel="t('larpinq', 'Status')"
				:placeholder="t('larpinq', 'Leave unchanged')"
				label="label"
				:disabled="busy"
				data-testid="character-bulk-edit-status" />
			<NcSelect
				v-model="type"
				:options="typeOptions"
				:inputLabel="t('larpinq', 'Type')"
				:placeholder="t('larpinq', 'Leave unchanged')"
				label="label"
				:disabled="busy"
				data-testid="character-bulk-edit-type" />
			<NcSelect
				v-model="world"
				:options="worldOptions"
				:inputLabel="t('larpinq', 'World')"
				:placeholder="t('larpinq', 'Leave unchanged')"
				label="label"
				:disabled="busy"
				data-testid="character-bulk-edit-world" />
			<p v-if="busy" data-testid="character-bulk-edit-progress">
				{{
					t('larpinq', 'Changed {done} of {total}', {
						done: progress,
						total: ids.length,
					})
				}}
			</p>
		</template>
		<template v-else>
			<NcNoteCard
				v-if="report.changed.length > 0"
				type="success"
				data-testid="character-bulk-edit-done">
				{{
					n(
						'larpinq',
						'%n character changed.',
						'%n characters changed.',
						report.changed.length,
					)
				}}
			</NcNoteCard>
			<NcNoteCard
				v-if="report.refused.length > 0"
				type="warning"
				data-testid="character-bulk-edit-refused">
				<p>{{ t('larpinq', 'These characters were not changed:') }}</p>
				<ul>
					<li v-for="entry in report.refused" :key="entry.id">
						{{
							t('larpinq', '{name}: {reason}', {
								name: entry.name,
								reason: entry.reason,
							})
						}}
					</li>
				</ul>
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton data-testid="character-bulk-edit-cancel" @click="close">
				{{ done ? t('larpinq', 'Close') : t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				v-if="!done"
				data-testid="character-bulk-edit-confirm"
				variant="primary"
				:disabled="busy || !chosen"
				@click="apply">
				{{
					n(
						'larpinq',
						'Change %n character',
						'Change %n characters',
						ids.length,
					)
				}}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import {
	applyBulkEdit,
	bulkBody,
	fetchCharacterNames,
	fetchWorlds,
} from '../services/characterBulkEdit.js'

export default {
	name: 'CharacterBulkEditDialog',

	components: { NcButton, NcDialog, NcNoteCard, NcSelect },

	props: {
		/**
		 * The selected character ids, from the index page's selection.
		 */
		selectedIds: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close'],

	data() {
		return {
			status: null,
			type: null,
			world: null,
			worldOptions: [],
			busy: false,
			progress: 0,
			done: false,
			report: { changed: [], refused: [] },
		}
	},

	computed: {
		/**
		 * The selected ids as strings.
		 *
		 * @return {Array<string>} The ids.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		ids() {
			return (this.selectedIds || []).map(String)
		},

		/**
		 * The statuses to choose from.
		 *
		 * @return {Array<{id: string, label: string}>} The options.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		statusOptions() {
			return [
				{ id: 'active', label: t('larpinq', 'Active') },
				{ id: 'retired', label: t('larpinq', 'Retired') },
				{ id: 'dead', label: t('larpinq', 'Dead') },
			]
		},

		/**
		 * The character types to choose from.
		 *
		 * @return {Array<{id: string, label: string}>} The options.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		typeOptions() {
			return [
				{ id: 'player', label: t('larpinq', 'Player character') },
				{ id: 'npc', label: t('larpinq', 'Non-player character') },
				{ id: 'other', label: t('larpinq', 'Other') },
			]
		},

		/**
		 * The values chosen so far.
		 *
		 * @return {object} Field to value.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		choices() {
			return {
				status: this.status?.id,
				type: this.type?.id,
				setting: this.world?.id,
			}
		},

		/**
		 * Whether anything was chosen.
		 *
		 * @return {boolean} True when at least one field has a value.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		chosen() {
			return Object.keys(bulkBody(this.choices)).length > 0
		},
	},

	/**
	 * Load the worlds.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	async mounted() {
		this.worldOptions = await fetchWorlds()
	},

	methods: {
		t,
		n,

		/**
		 * Write every selected character and keep the report.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		async apply() {
			this.busy = true
			this.progress = 0
			const names = await fetchCharacterNames(this.ids)
			this.report = await applyBulkEdit(
				this.ids,
				this.choices,
				names,
				(count) => {
					this.progress = count
				},
			)
			this.busy = false
			this.done = true
		},

		/**
		 * Close, saying how many characters changed.
		 *
		 * @return {void}
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		close() {
			this.$emit('close', this.report.changed.length)
		},
	},
}
</script>
