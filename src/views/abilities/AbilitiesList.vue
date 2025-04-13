<script setup>
import { objectStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
    <div class="abilitiesList">
        <div class="abilitiesHeader">
            <NcTextField
                :value="searchStore.getSearchTerm('ability')"
                :show-trailing-button="searchStore.getSearchTerm('ability') !== ''"
                type="search"
                label="Zoeken"
                @input="searchStore.setSearchTerm('ability', $event.target.value)"
                @trailing-button-click="searchStore.clearSearchTerm('ability')">
                <template #trailing-button-icon>
                    <Close :size="20" />
                </template>
            </NcTextField>

            <div class="abilitiesActions">
                <NcActions>
                    <NcActionButton @click="objectStore.refreshObjectList('ability')">
                        <template #icon>
                            <Refresh :size="20" />
                        </template>
                        Vernieuwen
                    </NcActionButton>
                    <NcActionButton @click="objectStore.clearActiveObject('ability'); navigationStore.setModal('editAbility')">
                        <template #icon>
                            <Plus :size="20" />
                        </template>
                        Nieuwe vaardigheid
                    </NcActionButton>
                </NcActions>
            </div>
        </div>

        <div v-if="objectStore.getObjectList('ability')?.length > 0 && !objectStore.isLoading('ability')" class="abilityItems">
            <NcListItem v-for="ability in objectStore.getObjectList('ability')"
                :key="ability.id"
                :title="ability.name"
                :active="objectStore.getActiveObject('ability')?.id === ability.id"
                @click="selectAbility(ability)">
                <template #icon>
                    <MagicStaff :class="objectStore.getActiveObject('ability')?.id === ability.id && 'selectedAbilityIcon'" :size="20" />
                </template>
                <template #actions>
                    <NcActions>
                        <NcActionButton @click.stop="objectStore.setActiveObject('ability', ability); navigationStore.setModal('editAbility')">
                            <template #icon>
                                <Pencil :size="20" />
                            </template>
                            Bewerken
                        </NcActionButton>
                        <NcActionButton @click.stop="objectStore.setActiveObject('ability', ability); navigationStore.setDialog('deleteAbility')">
                            <template #icon>
                                <TrashCanOutline :size="20" />
                            </template>
                            Verwijderen
                        </NcActionButton>
                    </NcActions>
                </template>
            </NcListItem>
        </div>

        <div v-if="objectStore.isLoading('ability')" class="abilitiesLoading">
            <NcLoadingIcon :size="50" />
        </div>

        <div v-if="objectStore.getObjectList('ability')?.length === 0 && !objectStore.isLoading('ability')" class="abilitiesEmpty">
            <NcEmptyContent
                icon="icon-category-customization"
                title="Geen vaardigheden gevonden">
                <template #action>
                    <NcButton type="primary" @click="objectStore.clearActiveObject('ability'); navigationStore.setModal('editAbility')">
                        <template #icon>
                            <Plus :size="20" />
                        </template>
                        Nieuwe vaardigheid
                    </NcButton>
                </template>
            </NcEmptyContent>
        </div>
    </div>
</template>

<script>
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'
import { navigationStore } from '../../store/store.js'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Close from 'vue-material-design-icons/Close.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
    name: 'AbilitiesList',
    components: {
        NcListItem,
        NcActions,
        NcActionButton,
        NcAppContentList,
        NcTextField,
        NcLoadingIcon,
        Magnify,
        Refresh,
        Plus,
        Pencil,
        TrashCanOutline,
        AccountGroup,
        Close,
        MagicStaff,
        NcEmptyContent,
        NcButton,
    },
    mounted() {
        objectStore.refreshObjectList('ability')
    },
    methods: {
        selectAbility(ability) {
            objectStore.setActiveObject('ability', ability)
            navigationStore.setSelected('abilities')
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

.selectedIcon>svg {
    fill: var(--color-primary);
}

.loadingIcon {
    margin-block-start: var(--OC-margin-20);
}
</style>
