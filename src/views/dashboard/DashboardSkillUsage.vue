<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Skill-usage chart widget for the larpingapp dashboard. Thin wrapper
 around SkillUsageChart so the manifest can mount it via the
 customComponents registry without referencing a deep file path.
-->
<template>
	<SkillUsageChart />
</template>

<script>
import SkillUsageChart from './SkillUsageChart.vue'

export default {
	name: 'DashboardSkillUsage',
	components: { SkillUsageChart },
}
</script>
