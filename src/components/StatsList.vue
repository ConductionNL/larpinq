<script setup>
import { NcListItem } from '@nextcloud/vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'

defineProps({
	stats: {
		type: Array,
		required: true,
		default: () => [],
	},
})
</script>

<template>
	<div class="stats-list">
		<NcListItem v-for="stat in stats"
			:key="stat.id"
			:name="stat.value + ' ' + stat.name"
			:bold="false">
			<template #icon>
				<ShieldSwordOutline :size="44" />
			</template>
			<template #subname>
				<div class="stat-effects">
					Base: {{ stat.base }}{{ stat.audit ? ', Effects: ' + stat.audit.map(a => `(${a.type.charAt(0).toUpperCase() + a.type.slice(1)}:${a.name} ${a.effect.modification > 0 ? '+' : '-'}${a.effect.modifier})`).join(', ') : '' }}
				</div>
			</template>
		</NcListItem>
		<div v-if="!stats.length" class="empty-stats">
			Geen eigenschappen gevonden
		</div>
	</div>
</template>

<style>
.stats-list {
    padding: 1rem;
}

.stat-effects {
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
    line-height: 1.4;
    padding: 4px 0;
}

.empty-stats {
    text-align: center;
    color: var(--color-text-maxcontrast);
    padding: 2rem;
}
</style>
