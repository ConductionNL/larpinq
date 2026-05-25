<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Generic "recent items" widget for the larpingapp dashboard. Renders
 the most-recent items from one objectType collection (character /
 event / etc.) with a "view all" link to the matching index route.

 Config (from manifest widgets[].config):
   - objectType (string)
   - indexRoute (string) — vue-router route name for the index view
   - emptyLabel (string) — translated message when the list is empty
   - iconName (string) — vue-material-design-icons component name
   - limit (number) — max items to show, default 5
-->
<template>
	<div class="list-widget-content">
		<div v-if="items.length === 0" class="widget-empty">
			{{ emptyLabel }}
		</div>
		<div v-else class="widget-list">
			<div
				v-for="item in items.slice(0, limit)"
				:key="item.id"
				class="widget-list-item"
				@click="goToIndex">
				<component :is="iconComponent" v-if="iconComponent" :size="20" class="item-icon" />
				<div class="item-content">
					<span class="item-title">{{ item.name || fallbackLabel }}</span>
				</div>
			</div>
			<button
				v-if="totalCount > limit"
				class="view-all-button"
				@click="goToIndex">
				{{ t('larpingapp', 'View all ({count})', { count: totalCount }) }}
			</button>
		</div>
	</div>
</template>

<script>
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import { useObjectStore } from '../../store/modules/object.js'

const ICONS = { AccountGroup, CalendarStar }

export default {
	name: 'DashboardRecentList',
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
		 * @spec exclude Trivial numeric config getter with default — no logic.
		 */
		limit() {
			return Number(this.config.limit || 5)
		},
		/**
		 * @spec exclude Trivial translated-label getter from config — formatter glue.
		 */
		emptyLabel() {
			return t('larpingapp', this.config.emptyLabel || 'No items yet')
		},
		/**
		 * @spec exclude Trivial translated-fallback getter from config — formatter glue.
		 */
		fallbackLabel() {
			return t('larpingapp', this.config.fallbackLabel || 'Untitled')
		},
		/**
		 * @spec exclude Static icon-map lookup by config.iconName — UI glue.
		 */
		iconComponent() {
			return ICONS[this.config.iconName] || null
		},
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-2
		 */
		items() {
			return this.objectStore.getCollection(this.objectType).results || []
		},
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-2
		 */
		totalCount() {
			const pagination = this.objectStore.getPagination(this.objectType) || {}
			return pagination.total || this.items.length
		},
	},
	methods: {
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-2
		 */
		goToIndex() {
			if (this.config.indexRoute) {
				this.$router.push({ name: this.config.indexRoute })
			}
		},
	},
}
</script>

<style scoped>
.list-widget-content {
	padding: 12px;
	height: 100%;
	box-sizing: border-box;
	overflow-y: auto;
}

.widget-empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	text-align: center;
	padding: 16px;
}

.widget-list {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.widget-list-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 12px;
	cursor: pointer;
	border-radius: var(--border-radius);
}

.widget-list-item:hover {
	background: var(--color-background-hover);
}

.item-icon {
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.item-content {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.item-title {
	font-weight: 500;
}

.view-all-button {
	border: none;
	background: transparent;
	color: var(--color-primary-element);
	cursor: pointer;
	padding: 8px;
	text-align: left;
	font-weight: 500;
}

.view-all-button:hover {
	text-decoration: underline;
}
</style>
