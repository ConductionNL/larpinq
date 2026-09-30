<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  The active-world switcher (events-world-scope-and-upcoming; setting-management
  "A per-user active setting MUST filter lists server-side"). Mounted as the
  `header` of the world-scoped index pages, where it also draws the page title
  it replaces, and as the dashboard's `actions`. Choosing a world writes it into
  the page workspace, which the lists' `@workspace.activeWorld?` filter reads,
  and stores it through the preferences API. While a world is active the page
  says so and offers the way back to all worlds (REQ-EWU-003).
-->
<template>
	<div class="larpinq-world-switcher" data-testid="world-switcher">
		<CnPageHeader
			v-if="placement === 'header'"
			:title="title"
			:description="description"
			:icon="icon"
			:visuallyHidden="!showTitle" />
		<div class="larpinq-world-switcher__row">
			<NcSelect
				:modelValue="selectedOption"
				:options="options"
				:inputLabel="t('larpinq', 'World')"
				:clearable="false"
				:loading="loading"
				label="label"
				class="larpinq-world-switcher__select"
				data-testid="world-switcher-select"
				@update:modelValue="onSelect" />
			<p
				v-if="activeLabel"
				class="larpinq-world-switcher__note"
				data-testid="world-switcher-note">
				{{ t('larpinq', 'Only {world} is shown.', { world: activeLabel }) }}
				<NcButton
					variant="tertiary"
					data-testid="world-switcher-all"
					@click="choose('')">
					{{ t('larpinq', 'Show all worlds') }}
				</NcButton>
			</p>
		</div>
	</div>
</template>

<script>
import { CnPageHeader } from '@conduction/nextcloud-vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { inject } from 'vue'
import {
	activeWorld,
	appWorkspace,
	applyWorld,
	listWorlds,
	resolveWorld,
	saveWorld,
} from '../services/activeWorld.js'

export default {
	name: 'WorldSwitcher',

	components: { CnPageHeader, NcButton, NcSelect },

	props: {
		/** Where it is mounted: `header` (index pages) or `actions` (dashboard). */
		placement: { type: String, default: 'header' },
		/** The page title, handed over by the index page's header slot. */
		title: { type: String, default: '' },
		/** The page description, handed over by the header slot. */
		description: { type: String, default: '' },
		/** The page icon, handed over by the header slot. */
		icon: { type: [String, Object], default: '' },
		/** Whether the page shows its title, handed over by the header slot. */
		showTitle: { type: Boolean, default: true },
	},

	/**
	 * The workspace of the page this switcher sits on: the app's, or the
	 * dashboard's own.
	 *
	 * @return {{workspace: object|null}} The injected workspace.
	 *
	 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
	 */
	setup() {
		return { workspace: inject('cnWorkspaceContext', null) }
	},

	data() {
		return { worlds: [], loading: true, shared: activeWorld }
	},

	computed: {
		/**
		 * The choices: all worlds, then every active world by name.
		 *
		 * @return {Array<{id: string, label: string}>} The options.
		 *
		 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
		 */
		options() {
			return [{ id: '', label: this.t('larpinq', 'All worlds') }, ...this.worlds]
		},

		/**
		 * The option of the active world.
		 *
		 * @return {{id: string, label: string}} The selected option.
		 *
		 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
		 */
		selectedOption() {
			return this.options.find((o) => o.id === this.shared.id) || this.options[0]
		},

		/**
		 * The name of the active world, or '' for all worlds.
		 *
		 * @return {string} The name.
		 *
		 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
		 */
		activeLabel() {
			return this.shared.id ? this.selectedOption.label : ''
		},
	},

	/**
	 * Put the active world on this page's workspace, check it still exists,
	 * and load the worlds to choose from.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
	 */
	async mounted() {
		applyWorld(this.workspace, this.shared.id)
		try {
			if (this.shared.id && !this.shared.checked) {
				this.shared.checked = true
				const kept = await resolveWorld(this.shared.id)
				if (kept !== this.shared.id) {
					this.apply(kept)
				}
			}
			this.worlds = await listWorlds()
		} catch {
			this.worlds = []
		} finally {
			this.loading = false
		}
	},

	methods: {
		/**
		 * The user picked an option.
		 *
		 * @param {{id: string}|null} option The option.
		 * @return {void}
		 *
		 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
		 */
		onSelect(option) {
			this.choose(option?.id || '')
		},

		/**
		 * Make a world active, or all worlds, and store the choice.
		 *
		 * @param {string} worldId The world UUID, or ''.
		 * @return {void}
		 *
		 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
		 */
		choose(worldId) {
			if (worldId === this.shared.id) {
				return
			}
			this.apply(worldId)
			saveWorld(worldId).catch(() => {})
		},

		/**
		 * Write a world into the shared state and every workspace that lists read.
		 *
		 * @param {string} worldId The world UUID, or ''.
		 * @return {void}
		 *
		 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
		 */
		apply(worldId) {
			this.shared.id = worldId
			applyWorld(appWorkspace, worldId)
			if (this.workspace && this.workspace !== appWorkspace) {
				applyWorld(this.workspace, worldId)
			}
		},
	},
}
</script>

<style scoped>
.larpinq-world-switcher__row {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
	margin-block-end: calc(var(--default-grid-baseline) * 2);
}

.larpinq-world-switcher__select {
	min-width: 240px;
}

.larpinq-world-switcher__note {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
