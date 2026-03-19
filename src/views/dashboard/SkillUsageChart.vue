<template>
	<div class="skill-usage-chart">
		<div v-if="!openRegisterConfigured" class="chart-empty">
			<p>Configure OpenRegister data source to enable this widget</p>
		</div>

		<div v-else-if="loading" class="chart-loading">
			<NcLoadingIcon :size="44" />
			<span>Loading skill data...</span>
		</div>

		<div v-else-if="error" class="chart-error">
			<p>{{ error }}</p>
			<NcButton type="secondary" @click="fetchData">
				Retry
			</NcButton>
		</div>

		<div v-else-if="!hasData" class="chart-empty">
			<p>No skill data available</p>
		</div>

		<VueApexCharts
			v-else
			type="donut"
			height="280"
			:options="chartOptions"
			:series="chartSeries" />
	</div>
</template>

<script>
import VueApexCharts from 'vue-apexcharts'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { queryGraphQL } from '../../services/graphql.js'
import { useSettingsStore } from '../../store/modules/settings.js'

export default {
	name: 'SkillUsageChart',
	components: {
		VueApexCharts,
		NcButton,
		NcLoadingIcon,
	},
	data() {
		return {
			loading: true,
			error: null,
			skillLabels: [],
			skillCounts: [],
			openRegisterConfigured: true,
		}
	},
	computed: {
		hasData() {
			return this.skillLabels.length > 0
		},
		chartSeries() {
			return this.skillCounts
		},
		chartOptions() {
			return {
				chart: {
					type: 'donut',
					background: 'transparent',
				},
				labels: this.skillLabels,
				theme: {
					mode: this.isDarkTheme ? 'dark' : 'light',
				},
				legend: {
					position: 'bottom',
				},
				plotOptions: {
					pie: {
						donut: {
							size: '55%',
						},
					},
				},
				dataLabels: {
					enabled: true,
					formatter(val) {
						return Math.round(val) + '%'
					},
				},
				tooltip: {
					y: {
						formatter(val) {
							return val + ' characters'
						},
					},
				},
			}
		},
		isDarkTheme() {
			return document.body.dataset.themeDark !== undefined
				|| (window.matchMedia
					&& window.matchMedia('(prefers-color-scheme: dark)').matches
					&& document.body.dataset.themeLight === undefined)
		},
	},
	mounted() {
		this.fetchData()
	},
	methods: {
		async fetchData() {
			this.loading = true
			this.error = null

			try {
				// Check if character source is configured for OpenRegister
				const settingsStore = useSettingsStore()
				if (!settingsStore.isInitialized) {
					await settingsStore.fetchSettings()
				}
				const config = settingsStore.getConfig
				const characterSource = config?.character_source
				if (characterSource && characterSource !== 'openregister') {
					this.openRegisterConfigured = false
					return
				}
				this.openRegisterConfigured = true

				const result = await queryGraphQL(`{
					character(first: 1, facets: ["skills"]) {
						totalCount
						facets
					}
				}`)

				const characterData = result?.data?.character
				if (!characterData) {
					this.skillLabels = []
					this.skillCounts = []
					return
				}

				const facets = characterData.facets
				const skillFacet = facets?.skills?.data?.buckets || []

				if (skillFacet.length === 0) {
					this.skillLabels = []
					this.skillCounts = []
					return
				}

				const sorted = [...skillFacet].sort((a, b) => b.count - a.count)
				const top = sorted.slice(0, 10)
				const rest = sorted.slice(10)

				this.skillLabels = top.map(b => b.label || b.value)
				this.skillCounts = top.map(b => b.count)

				if (rest.length > 0) {
					const otherCount = rest.reduce((sum, b) => sum + b.count, 0)
					this.skillLabels.push('Other')
					this.skillCounts.push(otherCount)
				}
			} catch (err) {
				this.error = err.message || 'Failed to load skill data'
				console.error('[SkillUsageChart] Error:', err)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.skill-usage-chart {
	height: 100%;
	box-sizing: border-box;
}

.skill-usage-chart h3 {
	margin-top: 0;
	margin-bottom: 1rem;
}

.chart-loading,
.chart-empty,
.chart-error {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 200px;
	gap: 1rem;
	color: var(--color-text-maxcontrast);
}

.chart-error p {
	color: var(--color-error);
}
</style>
