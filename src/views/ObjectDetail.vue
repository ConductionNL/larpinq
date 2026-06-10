<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Integration-leaf host for LarpingApp detail pages (ADR-019, ADR-022).

 Surfaces OpenRegister integration leaves on the per-objectType detail
 page. The available leaves vary by object type — each is sourced from
 the schema `configuration.linkedTypes` (Stage 2) and the OR integration
 registry exposes the leaf (Stage 1).  This component is a thin host: it
 renders one invisible marker div per leaf so the CnObjectSidebar can
 mount the actual leaf widget and so spec-coverage tests can address the
 injection point.  No bespoke domain stores or parallel data paths are
 introduced — the leaf widgets read/write through the OR abstractions
 (files, contacts, forms, calendar, maps).

 Graceful degradation: when the OR integration registry is unavailable
 or does not expose a given leaf, the corresponding host div is omitted
 and the detail page continues to work normally (DocuDesk PDF pattern).

 Leaf map by objectType (kept in sync with `linkedTypes` fragments):
   - character → ["photos"]
   - event     → ["calendar", "maps", "forms"]
   - player    → ["contacts"]

 @spec openspec/changes/character-photos-leaf/tasks.md#task-1.2
 @spec openspec/changes/event-calendar-leaf/tasks.md#task-1.2
 @spec openspec/changes/event-location-to-maps-leaf/tasks.md#task-1.2
 @spec openspec/changes/event-signup-to-forms-leaf/tasks.md#task-1.2
 @spec openspec/changes/player-to-contacts-leaf/tasks.md#task-1.2
-->
<template>
	<div class="object-detail__leaf-hosts" :data-object-type="objectType">
		<div
			v-for="leaf in availableLeaves"
			:key="leaf"
			class="object-detail__leaf-host"
			:class="`object-detail__leaf-host--${leaf}`"
			:data-integration-host="leaf"
			:data-object-type="objectType">
			<!--
			  Each leaf widget is surfaced inside CnObjectSidebar
			  (App.vue provide/inject, ADR-019 Stage 3).  This marker
			  carries test-selectable attributes for spec-coverage tests
			  and the data-* host attributes consumed by the sidebar
			  binding code.
			-->
		</div>
	</div>
</template>

<script>
/**
 * Per-objectType leaf assignments (ADR-019).
 *
 * Must stay in sync with the schema `configuration.linkedTypes`
 * declared in lib/Settings/register.d/*-leaf.json fragments.
 */
const LEAVES_BY_OBJECT_TYPE = {
	character: ['photos'],
	event: ['calendar', 'maps', 'forms'],
	player: ['contacts'],
}

export default {
	name: 'ObjectDetail',

	props: {
		/**
		 * Object type string passed from the manifest page config or parent.
		 * Expected values: 'character', 'event', 'player'.
		 *
		 * @spec openspec/changes/character-photos-leaf/tasks.md#task-1.2
		 * @spec openspec/changes/event-calendar-leaf/tasks.md#task-1.2
		 * @spec openspec/changes/event-location-to-maps-leaf/tasks.md#task-1.2
		 * @spec openspec/changes/event-signup-to-forms-leaf/tasks.md#task-1.2
		 * @spec openspec/changes/player-to-contacts-leaf/tasks.md#task-1.2
		 */
		objectType: {
			type: String,
			default: 'character',
		},
		/**
		 * Manifest page config forwarded by CnPageRenderer.
		 *
		 * @spec exclude Config passthrough — no product logic.
		 */
		config: {
			type: Object,
			default: () => ({}),
		},
	},

	computed: {
		/**
		 * Candidate leaves for this object type (before registry filter).
		 *
		 * @spec exclude Lookup table accessor — no logic.
		 * @return {Array<string>}
		 */
		candidateLeaves() {
			return LEAVES_BY_OBJECT_TYPE[this.objectType] || []
		},

		/**
		 * Returns the subset of candidate leaves the OR integration
		 * registry has actually registered (ADR-019 Stage 1 filter).
		 * Checks the global OCA.OpenRegister.integrations API that OR's
		 * frontend bootstrap populates.  Falls back to "none available"
		 * when OR is absent (graceful degradation — ADR-022 leaf pattern).
		 *
		 * @spec openspec/changes/character-photos-leaf/tasks.md#task-3.1
		 * @spec openspec/changes/event-calendar-leaf/tasks.md#task-3.1
		 * @spec openspec/changes/event-location-to-maps-leaf/tasks.md#task-4.1
		 * @spec openspec/changes/event-signup-to-forms-leaf/tasks.md#task-4.1
		 * @spec openspec/changes/player-to-contacts-leaf/tasks.md#task-5.1
		 * @return {Array<string>}
		 */
		availableLeaves() {
			const registry = window.OCA?.OpenRegister?.integrations
			if (!registry) {
				return []
			}

			const isAvailable = (leaf) => {
				if (typeof registry.isRegistered === 'function') {
					return registry.isRegistered(leaf)
				}
				if (Array.isArray(registry.list)) {
					return registry.list.some((i) => i.id === leaf)
				}
				return false
			}

			return this.candidateLeaves.filter(isAvailable)
		},
	},
}
</script>

<style scoped>
/*
 * The host divs are intentionally invisible — the leaf widgets are
 * rendered inside CnObjectSidebar (App.vue), not inline here.  Zero
 * dimensions keep them out of layout while remaining addressable by
 * tests and the sidebar binding code.
 */
.object-detail__leaf-hosts,
.object-detail__leaf-host {
	display: none;
}
</style>
