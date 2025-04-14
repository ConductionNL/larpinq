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

// Create a dynamic tabs array based on Uses and Used data
const tabs = computed(() => {
	const tabsMap = new Map()

	// Helper function to add objects to tabs
	const addObjectsToTabs = (objects, isUsedIn = false) => {
		objects.forEach(obj => {
			// Try to get the schema title, fallback to id or '@self.schema' if not available
			const schemaTitle = obj['@self']?.schema?.title || obj['@self']?.schema?.id || obj['@self']?.schema

			const tabKey = schemaTitle
			if (!tabsMap.has(tabKey)) {
				tabsMap.set(tabKey, {
					title: schemaTitle,
					objects: [],
				})
			}

			// Add the object with its source type
			tabsMap.get(tabKey).objects.push({
				...obj,
				isUsedIn,
				objectType: obj['@self.schema']?.slug || 'unknown',
			})
		})
	}

	// Get and process Uses data
	const usesData = objectStore.getRelatedData(props.type, 'uses')
	const usesItems = Array.isArray(usesData) ? usesData : (usesData?.results || [])
	addObjectsToTabs(usesItems, false)

	// Get and process Used data
	const usedData = objectStore.getRelatedData(props.type, 'used')
	const usedItems = Array.isArray(usedData) ? usedData : (usedData?.results || [])
	addObjectsToTabs(usedItems, true)

	// Convert Map to Array and sort by title
	return Array.from(tabsMap.values()).sort((a, b) => a.title.localeCompare(b.title))
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
		<!-- Stats tab (for characters) -->
		<BTab v-if="type === 'character'" active>
			<template #title>
				Eigenschappen <NcCounterBubble>{{ statsArray.length }}</NcCounterBubble>
			</template>
			<StatsList v-if="statsArray.length" :stats="statsArray" />
			<div v-else class="empty-state">
				Geen eigenschappen gevonden
			</div>
		</BTab>

		<!-- Background tab (for characters) -->
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

		<!-- Dynamic tabs based on schema titles -->
		<BTab v-for="tab in tabs" :key="tab.title">
			<template #title>
				{{ tab.title }} <NcCounterBubble>{{ tab.objects.length }}</NcCounterBubble>
			</template>
			<div v-if="tab.objects.length > 0">
				<!-- Group objects by their usage type -->
				<div v-if="tab.objects.some(obj => !obj.isUsedIn)" class="tab-section">
					<h3>Uses {{ tab.title }}</h3>
					<ObjectList :objects="tab.objects.filter(obj => !obj.isUsedIn)" />
				</div>
				<div v-if="tab.objects.some(obj => obj.isUsedIn)" class="tab-section">
					<h3>Used in {{ tab.title }}</h3>
					<ObjectList :objects="tab.objects.filter(obj => obj.isUsedIn)" />
				</div>
			</div>
			<div v-else class="empty-state">
				Geen {{ tab.title.toLowerCase() }} gevonden
			</div>
		</BTab>

		<!-- Files tab -->
		<BTab v-if="files.length">
			<template #title>
				Files <NcCounterBubble>{{ files.length }}</NcCounterBubble>
			</template>
			<ObjectList :objects="files" />
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

.tab-section {
	margin-bottom: 1.5rem;
}

.tab-section h3 {
	margin-bottom: 1rem;
	color: var(--color-text-maxcontrast);
}

/* Add margin to counter bubble only when inside nav-item */
.nav-item .counter-bubble__counter {
	margin-left: 10px;
}
</style>
