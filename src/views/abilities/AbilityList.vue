<NcEmptyContent
	v-else
	name="Geen Vaardigheden"
	icon="icon-shield">
	<template #icon>
		<ShieldSwordOutline :size="20" />
	</template>
	<template #action>
		<NcButton @click="openModal">
			<template #icon>
				<Plus :size="20" />
			</template>
			Vaardigheid toevoegen
		</NcButton>
	</template>
</NcEmptyContent>

// Icons
import ShieldSwordOutline from 'vue-material-design-icons/ShieldSwordOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

components: {
	NcEmptyContent,
	NcButton,
	// Icons
	ShieldSwordOutline,
	Plus,
}, 