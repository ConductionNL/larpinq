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
		<ViewAuditTrail />
	</div>
</template>

<script>
import EditAbility from './ability/EditAbility.vue'
import EditCharacter from './character/EditCharacter.vue'
import EditCondition from './condition/EditCondition.vue'
import DeleteCondition from './condition/DeleteCondition.vue'
import EditEffect from './effect/EditEffect.vue'
import EditEvent from './event/EditEvent.vue'
import EditItem from './item/EditItem.vue'
import EditPlayer from './player/EditPlayer.vue'
import EditSkill from './skill/EditSkill.vue'
import EditTemplate from './template/EditTemplate.vue'
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
		EditCharacter,
		EditCondition,
		DeleteCondition,
		EditEffect,
		EditEvent,
		EditItem,
		EditPlayer,
		EditSkill,
		EditTemplate,
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

