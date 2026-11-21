<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Copy a world's rules into a new world: show what will be copied, ask for the
  new name, run the copy, then link to the new world. Opened by the
  SettingDetail header action "Copy world" (open-modal), game masters only.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Copy world')"
		data-testid="copy-world-dialog"
		@closing="$emit('close')">
		<p v-if="status === 'loading'">
			{{ t('larpinq', 'Counting what will be copied') }}
		</p>
		<NcNoteCard
			v-else-if="status === 'forbidden'"
			type="warning"
			data-testid="copy-world-forbidden">
			{{ t('larpinq', 'Only game masters can copy a world.') }}
		</NcNoteCard>
		<NcNoteCard
			v-else-if="status === 'too-large'"
			type="warning"
			data-testid="copy-world-too-large">
			{{ t('larpinq', 'This world has too many rules to copy in one go.') }}
			{{ error }}
		</NcNoteCard>
		<NcNoteCard
			v-else-if="status === 'failed'"
			type="error"
			data-testid="copy-world-load-failed">
			{{ t('larpinq', 'The world could not be loaded. Try again later.') }}
		</NcNoteCard>
		<template v-else-if="newWorldId">
			<NcNoteCard type="success" data-testid="copy-world-done">
				{{
					t('larpinq', 'The new world {name} is ready.', {
						name: name.trim(),
					})
				}}
			</NcNoteCard>
		</template>
		<template v-else>
			<p>
				{{
					t('larpinq', 'The rules of {world} go into a new world:', {
						world: worldName,
					})
				}}
			</p>
			<ul data-testid="copy-world-counts">
				<li v-for="row in rows" :key="row.key">
					{{ row.label }}: {{ row.count }}
				</li>
			</ul>
			<p>
				{{
					t(
						'larpinq',
						'Characters are not copied: items and conditions in the new world start without holders.',
					)
				}}
			</p>
			<NcTextField
				v-model="name"
				data-testid="copy-world-name"
				:label="t('larpinq', 'Name of the new world')"
				:disabled="busy" />
			<NcNoteCard v-if="copyError" type="error" data-testid="copy-world-error">
				{{ t('larpinq', 'The copy failed: {error}', { error: copyError }) }}
				<template v-if="leftovers.length > 0">
					{{
						t(
							'larpinq',
							'These objects could not be removed again: {ids}',
							{ ids: leftovers.join(', ') },
						)
					}}
				</template>
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton data-testid="copy-world-cancel" @click="$emit('close')">
				{{ newWorldId ? t('larpinq', 'Close') : t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				v-if="newWorldId"
				data-testid="copy-world-open"
				variant="primary"
				@click="openNewWorld">
				{{ t('larpinq', 'Open the new world') }}
			</NcButton>
			<NcButton
				v-else
				data-testid="copy-world-confirm"
				variant="primary"
				:disabled="!copyable"
				@click="copy">
				{{ busy ? t('larpinq', 'Copying') : t('larpinq', 'Copy world') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { canCopy, copyWorld, fetchCopyPreview } from '../services/worldCopy.js'

export default {
	name: 'CopyWorldDialog',

	components: { NcButton, NcDialog, NcNoteCard, NcTextField },

	props: {
		/**
		 * The world to copy. Falls back to the route: an `open-modal` header
		 * action forwards its props verbatim, so `@objectId` would arrive as
		 * that literal string.
		 */
		worldId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			status: 'loading',
			worldName: '',
			counts: {},
			error: '',
			name: '',
			busy: false,
			copyError: '',
			leftovers: [],
			newWorldId: '',
		}
	},

	computed: {
		/**
		 * The world this dialog copies.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/setting-management/spec.md
		 */
		id() {
			const fromProp = this.worldId.startsWith('@') ? '' : this.worldId
			return fromProp || String(this.$route?.params?.id ?? '')
		},

		/**
		 * The counts as labelled rows.
		 *
		 * @return {Array<{key: string, label: string, count: number}>} The rows.
		 *
		 * @spec openspec/specs/setting-management/spec.md
		 */
		rows() {
			const labels = {
				abilities: t('larpinq', 'Abilities'),
				effects: t('larpinq', 'Effects'),
				skills: t('larpinq', 'Skills'),
				items: t('larpinq', 'Items'),
				conditions: t('larpinq', 'Conditions'),
				lorePages: t('larpinq', 'Lore pages'),
				characterFields: t('larpinq', 'Character fields'),
			}
			return Object.keys(labels).map((key) => ({
				key,
				label: labels[key],
				count: Number(this.counts[key] ?? 0),
			}))
		},

		/**
		 * Whether Copy world may be pressed.
		 *
		 * @return {boolean} True when the preview loaded and a name is filled in.
		 *
		 * @spec openspec/specs/setting-management/spec.md
		 */
		copyable() {
			return canCopy({ status: this.status, name: this.name, busy: this.busy })
		},
	},

	/**
	 * Load the preview, and suggest a name for the copy.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	async mounted() {
		const state = await fetchCopyPreview(this.id)
		this.status = state.status
		this.worldName = state.worldName
		this.counts = state.counts
		this.error = state.error
		if (state.status === 'ready') {
			this.name = t('larpinq', '{world} (copy)', { world: state.worldName })
		}
	},

	methods: {
		t,

		/**
		 * Run the copy and remember the new world, or the reason it failed.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/setting-management/spec.md
		 */
		async copy() {
			if (!this.copyable) {
				return
			}
			this.busy = true
			this.copyError = ''
			this.leftovers = []
			const result = await copyWorld(this.id, this.name)
			this.busy = false
			if (result.ok) {
				this.newWorldId = result.worldId
				return
			}
			this.copyError = result.error || t('larpinq', 'unknown error')
			this.leftovers = result.leftovers
		},

		/**
		 * Go to the new world and close the dialog.
		 *
		 * @return {void}
		 *
		 * @spec openspec/specs/setting-management/spec.md
		 */
		openNewWorld() {
			this.$router?.push(`/settings/${encodeURIComponent(this.newWorldId)}`)
			this.$emit('close')
		},
	},
}
</script>
