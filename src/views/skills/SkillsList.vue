<script setup>
import { skillStore, navigationStore, searchStore, characterStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value.sync="searchStore.search"
					:show-trailing-button="searchStore.search !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@trailing-button-click="searchStore.setSearch('')">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="skillStore.refreshSkillList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="skillStore.setSkillItem(null); navigationStore.setModal('editSkill')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Vaardigheid toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="skillStore.skillList && skillStore.skillList.length > 0">
				<NcListItem v-for="(skill, i) in skillStore.skillList"
					:key="`${skill}${i}`"
					:name="skill?.name"
					:active="skillStore.skillItem?.id === skill?.id"
					:details="(skill?.effects?.length || '0') + ' effect(s)'"
					:force-display-actions="true"
					@click="handleSkillSelect(skill)">
					<template #icon>
						<SwordCross :class="skillStore.skillItem === skill.id && 'selectedSkillIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ skill?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="handleSkillSelect(skill); navigationStore.setModal('editSkill')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="handleSkillSelect(skill), navigationStore.setDialog('deleteSkill')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!skillStore.skillList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Zaken aan het laden" />

		<div v-if="skillStore.skillList.length === 0">
			Er zijn nog geen vaardigheden gedefinieerd.
		</div>
	</NcAppContentList>
</template>
<script>
// Components
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'

// Icons
import Magnify from 'vue-material-design-icons/Magnify.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

export default {
	name: 'SkillsList',
	components: {
		// Components
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		// Icons
		SwordCross,
		Magnify,
		Plus,
		TrashCanOutline,
		Refresh,
	},
	mounted() {
		skillStore.refreshSkillList()
	},
	methods: {
		/**
		 * Handle skill selection
		 * @param {object} skill - The selected skill object
		 * @returns {Promise<void>}
		 */
		async handleSkillSelect(skill) {
			// Set the selected skill in the store
			skillStore.setSkillItem(skill)

			try {
				// Fetch audit trails and relations for the selected skill
				await Promise.all([
					skillStore.getRelations(skill.id),
					skillStore.getAuditTrails(skill.id),
				])
			} catch (error) {
				console.error('Error fetching skill data:', error)
			}
		},
	},
}
</script>
<style>
.listHeader {
    position: sticky;
    top: 0;
    z-index: 1000;
    background-color: var(--color-main-background);
    border-bottom: 1px solid var(--color-border);
}

.searchField {
    padding-inline-start: 65px;
    padding-inline-end: 20px;
    margin-block-end: 6px;
}

.selectedZaakIcon>svg {
    fill: white;
}

.loadingIcon {
    margin-block-start: var(--zaa-margin-20);
}
</style>
