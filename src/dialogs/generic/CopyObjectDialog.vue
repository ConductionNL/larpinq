<script setup>
import { NcButton, NcDialog, NcNoteCard, NcLoadingIcon } from '@nextcloud/vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import { navigationStore, objectStore } from '../../store/store.js'
import { computed } from 'vue'

const dialogProperties = computed(() => navigationStore.dialogProperties)

const objectType = computed(() => dialogProperties.value?.objectType)
const dialogTitle = computed(() => dialogProperties.value?.dialogTitle)
const isMultiple = computed(() => dialogProperties.value?.isMultiple ?? false)

// Check if this dialog should be shown
const shouldShowDialog = computed(() => navigationStore.dialog === 'copyObject')

/**
 * Copy the object(s)
 *
 * @return {void}
 */
const copyObject = () => {
	if (isMultiple.value) {
		const selectedObjects = objectStore.getSelectedObjects(objectType.value)
		if (!selectedObjects?.length) return

		Promise.all(selectedObjects.map(obj =>
			objectStore.copyObject(objectType.value, obj.id),
		))
			.then(() => {
				closeDialog()
			})
	} else {
		const activeObject = objectStore.getActiveObject(objectType.value)
		if (!activeObject?.id) return

		objectStore.copyObject(objectType.value, activeObject.id)
			.then(() => {
				closeDialog()
			})
	}
}

/**
 * Close the dialog after a delay
 *
 * @return {void}
 */
const closeDialog = () => {
	setTimeout(() => {
		objectStore.setState(objectType.value, { success: null, error: null })
		navigationStore.setDialog(false)
	}, 2000)
}
</script>

<template>
	<NcDialog
		v-if="shouldShowDialog"
		:name="`${dialogTitle}${isMultiple ? 's' : ''} kopiëren`"
		:can-close="false">
		<div v-if="objectStore.getState(objectType).success !== null || objectStore.getState(objectType).error">
			<NcNoteCard v-if="objectStore.getState(objectType).success" type="success">
				<p>{{ dialogTitle }}{{ isMultiple ? 's' : '' }} succesvol gekopieerd</p>
			</NcNoteCard>
			<NcNoteCard v-if="!objectStore.getState(objectType).success" type="error">
				<p>Er is iets fout gegaan bij het kopiëren van {{ dialogTitle.toLowerCase() }}{{ isMultiple ? 's' : '' }}</p>
			</NcNoteCard>
			<NcNoteCard v-if="objectStore.getState(objectType).error" type="error">
				<p>{{ objectStore.getState(objectType).error }}</p>
			</NcNoteCard>
		</div>
		<div v-if="objectStore.isLoading(objectType)" class="loading-status">
			<NcLoadingIcon :size="20" />
			<span>{{ dialogTitle }}{{ isMultiple ? 's' : '' }} {{ isMultiple ? 'worden' : 'wordt' }} gekopieerd...</span>
		</div>
		<p v-if="objectStore.getState(objectType).success === null && !objectStore.isLoading(objectType)">
			<template v-if="isMultiple">
				Wil je de geselecteerde {{ dialogTitle.toLowerCase() }}s kopiëren?
			</template>
			<template v-else>
				Wil je <b>{{ objectStore.getActiveObject(objectType)?.name || objectStore.getActiveObject(objectType)?.title }}</b> kopiëren?
			</template>
		</p>
		<template v-if="objectStore.getState(objectType).success === null && !objectStore.isLoading(objectType)" #actions>
			<NcButton
				:disabled="objectStore.isLoading(objectType)"
				icon=""
				@click="navigationStore.setDialog(false)">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Annuleer
			</NcButton>
			<NcButton
				:disabled="objectStore.isLoading(objectType)"
				icon="ContentCopy"
				type="primary"
				@click="copyObject">
				<template #icon>
					<ContentCopy :size="20" />
				</template>
				Kopiëren
			</NcButton>
		</template>
		<template v-else #actions>
			<NcButton
				icon=""
				@click="navigationStore.setDialog(false)">
				<template #icon>
					<Cancel :size="20" />
				</template>
				Sluiten
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
/**
 * Copy Object Dialog Component
 * @module Dialogs
 * @package
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @see {@link https://github.com/opencatalogi/opencatalogi}
 */
export default {
	name: 'CopyObjectDialog',
	components: {
		NcDialog,
		NcButton,
		NcNoteCard,
		NcLoadingIcon,
		// Icons
		Cancel,
		ContentCopy,
	},
	methods: {
		/**
		 * Copy the object(s)
		 *
		 * @return {void}
		 */
		copyObject() {
			if (this.isMultiple) {
				const selectedObjects = objectStore.getSelectedObjects(this.objectType)
				if (!selectedObjects?.length) return

				Promise.all(selectedObjects.map(obj =>
					objectStore.copyObject(this.objectType, obj.id),
				))
					.then(() => {
						this.closeDialog()
					})
			} else {
				const activeObject = objectStore.getActiveObject(this.objectType)
				if (!activeObject?.id) return

				objectStore.copyObject(this.objectType, activeObject.id)
					.then(() => {
						this.closeDialog()
					})
			}
		},
		/**
		 * Close the dialog after a delay
		 *
		 * @return {void}
		 */
		closeDialog() {
			setTimeout(() => {
				objectStore.setState(this.objectType, { success: null, error: null })
				navigationStore.setDialog(false)
			}, 2000)
		},
	},
}
</script>

<style>
.modal__content {
    margin: var(--OC-margin-50);
    text-align: center;
}

.zaakDetailsContainer {
    margin-block-start: var(--OC-margin-20);
    margin-inline-start: var(--OC-margin-20);
    margin-inline-end: var(--OC-margin-20);
}

.success {
    color: green;
}

.loading-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin: 1rem 0;
    color: var(--color-text-lighter);
}
</style>
