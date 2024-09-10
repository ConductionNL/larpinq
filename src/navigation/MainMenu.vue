<script setup>
import { navigationStore } from '../store/store.js'
</script>

<template>
	<NcAppNavigation>
		<NcAppNavigationList>
			<NcAppNavigationNew text="Karakter toevoegen" @click="navigationStore.setModal('addCharacter')">
				<template #icon>
					<Plus :size="20" />
				</template>
			</NcAppNavigationNew>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'dashboard'"
				name="Dashboard"
				@click="navigationStore.setSelected('dashboard')">
				<template #icon>
					<Finance :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'characters'"
				name="Karakters"
				@click="navigationStore.setSelected('characters')">
				<template #icon>
					<BriefcaseAccountOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'players'"
				name="Spelers"
				@click="navigationStore.setSelected('players')">
				<template #icon>
					<AccountGroupOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'items'"
				name="Items"
				@click="navigationStore.setSelected('items')">
				<template #icon>
					<Sword :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'events'"
				name="Events"
				@click="navigationStore.setSelected('events')">
				<template #icon>
					<CalendarMonthOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'conditions'"
				name="Conditions"
				@click="navigationStore.setSelected('conditions')">
				<template #icon>
					<EmoticonSickOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'search'"
				name="Zoeken"
				@click="navigationStore.setSelected('search')">
				<template #icon>
					<Magnify :size="20" />
				</template>
			</NcAppNavigationItem>
		</NcAppNavigationList>

		<NcAppNavigationSettings>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'abilities'"
				name="Abilities"
				@click="navigationStore.setSelected('abilities')">
				<template #icon>
					<ShieldSwordOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'skills'"
				name="Skills"
				@click="navigationStore.setSelected('skills')">
				<template #icon>
					<SwordCross :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'effects'"
				name="Effects"
				@click="navigationStore.setSelected('effects')">
				<template #icon>
					<MagicStaff :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:active="navigationStore.selected === 'templates'"
				name="Templates"
				@click="navigationStore.setSelected('templates')">
				<template #icon>
					<FileDocumentOutline :size="20" />
				</template>
			</NcAppNavigationItem>
		</NcAppNavigationSettings>
	</NcAppNavigation>
</template>
<script>

import {
	NcAppNavigation,
	NcAppNavigationList,
	NcAppNavigationItem,
	NcAppNavigationNew,
	NcAppNavigationSettings,
} from '@nextcloud/vue'

// Configuration
import Configuration from './Configuration.vue'

// Icons
import Finance from 'vue-material-design-icons/Finance.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'

export default {
	name: 'MainMenu',
	components: {
		NcAppNavigation,
		NcAppNavigationList,
		NcAppNavigationItem,
		NcAppNavigationNew,
		NcAppNavigationSettings,
		// Icons
		ShieldSwordOutline,
		SwordCross,
		MagicStaff,
		Finance,
		Sword,
		AccountGroupOutline,
		CalendarMonthOutline,
		BriefcaseAccountOutline,
		Plus,
		Configuration,
		Magnify,
		FileDocumentOutline,
		EmoticonSickOutline,
	},
}
</script>
