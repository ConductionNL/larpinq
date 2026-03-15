<template>
	<div>
		<CnDashboardPage
			:title="t('larpingapp', 'Dashboard')"
			:widgets="widgetDefs"
			:layout="dashboardLayout"
			:loading="loading"
			:empty-label="t('larpingapp', 'Welcome to the Larping App!')"
			@layout-change="onLayoutChange">
			<!-- Header actions -->
			<template #header-actions>
				<NcButton
					:disabled="loading"
					:aria-label="t('larpingapp', 'Refresh dashboard')"
					@click="refreshData">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<Refresh v-else :size="20" />
					</template>
				</NcButton>
			</template>

			<!-- Characters count -->
			<template #widget-count-characters>
				<div class="kpi-card">
					<div class="kpi-icon">
						<AccountGroup :size="24" />
					</div>
					<div class="kpi-content">
						<span class="kpi-value">{{ getPagination('character').total || 0 }}</span>
						<span class="kpi-label">{{ t('larpingapp', 'Characters') }}</span>
					</div>
				</div>
			</template>

			<!-- Events count -->
			<template #widget-count-events>
				<div class="kpi-card">
					<div class="kpi-icon">
						<CalendarStar :size="24" />
					</div>
					<div class="kpi-content">
						<span class="kpi-value">{{ getPagination('event').total || 0 }}</span>
						<span class="kpi-label">{{ t('larpingapp', 'Events') }}</span>
					</div>
				</div>
			</template>

			<!-- Items count -->
			<template #widget-count-items>
				<div class="kpi-card">
					<div class="kpi-icon">
						<Sword :size="24" />
					</div>
					<div class="kpi-content">
						<span class="kpi-value">{{ getPagination('item').total || 0 }}</span>
						<span class="kpi-label">{{ t('larpingapp', 'Items') }}</span>
					</div>
				</div>
			</template>

			<!-- Players count -->
			<template #widget-count-players>
				<div class="kpi-card">
					<div class="kpi-icon">
						<AccountMultiple :size="24" />
					</div>
					<div class="kpi-content">
						<span class="kpi-value">{{ getPagination('player').total || 0 }}</span>
						<span class="kpi-label">{{ t('larpingapp', 'Players') }}</span>
					</div>
				</div>
			</template>

			<!-- Recent Characters widget -->
			<template #widget-recent-characters>
				<div class="list-widget-content">
					<div v-if="characters.length === 0" class="widget-empty">
						{{ t('larpingapp', 'No characters yet') }}
					</div>
					<div v-else class="widget-list">
						<div
							v-for="character in characters.slice(0, 5)"
							:key="character.id"
							class="widget-list-item"
							@click="$router.push({ name: 'Characters' })">
							<AccountGroup :size="20" class="item-icon" />
							<div class="item-content">
								<span class="item-title">{{ character.name || 'Unnamed Character' }}</span>
							</div>
						</div>
						<button
							v-if="characters.length > 5"
							class="view-all-button"
							@click="$router.push({ name: 'Characters' })">
							{{ t('larpingapp', 'View all ({count})', { count: getPagination('character').total || characters.length }) }}
						</button>
					</div>
				</div>
			</template>

			<!-- Recent Events widget -->
			<template #widget-recent-events>
				<div class="list-widget-content">
					<div v-if="events.length === 0" class="widget-empty">
						{{ t('larpingapp', 'No events yet') }}
					</div>
					<div v-else class="widget-list">
						<div
							v-for="event in events.slice(0, 5)"
							:key="event.id"
							class="widget-list-item"
							@click="$router.push({ name: 'Events' })">
							<CalendarStar :size="20" class="item-icon" />
							<div class="item-content">
								<span class="item-title">{{ event.name || 'Unnamed Event' }}</span>
							</div>
						</div>
						<button
							v-if="events.length > 5"
							class="view-all-button"
							@click="$router.push({ name: 'Events' })">
							{{ t('larpingapp', 'View all ({count})', { count: getPagination('event').total || events.length }) }}
						</button>
					</div>
				</div>
			</template>
		</CnDashboardPage>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import CnDashboardPage from '../../components/CnDashboardPage.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import { useObjectStore } from '../../store/modules/object.js'
import pinia from '../../pinia.js'

const DEFAULT_LAYOUT = [
	{ id: 1, widgetId: 'count-characters', gridX: 0, gridY: 0, gridWidth: 3, gridHeight: 2, showTitle: false },
	{ id: 2, widgetId: 'count-events', gridX: 3, gridY: 0, gridWidth: 3, gridHeight: 2, showTitle: false },
	{ id: 3, widgetId: 'count-items', gridX: 6, gridY: 0, gridWidth: 3, gridHeight: 2, showTitle: false },
	{ id: 4, widgetId: 'count-players', gridX: 9, gridY: 0, gridWidth: 3, gridHeight: 2, showTitle: false },
	{ id: 5, widgetId: 'recent-characters', gridX: 0, gridY: 2, gridWidth: 6, gridHeight: 4 },
	{ id: 6, widgetId: 'recent-events', gridX: 6, gridY: 2, gridWidth: 6, gridHeight: 4 },
]

export default {
	name: 'Dashboard',
	components: {
		NcButton,
		NcLoadingIcon,
		CnDashboardPage,
		Refresh,
		AccountGroup,
		AccountMultiple,
		CalendarStar,
		Sword,
	},
	data() {
		return {
			loading: false,
			dashboardLayout: [...DEFAULT_LAYOUT],
		}
	},
	computed: {
		objectStore() {
			return useObjectStore(pinia)
		},
		widgetDefs() {
			return [
				{ id: 'count-characters', title: t('larpingapp', 'Characters') },
				{ id: 'count-events', title: t('larpingapp', 'Events') },
				{ id: 'count-items', title: t('larpingapp', 'Items') },
				{ id: 'count-players', title: t('larpingapp', 'Players') },
				{ id: 'recent-characters', title: t('larpingapp', 'Recent Characters') },
				{ id: 'recent-events', title: t('larpingapp', 'Recent Events') },
			]
		},
		characters() {
			return this.objectStore.getCollection('character').results || []
		},
		events() {
			return this.objectStore.getCollection('event').results || []
		},
	},
	async mounted() {
		await this.refreshData()
	},
	methods: {
		getPagination(type) {
			return this.objectStore.getPagination(type) || {}
		},
		async refreshData() {
			this.loading = true
			try {
				await Promise.allSettled([
					this.objectStore.fetchCollection('character', { _limit: 5 }),
					this.objectStore.fetchCollection('event', { _limit: 5 }),
					this.objectStore.fetchCollection('item', { _limit: 1 }),
					this.objectStore.fetchCollection('player', { _limit: 1 }),
				])
			} catch (error) {
				console.error('Failed to load dashboard data:', error)
			} finally {
				this.loading = false
			}
		},
		onLayoutChange(newLayout) {
			this.dashboardLayout = newLayout
		},
	},
}
</script>

<style scoped>
.kpi-card {
	display: flex;
	align-items: center;
	gap: 12px;
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
	font-size: 24px;
	font-weight: 700;
	line-height: 1.2;
}

.kpi-label {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.list-widget-content {
	overflow: auto;
}

.widget-list {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.widget-list-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 12px;
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
	font-size: 14px;
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.view-all-button {
	padding: 8px 12px;
	margin-top: 4px;
	background: none;
	border: none;
	color: var(--color-primary-element);
	font-weight: 500;
	cursor: pointer;
	text-align: left;
}

.view-all-button:hover {
	text-decoration: underline;
}

.widget-empty {
	padding: 24px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}
</style>
