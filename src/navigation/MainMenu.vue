<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Dashboard')"
				:to="{ name: 'Dashboard' }">
				<template #icon>
					<ViewDashboard :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Characters')"
				:to="{ name: 'Characters' }">
				<template #icon>
					<BriefcaseAccountOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Players')"
				:to="{ name: 'Players' }">
				<template #icon>
					<AccountGroupOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Items')"
				:to="{ name: 'Items' }">
				<template #icon>
					<Sword :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Events')"
				:to="{ name: 'Events' }">
				<template #icon>
					<CalendarMonthOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Conditions')"
				:to="{ name: 'Conditions' }">
				<template #icon>
					<EmoticonSickOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Documentation')"
				@click="openLink('https://larpingapp.com', '_blank')">
				<template #icon>
					<BookOpenVariantOutline :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
		<template #footer>
			<NcAppNavigationSettings>
				<NcAppNavigationItem
					:name="t('larpingapp', 'Abilities')"
					:to="{ name: 'Abilities' }">
					<template #icon>
						<ShieldSwordOutline :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="t('larpingapp', 'Skills')"
					:to="{ name: 'Skills' }">
					<template #icon>
						<SwordCross :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="t('larpingapp', 'Effects')"
					:to="{ name: 'Effects' }">
					<template #icon>
						<MagicStaff :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="t('larpingapp', 'Settings')"
					@click="$emit('open-settings')">
					<template #icon>
						<Cog :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationSettings } from '@nextcloud/vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import EmoticonSickOutline from 'vue-material-design-icons/EmoticonSickOutline.vue'
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import SwordCross from 'vue-material-design-icons/SwordCross.vue'
import MagicStaff from 'vue-material-design-icons/MagicStaff.vue'
import BookOpenVariantOutline from 'vue-material-design-icons/BookOpenVariantOutline.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

export default {
	name: 'MainMenu',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationSettings,
		ViewDashboard,
		BriefcaseAccountOutline,
		AccountGroupOutline,
		Sword,
		CalendarMonthOutline,
		EmoticonSickOutline,
		ShieldSwordOutline,
		SwordCross,
		MagicStaff,
		BookOpenVariantOutline,
		Cog,
	},
	methods: {
		openLink(url, target) {
			window.open(url, target)
		},
	},
}
</script>
