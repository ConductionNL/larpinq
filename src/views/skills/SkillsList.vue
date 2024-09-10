<script setup>
	import { skillStore, navigationStore, searchStore } from '../../store/store.js'
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
					<NcActionButton @click="skillStore.setSkillItem([]); navigationStore.setModal('editSkill')">
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
					:active="skillStore.skillItem === skill?.id"
					:details="'1h'"
					:counter-number="44"
					@click="skillStore.setSkillItem(skill)">
					<template #icon>
						<SwordCross :class="skillStore.skillItem === skill.id && 'selectedSkillIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ skill?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="skillStore.setSkillItem(skill); navigationStore.setModal('editSkill')">
							<template #icon>
								<Plus/>
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="skillStore.setSkillItem(skill), navigationStore.setDialog('deleteSkill')">
							<template #icon>
								<TrashCanOutline/>
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
		Pencil,
		TrashCanOutline,
		Refresh,
	},
	mounted() {
		skillStore.refreshSkillList()
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
