<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Photos-leaf host for the Character detail page (ADR-019, ADR-022).

 Surfaces the OpenRegister photos integration leaf on the character detail
 page so portrait / reference images can be attached and viewed.  Images
 are stored through the OR files / object-interactions abstraction — this
 component adds no bespoke image column or upload handler (ADR-022).

 Graceful degradation: when the OR integration registry is unavailable or
 does not expose the photos leaf, this component renders nothing and the
 character detail page continues to work normally, mirroring the DocuDesk
 PDF pattern.

 @spec openspec/changes/character-photos-leaf/tasks.md#task-1.2
-->
<template>
	<div
		v-if="photosLeafAvailable"
		class="object-detail__photos-leaf"
		data-integration-host="photos"
		:data-object-type="objectType">
		<!--
		  Photos integration is surfaced via the CnObjectSidebar driven by
		  objectSidebarState (App.vue provide/inject chain, ADR-019 Stage 3).
		  The sidebar tab appears because the character schema's linkedTypes
		  includes "photos" (Stage 2) and the OR registry exposes the leaf
		  (Stage 1).  This host element marks the injection point and carries
		  test-selectable attributes for spec-coverage tests.
		-->
	</div>
</template>

<script>
export default {
	name: 'ObjectDetail',

	props: {
		/**
		 * Object type string passed from the manifest page config or parent.
		 * Expected value for the character detail host: 'character'.
		 *
		 * @spec openspec/changes/character-photos-leaf/tasks.md#task-1.2
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
		 * Returns true when the OR integration registry has the photos leaf
		 * registered (ADR-019 Stage 1 filter).  Checks the global
		 * OCA.OpenRegister.integrations API that OR's frontend bootstrap
		 * populates.  Falls back to false when OR is absent (graceful
		 * degradation — ADR-022 leaf pattern).
		 *
		 * @spec openspec/changes/character-photos-leaf/tasks.md#task-3.1
		 * @return {boolean}
		 */
		photosLeafAvailable() {
			const registry = window.OCA?.OpenRegister?.integrations
			if (!registry) {
				return false
			}
			if (typeof registry.isRegistered === 'function') {
				return registry.isRegistered('photos')
			}
			// Fallback: scan registered integrations array if exposed.
			if (Array.isArray(registry.list)) {
				return registry.list.some((i) => i.id === 'photos')
			}
			return false
		},
	},
}
</script>

<style scoped>
/*
 * The host div is intentionally invisible — the photos leaf is rendered
 * inside CnObjectSidebar (App.vue), not inline here.  Zero dimensions keep
 * it from affecting layout while remaining addressable by tests.
 */
.object-detail__photos-leaf {
	display: none;
}
</style>
