<script setup>
import { computed } from 'vue'
import { objectStore } from '../store/store.js'
import { BTabs, BTab } from 'bootstrap-vue'
import { NcCounterBubble } from '@nextcloud/vue'
import ObjectList from './ObjectList.vue'
import AuditList from './AuditList.vue'
import StatsList from './StatsList.vue'

const props = defineProps({
	type: {
		type: String,
		required: true,
	},
	object: {
		type: Object,
		required: true,
	},
})

// Convert stats object to array if needed
const statsArray = computed(() => {
	if (!props.object?.stats) return []
	return Array.isArray(props.object.stats) ? props.object.stats : Object.values(props.object.stats)
})

// Get direct uses/skills from the object itself
const directObjects = computed(() => {
	const result = []
	// Add skills if they exist
	if (props.object.skills?.length) {
		result.push({
			title: 'Skills',
			objects: props.object.skills.map(skillId => {
				// Get the full skill object from the store
				const fullSkill = objectStore.getCollection('skill').results.find(s => s.id === skillId)
				return {
					...(fullSkill || {}),
					id: skillId,
					objectType: 'skill',
				}
			}),
		})
	}
	// Add items if they exist
	if (props.object.items?.length) {
		result.push({
			title: 'Items',
			objects: props.object.items.map(itemId => {
				// Get the full item object from the store
				const fullItem = objectStore.getCollection('item').results.find(i => i.id === itemId)
				return {
					...(fullItem || {}),
					id: itemId,
					objectType: 'item',
				}
			}),
		})
	}
	// Add conditions if they exist
	if (props.object.conditions?.length) {
		result.push({
			title: 'Conditions',
			objects: props.object.conditions.map(conditionId => {
				// Get the full condition object from the store
				const fullCondition = objectStore.getCollection('condition').results.find(c => c.id === conditionId)
				return {
					...(fullCondition || {}),
					id: conditionId,
					objectType: 'condition',
				}
			}),
		})
	}
	// Add events if they exist
	if (props.object.events?.length) {
		result.push({
			title: 'Events',
			objects: props.object.events.map(eventId => {
				// Get the full event object from the store
				const fullEvent = objectStore.getCollection('event').results.find(e => e.id === eventId)
				return {
					...(fullEvent || {}),
					id: eventId,
					objectType: 'event',
				}
			}),
		})
	}
	return result
})

// Group uses by schema type
const usesGrouped = computed(() => {
	const usesData = objectStore.getRelatedData(props.type, 'uses')
	const itemsArray = Array.isArray(usesData) ? usesData : (usesData?.results || [])

	// Group by schema type, excluding items that are already in direct tabs
	const groups = {}
	itemsArray.forEach(obj => {
		const schemaTitle = obj['@self.schema.title']
		// Skip items that would go into the "Other" category
		if (!schemaTitle) return

		if (!groups[schemaTitle]) {
			groups[schemaTitle] = []
		}
		groups[schemaTitle].push(obj)
	})

	return Object.entries(groups).map(([title, objects]) => ({
		title,
		objects,
	}))
})

// Group used by schema type
const usedGrouped = computed(() => {
	const usedData = objectStore.getRelatedData(props.type, 'used')
	const itemsArray = Array.isArray(usedData) ? usedData : (usedData?.results || [])

	// Group by schema type
	const groups = {}
	itemsArray.forEach(obj => {
		const schemaTitle = obj['@self.schema.title'] || 'Referenced'
		if (!groups[schemaTitle]) {
			groups[schemaTitle] = []
		}
		groups[schemaTitle].push(obj)
	})

	return Object.entries(groups).map(([title, objects]) => ({
		title: title === 'Referenced' ? 'Referenced' : `Used in ${title}`,
		objects,
	}))
})

const files = computed(() => {
	const files = objectStore.getRelatedData(props.type, 'files')
	return Array.isArray(files) ? files : (files?.results || [])
})

const logs = computed(() => {
	return objectStore.getAuditTrails(props.type) || []
})
</script>

<template>
	<BTabs content-class="mt-3" justified>
		<!-- Stats tab (always shown for characters) -->
		<BTab v-if="type === 'character'" active>
			<template #title>
				Eigenschappen <NcCounterBubble>{{ statsArray.length }}</NcCounterBubble>
			</template>
			<StatsList v-if="statsArray.length" :stats="statsArray" />
			<div v-else class="empty-state">
				Geen eigenschappen gevonden
			</div>
		</BTab>

		<!-- Background tab (always shown for characters) -->
		<BTab v-if="type === 'character'">
			<template #title>
				Background <NcCounterBubble v-if="object.background">
					1
				</NcCounterBubble>
			</template>
			<div v-if="object.background" class="background-content">
				{{ object.background }}
			</div>
			<div v-else class="empty-state">
				Geen background gevonden
			</div>
		</BTab>

		<!-- Direct object tabs (skills, items, conditions, events) -->
		<BTab v-for="group in directObjects" :key="group.title">
			<template #title>
				{{ group.title }} <NcCounterBubble>{{ group.objects.length }}</NcCounterBubble>
			</template>
			<ObjectList :objects="group.objects" show-titles />
		</BTab>

		<!-- Grouped uses tabs -->
		<BTab v-for="group in usesGrouped" :key="'uses-' + group.title">
			<template #title>
				{{ group.title }} <NcCounterBubble>{{ group.objects.length }}</NcCounterBubble>
			</template>
			<ObjectList :objects="group.objects" show-titles />
		</BTab>

		<!-- Grouped used tabs -->
		<BTab v-for="group in usedGrouped" :key="'used-' + group.title">
			<template #title>
				{{ group.title }} <NcCounterBubble>{{ group.objects.length }}</NcCounterBubble>
			</template>
			<ObjectList :objects="group.objects" show-titles />
		</BTab>

		<!-- Files tab -->
		<BTab v-if="files.length">
			<template #title>
				Files <NcCounterBubble>{{ files.length }}</NcCounterBubble>
			</template>
			<ObjectList :objects="files" show-titles />
		</BTab>

		<!-- Logs tab -->
		<BTab>
			<template #title>
				Logging <NcCounterBubble>{{ logs.length }}</NcCounterBubble>
			</template>
			<AuditList :logs="logs" />
		</BTab>
	</BTabs>
</template>

<style>
.background-content {
    padding: 1rem;
    white-space: pre-wrap;
}

.empty-state {
    padding: 1rem;
    text-align: center;
    color: var(--color-text-maxcontrast);
}

/* Add margin to counter bubble only when inside nav-item */
.nav-item .counter-bubble__counter {
    margin-left: 10px;
}
</style>
