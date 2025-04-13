<script setup>
import { objectStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="templates">
		<NcEmptyContent v-if="!objectStore.getActiveObject('template') || navigationStore.selected != 'templates'"
			icon="icon-template"
			title="Templates">
			<template #action>
				<div class="buttons">
					<NcButton type="primary" @click="objectStore.setActiveObject('template', null); navigationStore.setModal('editTemplate')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Template toevoegen
					</NcButton>
				</div>
			</template>
		</NcEmptyContent>
		<TemplateDetails v-if="objectStore.getActiveObject('template') && navigationStore.selected === 'templates'" />
	</div>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import TemplatesList from './TemplatesList.vue'
import TemplateDetails from './TemplateDetails.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'TemplatesIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		TemplatesList,
		TemplateDetails,
		FileDocumentOutline,
		Plus,
	},
}
</script>
