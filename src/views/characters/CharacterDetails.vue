<script setup>
import { characterStore, conditionStore, eventStore, itemStore, navigationStore, skillStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<!-- app-content-wrapper is optional, only use if app-content-list  -->
			<div>
				<div class="head">
					<h1 class="h1">
						{{ characterStore.characterItem.name }}
					</h1>
					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editCharacter')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addSkillToCharacter')">
							<template #icon>
								<FileDocumentPlusOutline :size="20" />
							</template>
							Skill toevoegen
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addItemToCharacter')">
							<template #icon>
								<AccountPlus :size="20" />
							</template>
							Item toevoegen
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addConditionToCharacter')">
							<template #icon>
								<CalendarPlus :size="20" />
							</template>
							Conditie toevoegen
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('addEventToCharacter')">
							<template #icon>
								<CalendarPlus :size="20" />
							</template>
							Event toevoegen
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('downloadPdfFromCharacter')">
							<template #icon>
								<MessagePlus :size="20" />
							</template>
							Als pdf downloaden
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteCharacter')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>
				<div class="detailGrid">
					<div>
						<b>Omschrijving:</b>
						<span>{{ characterStore.characterItem.description }}</span>
					</div>
				</div>
				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>
						<BTab title="Eigenschappen" active>
							Eigenschappen
						</BTab>

						<BTab title="Skills">
							<div v-if="filterSkills?.length > 0 && !skillsLoading">
								<NcListItem v-for="(skill, i) in filterSkills"
									:key="skill.id + i"
									:name="skill.name"
									:bold="false"
									:force-display-actions="true">
									<template #icon>
										<SwordCross disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ skill.description }}
									</template>
									<template #actions>
										<NcActionButton :aria-label="`Go to skill '${skill.name}'`"
											@click="skillStore.setSkillItem(skill); navigationStore.setSelected('skills')">
											<template #icon>
												<EyeArrowRight :size="20" />
											</template>
											Bekijken
										</NcActionButton>
										<NcActionButton :aria-label="`Edit '${skill.name}'`"
											@click="skillStore.setSkillItem(skill); navigationStore.setModal('editSkill')">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Bewerken
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!filterSkills?.length">
								Geen skills gevonden
							</div>
						</BTab>

						<BTab title="Items">
							<div v-if="filterItems?.length > 0 && !itemsLoading">
								<NcListItem v-for="(item, i) in filterItems"
									:key="item.id + i"
									:name="item.name"
									:bold="false"
									:force-display-actions="true">
									<template #icon>
										<Sword disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ item.description }}
									</template>
									<template #actions>
										<NcActionButton :aria-label="`Go to item '${item.name}'`"
											@click="itemStore.setItemItem(item); navigationStore.setSelected('items')">
											<template #icon>
												<EyeArrowRight :size="20" />
											</template>
											Bekijken
										</NcActionButton>
										<NcActionButton :aria-label="`Edit '${item.name}'`"
											@click="itemStore.setItemItem(item); navigationStore.setModal('editItem')">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Bewerken
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!filterItems?.length">
								Geen items gevonden
							</div>
						</BTab>

						<BTab title="Conditions">
							<div v-if="filterConditions?.length > 0 && !conditionsLoading">
								<NcListItem v-for="(condition, i) in filterConditions"
									:key="condition.id + i"
									:name="condition.name"
									:bold="false"
									:force-display-actions="true">
									<template #icon>
										<Sword disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ condition.description }}
									</template>
									<template #actions>
										<NcActionButton :aria-label="`Go to condition '${condition.name}'`"
											@click="conditionStore.setConditionItem(condition); navigationStore.setSelected('conditions')">
											<template #icon>
												<EyeArrowRight :size="20" />
											</template>
											Bekijken
										</NcActionButton>
										<NcActionButton :aria-label="`Edit '${condition.name}'`"
											@click="conditionStore.setConditionItem(condition); navigationStore.setModal('editCondition')">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Bewerken
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!filterConditions?.length">
								Geen conditions gevonden
							</div>
						</BTab>

						<BTab title="Events">
							<div v-if="filterEvents?.length > 0 && !eventsLoading">
								<NcListItem v-for="(event, i) in filterEvents"
									:key="event.id + i"
									:name="event.name"
									:bold="false"
									:force-display-actions="true">
									<template #icon>
										<Sword disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ event.description }}
									</template>
									<template #actions>
										<NcActionButton :aria-label="`Go to event '${event.name}'`"
											@click="eventStore.setEventItem(event); navigationStore.setSelected('events')">
											<template #icon>
												<EyeArrowRight :size="20" />
											</template>
											Bekijken
										</NcActionButton>
										<NcActionButton :aria-label="`Edit '${event.name}'`"
											@click="eventStore.setEventItem(event); navigationStore.setModal('editEvent')">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Bewerken
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!filterEvents?.length">
								Geen events gevonden
							</div>
						</BTab>
					</BTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
// Components
import { BTabs, BTab } from 'bootstrap-vue'
import { NcActions, NcActionButton, NcListItem } from '@nextcloud/vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue'
import CalendarPlus from 'vue-material-design-icons/CalendarPlus.vue'
import MessagePlus from 'vue-material-design-icons/MessagePlus.vue'
import FileDocumentPlusOutline from 'vue-material-design-icons/FileDocumentPlusOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import EyeArrowRight from 'vue-material-design-icons/EyeArrowRight.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import Sword from 'vue-material-design-icons/Sword.vue'

export default {
	name: 'CharacterDetails',
	components: {
		// Components
		NcActions,
		NcActionButton,
		NcListItem,
		BTabs,
		BTab,
		// Icons
		DotsHorizontal,
		Pencil,
		AccountPlus,
		CalendarPlus,
		FileDocumentPlusOutline,
		TrashCanOutline,

	},
	data() {
		return {
			skillsLoading: false,
			itemsLoading: false,
			conditionsLoading: false,
			eventsLoading: false,
		}
	},
	computed: {
		filterSkills() {
			return skillStore.skillList.filter((skill) => {
				return characterStore.characterItem?.skills.map(String).includes(skill.id.toString())
			})
		},
		filterItems() {
			return itemStore.itemList.filter((item) => {
				return characterStore.characterItem?.items.map(String).includes(item.id.toString())
			})
		},
		filterConditions() {
			return conditionStore.conditionList.filter((item) => {
				return characterStore.characterItem?.conditions.map(String).includes(item.id.toString())
			})
		},
		filterEvents() {
			return eventStore.eventList.filter((item) => {
				return characterStore.characterItem?.events.map(String).includes(item.id.toString())
			})
		},
	},
	mounted() {
		this.fetchSkills()
		this.fetchItems()
		this.fetchConditions()
		this.fetchEvents()
	},
	methods: {
		fetchSkills() {
			this.skillsLoading = true
			skillStore.refreshSkillList()
				.then(() => {
					this.skillsLoading = false
				})
		},
		fetchItems() {
			this.itemsLoading = true
			itemStore.refreshItemList()
				.then(() => {
					this.itemsLoading = false
				})
		},
		fetchConditions() {
			this.conditionsLoading = true
			conditionStore.refreshConditionList()
				.then(() => {
					this.conditionsLoading = false
				})
		},
		fetchEvents() {
			this.eventsLoading = true
			eventStore.refreshEventList()
				.then(() => {
					this.eventsLoading = false
				})
		},
	},
}
</script>

<style>

h4 {
  font-weight: bold;
}

.head{
	display: flex;
	justify-content: space-between;
}

.button{
	max-height: 10px;
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

.dataContent {
  display: flex;
  flex-direction: column;
}

</style>
