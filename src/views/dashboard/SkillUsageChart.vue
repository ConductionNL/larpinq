<template>
	<div class="skill-usage-chart">
		<h3>Skill Usage by Characters</h3>

		<div v-if="loading" class="chart-loading">
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
			height="350"
			:options="chartOptions"
			:series="chartSeries" />
	</div>
</template>

<script>
import VueApexCharts from 'vue-apexcharts'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { queryGraphQL } from '../../services/graphql.js'

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
	padding: 1rem;
	border-radius: 8px;
	min-height: 200px;
}

@media (prefers-color-scheme: light) {
	.skill-usage-chart {
		background-color: rgba(0, 0, 0, 0.07);
	}
}

@media (prefers-color-scheme: dark) {
	.skill-usage-chart {
		background-color: rgba(255, 255, 255, 0.1);
	}
}

body[data-theme-light] .skill-usage-chart {
	background-color: rgba(0, 0, 0, 0.07);
}

body[data-theme-dark] .skill-usage-chart {
	background-color: rgba(255, 255, 255, 0.1);
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
