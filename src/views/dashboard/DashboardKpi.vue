<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Generic KPI widget for the larpingapp dashboard. Renders a single
 count tile for one objectType (character / event / item / player /
 etc.). Mounted by CnDashboardPage as a custom widget slot. Reads
 the count from the shared object store's pagination metadata so
 the widget reflects whatever the index pages have already fetched.

 Config flows in via the `config` prop (CnPageRenderer forwards
 manifest `widgets[].config` to slot components). Expected keys:
   - objectType (string) — store key
   - label (string) — translated UI label
   - iconName (string) — vue-material-design-icons component name
-->
<template>
	<div class="kpi-card">
		<div class="kpi-icon">
			<component :is="iconComponent" v-if="iconComponent" :size="24" />
		</div>
		<div class="kpi-content">
			<span class="kpi-value">{{ count }}</span>
			<span class="kpi-label">{{ label }}</span>
		</div>
	</div>
</template>

<script>
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import { useObjectStore } from '../../store/modules/object.js'

const ICONS = {
	AccountGroup,
	AccountMultiple,
	CalendarStar,
	Sword,
}

export default {
	name: 'DashboardKpi',
	props: {
		config: {
			type: Object,
			default: () => ({}),
		},
	},
	computed: {
		/**
		 * @spec exclude Pinia store accessor passthrough — framework glue.
		 */
		objectStore() {
			return useObjectStore()
		},
		/**
		 * @spec exclude Trivial config getter (objectType from prop) — no logic.
		 */
		objectType() {
			return this.config.objectType || ''
		},
		/**
		 * @spec exclude Trivial translated-label getter from config — formatter glue.
		 */
		label() {
			return t('larpingapp', this.config.label || this.objectType)
		},
		/**
		 * @spec exclude Static icon-map lookup by config.iconName — UI glue.
		 */
		iconComponent() {
			return ICONS[this.config.iconName] || null
		},
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-1
		 */
		count() {
			const pagination = this.objectStore.getPagination(this.objectType) || {}
			return pagination.total || 0
		},
	},
}
</script>

<style scoped>
.kpi-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
	height: 100%;
	box-sizing: border-box;
}

.kpi-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	border-radius: 50%;
	background: var(--color-primary-element-light, rgba(0, 130, 201, 0.1));
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.kpi-content {
	display: flex;
	flex-direction: column;
}

.kpi-value {
	font-size: 1.5em;
	font-weight: 600;
}

.kpi-label {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}
</style>
