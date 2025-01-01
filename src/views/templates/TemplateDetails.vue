<script setup>
import { templateStore, navigationStore } from '../../store/store.js'
import DOMPurify from 'dompurify'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<div>
				<div class="head">
					<h1 class="h1">
						{{ templateStore.templateItem.name }}
					</h1>
					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editTemplate')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteTemplate')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<div class="detailGrid">
					<div>
						<b>Beschrijving:</b>
						<span>{{ templateStore.templateItem.description }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="tabContainer">
			<BTabs content-class="mt-3" justified>
				<BTab title="Content" active>
					<NcGuestContent>
						<NcRichText
							:text="DOMPurify.sanitize(templateStore.templateItem.template)"
							:autolink="true"
							:use-markdown="true" />
					</NcGuestContent>
				</BTab>
				<BTab title="Relations">
					<ObjectList :objects="templateStore.relations" />
				</BTab>
				<BTab title="Logging">
					<AuditList :logs="templateStore.auditTrails" />
				</BTab>
			</BTabs>
		</div>
	</div>
</template>

<script>
import { BTabs, BTab } from 'bootstrap-vue'
import { NcLoadingIcon, NcActions, NcActionButton, NcGuestContent, NcRichText } from '@nextcloud/vue'

// Custom components
import AuditList from '../auditTrail/AuditList.vue'
import ObjectList from '../objects/ObjectList.vue'

import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'

export default {
	name: 'TemplateDetails',
	components: {
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		BTabs,
		BTab,
		// Custom components
		AuditList,
		ObjectList,
		// Icons
		Pencil,
		TrashCanOutline,
		DotsHorizontal,
		NcGuestContent,
		NcRichText,
	},
}
</script>

<style>
h4 {
	font-weight: bold
}

.h1 {
	display: block !important;
	font-size: 2em !important;
	margin-block-start: 0.67em !important;
	margin-block-end: 0.67em !important;
	margin-inline-start: 0px !important;
	margin-inline-end: 0px !important;
	font-weight: bold !important;
	unicode-bidi: isolate !important;
}

.detailGrid {
	display: grid;
	grid-gap: 24px;
	grid-template-columns: 1fr;
	margin-block-start: var(--zaa-margin-50);
	margin-block-end: var(--zaa-margin-50);
}

.gridContent {
	display: flex;
	gap: 25px;
}

.tabContainer {
	margin-top: 20px;
	padding: 0 20px;
}

#guest-content-vue {
	margin: 20px 5px !important;
}

.tab-content {
	padding: 20px 0;
}
</style>
