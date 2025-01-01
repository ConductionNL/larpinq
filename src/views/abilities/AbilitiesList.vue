<script setup>
import { abilityStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
    <NcAppContentList>
        <ul>
            <div class="listHeader">
                <NcTextField
                    :value="abilityStore.searchTerm"
                    :show-trailing-button="abilityStore.searchTerm !== ''"
                    label="Search"
                    class="searchField"
                    trailing-button-icon="close"
                    @input="abilityStore.setSearchTerm($event.target.value)"
                    @trailing-button-click="abilityStore.clearSearch()">
                    <Magnify :size="20" />
                </NcTextField>
                <NcActions>
                    <NcActionButton @click="abilityStore.refreshAbilityList()">
                        <template #icon>
                            <Refresh :size="20" />
                        </template>
                        Ververs
                    </NcActionButton>
                    <NcActionButton @click="abilityStore.setAbilityItem(null); navigationStore.setModal('editAbility')">
                        <template #icon>
                            <Plus :size="20" />
                        </template>
                        Vaardigheid toevoegen
                    </NcActionButton>
                </NcActions>
            </div>

            <div v-if="abilityStore.abilityList && abilityStore.abilityList.length > 0 && !abilityStore.isLoadingAbilityList">
                <NcListItem v-for="(ability, i) in abilityStore.abilityList"
                    :key="`${ability}${i}`"
                    :name="ability?.name"
                    :force-display-actions="true"
                    :active="abilityStore.abilityItem?.id === ability?.id"
                    @click="abilityStore.setAbilityItem(ability)">
                    <template #icon>
                        <AccountGroup :class="abilityStore.abilityItem?.id === ability?.id && 'selectedIcon'"
                            disable-menu
                            :size="44" />
                    </template>
                    <template #subname>
                        {{ ability?.description || 'Geen beschrijving' }}
                    </template>
                    <template #actions>
                        <NcActionButton @click="abilityStore.setAbilityItem(ability); navigationStore.setModal('editAbility')">
                            <template #icon>
                                <Pencil />
                            </template>
                            Bewerken
                        </NcActionButton>
                        <NcActionButton @click="abilityStore.setAbilityItem(ability); navigationStore.setDialog('deleteAbility')">
                            <template #icon>
                                <TrashCanOutline />
                            </template>
                            Verwijderen
                        </NcActionButton>
                    </template>
                </NcListItem>
            </div>
        </ul>

        <NcLoadingIcon v-if="abilityStore.isLoadingAbilityList"
            class="loadingIcon"
            :size="64"
            appearance="dark"
            name="Vaardigheden aan het laden" />

        <div v-if="abilityStore.abilityList.length === 0 && !abilityStore.isLoadingAbilityList">
            Er zijn nog geen vaardigheden gedefinieerd.
        </div>
    </NcAppContentList>
</template>

<script>
import { NcListItem, NcActions, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon } from '@nextcloud/vue'
import { abilityStore, navigationStore } from '../../store/store.js'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

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
    },
    mounted() {
        abilityStore.refreshAbilityList()
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
