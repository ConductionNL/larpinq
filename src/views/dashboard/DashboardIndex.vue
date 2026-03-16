<template>
	<NcAppContent>
		<div class="dashboard-content">
			<h2 class="pageHeader">
				Dashboard
			</h2>

			<div class="graphs">
				<SkillUsageChart />
			</div>
		</div>
	</NcAppContent>
</template>

<script>

import { NcAppContent } from '@nextcloud/vue'
import SkillUsageChart from './SkillUsageChart.vue'

export default {
	name: 'DashboardIndex',
	components: {
		NcAppContent,
		SkillUsageChart,
	},
}
</script>

<style>
.dashboard-content {
	margin-inline: auto;
	max-width: 1000px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
}

.dashboard-content > * {
	margin-block-end: 4rem;
}

/* graphs */
.dashboard-content > .graphs {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 2rem;
	width: 100%;
}

@media screen and (max-width: 1800px) {
	.dashboard-content > .graphs {
		display: grid;
		grid-template-columns: 1fr;
		gap: 2rem;
	}
}

.dashboard-content > .graphs .full-width {
	grid-column-start: 1;
	grid-column-end: 3;
}

.dashboard-content > .graphs .content {
	display: flex;
	gap: 4px;
}
</style>
