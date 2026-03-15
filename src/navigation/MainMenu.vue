<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Dashboard')"
				:to="{ name: 'Dashboard' }">
				<template #icon>
					<Finance :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Karakters')"
				:to="{ name: 'Characters' }">
				<template #icon>
					<BriefcaseAccountOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:name="t('larpingapp', 'Spelers')"
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
				:name="t('larpingapp', 'Zoeken')"
				:to="{ name: 'Search' }">
				<template #icon>
					<Magnify :size="20" />
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
					:name="t('larpingapp', 'Templates')"
					:to="{ name: 'Templates' }">
					<template #icon>
						<FileDocumentOutline :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationSettings } from '@nextcloud/vue'

// Icons
import Finance from 'vue-material-design-icons/Finance.vue'
import Sword from 'vue-material-design-icons/Sword.vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import CalendarMonthOutline from 'vue-material-design-icons/CalendarMonthOutline.vue'
import BriefcaseAccountOutline from 'vue-material-design-icons/BriefcaseAccountOutline.vue'
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
		NcAppNavigationItem,
		NcAppNavigationSettings,
		// Icons
		Finance,
		Sword,
		AccountGroupOutline,
		CalendarMonthOutline,
		BriefcaseAccountOutline,
		ShieldSwordOutline,
		SwordCross,
		MagicStaff,
		Magnify,
		FileDocumentOutline,
		EmoticonSickOutline,
	},
}
</script>
