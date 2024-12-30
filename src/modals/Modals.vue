<template>
	<!-- Placeholder -->
	<div>
		<EditAbility />
		<DeleteAbility />
		<EditCharacter />
		<DeleteCharacter />
		<EditCondition />
		<DeleteCondition />
		<EditEffect />
		<AddEffect />
		<DeleteEffect />
		<EditEvent />
		<DeleteEvent />
		<EditItem />
		<DeleteItem />
		<EditPlayer />
		<DeletePlayer />
		<EditSkill />
		<DeleteSkill />
		<EditTemplate />
		<DeleteTemplate />
		<AddSkillToCharacter />
		<AddItemToCharacter />
		<AddConditionToCharacter />
		<AddEventToCharacter />
		<DeleteSkillFromCharacter />
		<DeleteItemFromCharacter />
		<DeleteConditionFromCharacter />
		<DeleteEventFromCharacter />
		<RenderPdfFromCharacter />
	</div>
</template>

<script>
import EditAbility from './ability/EditAbility.vue'
import DeleteAbility from './ability/DeleteAbility.vue'
import EditCharacter from './character/EditCharacter.vue'
import DeleteCharacter from './character/DeleteCharacter.vue'
import EditCondition from './condition/EditCondition.vue'
import DeleteCondition from './condition/DeleteCondition.vue'
import EditEffect from './effect/EditEffect.vue'
import AddEffect from './effect/AddEffect.vue'
import DeleteEffect from './effect/DeleteEffect.vue'
import EditEvent from './event/EditEvent.vue'
import DeleteEvent from './event/DeleteEvent.vue'
import EditItem from './item/EditItem.vue'
import DeleteItem from './item/DeleteItem.vue'
import EditPlayer from './player/EditPlayer.vue'
import DeletePlayer from './player/DeletePlayer.vue'
import EditSkill from './skill/EditSkill.vue'
import DeleteSkill from './skill/DeleteSkill.vue'
import EditTemplate from './template/EditTemplate.vue'
import DeleteTemplate from './template/DeleteTemplate.vue'
import AddSkillToCharacter from './character/AddSkillToCharacter.vue'
import AddItemToCharacter from './character/AddItemToCharacter.vue'
import AddConditionToCharacter from './character/AddConditionToCharacter.vue'
import AddEventToCharacter from './character/AddEventToCharacter.vue'
import DeleteSkillFromCharacter from './character/DeleteSkillFromCharacter.vue'
import DeleteItemFromCharacter from './character/DeleteItemFromCharacter.vue'
import DeleteConditionFromCharacter from './character/DeleteConditionFromCharacter.vue'
import DeleteEventFromCharacter from './character/DeleteEventFromCharacter.vue'
import RenderPdfFromCharacter from './character/RenderPdfFromCharacter.vue'
import ViewAuditTrail from './audittrail/ViewAuditTrail.vue'

export default {
	name: 'Modals',
	components: {
		EditAbility,
		DeleteAbility,
		EditCharacter,
		DeleteCharacter,
		EditCondition,
		DeleteCondition,
		EditEffect,
		AddEffect,
		DeleteEffect,
		EditEvent,
		DeleteEvent,
		EditItem,
		DeleteItem,
		EditPlayer,
		DeletePlayer,
		EditSkill,
		DeleteSkill,
		EditTemplate,
		DeleteTemplate,
		AddSkillToCharacter,
		AddItemToCharacter,
		AddConditionToCharacter,
		AddEventToCharacter,
		DeleteSkillFromCharacter,
		DeleteItemFromCharacter,
		DeleteConditionFromCharacter,
		DeleteEventFromCharacter,
		RenderPdfFromCharacter,
		ViewAuditTrail,
	},
}
</script>

