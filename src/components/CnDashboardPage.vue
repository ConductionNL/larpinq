<template>
	<div class="cn-dashboard-page">
		<div v-if="loading" class="cn-dashboard-loading">
			<NcLoadingIcon :size="44" />
		</div>
		<div v-else-if="isEmpty" class="cn-dashboard-empty">
			<slot name="empty">
				<p>{{ emptyLabel }}</p>
			</slot>
		</div>
		<div v-else class="cn-dashboard-content">
			<div class="cn-dashboard-header">
				<h2 class="cn-dashboard-title">{{ title }}</h2>
				<div class="cn-dashboard-header-actions">
					<slot name="header-actions" />
				</div>
			</div>
			<div class="cn-dashboard-grid">
				<div
					v-for="item in sortedLayout"
					:key="item.id"
					class="cn-dashboard-widget"
					:style="gridStyle(item)">
					<h3 v-if="getWidgetTitle(item) && item.showTitle !== false" class="cn-dashboard-widget-title">
						{{ getWidgetTitle(item) }}
					</h3>
					<slot :name="'widget-' + item.widgetId" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'CnDashboardPage',
	components: { NcLoadingIcon },
	props: {
		title: { type: String, default: '' },
		widgets: { type: Array, default: () => [] },
		layout: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		emptyLabel: { type: String, default: 'No data' },
		unavailableLabel: { type: String, default: '' },
	},
	emits: ['layout-change'],
	computed: {
		isEmpty() {
			return this.widgets.length === 0
		},
		widgetMap() {
			const map = {}
			for (const w of this.widgets) {
				map[w.id] = w
			}
			return map
		},
		sortedLayout() {
			return [...this.layout].sort((a, b) => {
				if (a.gridY !== b.gridY) return a.gridY - b.gridY
				return a.gridX - b.gridX
			})
		},
	},
	methods: {
		getWidgetTitle(item) {
			const def = this.widgetMap[item.widgetId]
			return def ? def.title : ''
		},
		gridStyle(item) {
			const colStart = (item.gridX || 0) + 1
			const colEnd = colStart + (item.gridWidth || 12)
			return {
				gridColumn: colStart + ' / ' + colEnd,
			}
		},
	},
}
</script>

<style scoped>
.cn-dashboard-page {
	padding: 16px;
}

.cn-dashboard-loading {
	display: flex;
	justify-content: center;
	padding: 40px;
}

.cn-dashboard-empty {
	text-align: center;
	padding: 40px;
	color: var(--color-text-maxcontrast);
}

.cn-dashboard-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16px;
}

.cn-dashboard-title {
	font-size: 20px;
	font-weight: 700;
}

.cn-dashboard-header-actions {
	display: flex;
	gap: 8px;
}

.cn-dashboard-grid {
	display: grid;
	grid-template-columns: repeat(12, 1fr);
	gap: 16px;
}

.cn-dashboard-widget {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px;
}

.cn-dashboard-widget-title {
	font-size: 16px;
	font-weight: 600;
	margin-bottom: 8px;
}
</style>
