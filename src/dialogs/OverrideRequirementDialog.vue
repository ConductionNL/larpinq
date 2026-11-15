<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  A game master grants a skill the character does not qualify for, with a
  reason (REQ-SRE-001). Opened by the CharacterDetail header action
  "Override a requirement" (open-modal), which only game masters see. The
  skill and its requirementOverrides[] entry go in one write, so the server's
  requirement check and the audit trail apply.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Override a requirement')"
		data-testid="override-dialog"
		@closing="$emit('close')">
		<p v-if="status === 'loading'">
			{{ t('larpinq', 'Loading the skills') }}
		</p>
		<NcNoteCard
			v-else-if="status === 'failed'"
			type="error"
			data-testid="override-load-failed">
			{{ t('larpinq', 'Could not read the character.') }}
		</NcNoteCard>
		<NcNoteCard
			v-else-if="status === 'granted'"
			type="success"
			data-testid="override-done">
			{{ t('larpinq', '{character} now has {skill}. The override and its reason are in the audit trail.', { character: characterName, skill: grantedName }) }}
		</NcNoteCard>
		<template v-else>
			<p>
				{{ t('larpinq', 'Grant {character} a skill whose requirements they do not meet. The reason is kept with the override.', { character: characterName }) }}
			</p>
			<p v-if="skills.length === 0" data-testid="override-no-skills">
				{{ t('larpinq', 'The character already has every skill of this world.') }}
			</p>
			<template v-else>
				<NcSelect
					v-model="selected"
					data-testid="override-skill"
					:inputLabel="t('larpinq', 'Skill')"
					:options="skills"
					label="name"
					:clearable="false" />
				<NcTextArea
					v-model="reason"
					data-testid="override-reason"
					:label="t('larpinq', 'Reason')"
					:helperText="t('larpinq', 'Required. For example: plot reward from the summer finale.')"
					:required="true" />
			</template>
			<NcNoteCard
				v-if="refusal"
				type="error"
				data-testid="override-refused">
				{{ refusal }}
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton data-testid="override-cancel" @click="$emit('close')">
				{{ status === 'granted' ? t('larpinq', 'Close') : t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				data-testid="override-save"
				variant="primary"
				:disabled="!savable"
				@click="save">
				{{ t('larpinq', 'Grant with override') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { firstId } from '../services/characterBuilds.js'
import { fetchOverrideOptions, grantWithOverride } from '../services/requirementOverride.js'

export default {
	name: 'OverrideRequirementDialog',

	components: { NcButton, NcDialog, NcNoteCard, NcSelect, NcTextArea },

	props: {
		/**
		 * The character. Falls back to the route: an `open-modal` header
		 * action forwards its props verbatim.
		 */
		characterId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			status: 'loading',
			character: null,
			skills: [],
			selected: null,
			reason: '',
			busy: false,
			refusal: '',
			grantedName: '',
		}
	},

	computed: {
		/**
		 * The character this dialog grants a skill to.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
		 */
		id() {
			return firstId(this.characterId, this.$route?.params?.id)
		},

		/**
		 * The character's name.
		 *
		 * @return {string} The name.
		 */
		characterName() {
			return String(this.character?.name ?? '')
		},

		/**
		 * Whether the override may be saved: a skill and a reason that is more than spaces.
		 *
		 * @return {boolean} True when it may.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
		 */
		savable() {
			return this.status === 'ready' && this.selected !== null && this.reason.trim() !== '' && !this.busy
		},
	},

	/**
	 * Read the character and the skills it does not have.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
	 */
	async mounted() {
		const options = await fetchOverrideOptions(this.id)
		if (options === null) {
			this.status = 'failed'
			return
		}
		this.character = options.character
		this.skills = options.skills
		this.status = 'ready'
	},

	methods: {
		t,

		/**
		 * Grant the chosen skill with the override, in one write.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
		 */
		async save() {
			if (!this.savable) {
				return
			}
			this.busy = true
			this.refusal = ''
			const outcome = await grantWithOverride({ id: this.id, ...this.character }, this.selected.id, this.reason)
			this.busy = false
			if (!outcome.ok) {
				this.refusal = t('larpinq', 'The override was not saved: {reason}', {
					reason: outcome.message || t('larpinq', 'unknown error'),
				})
				return
			}
			this.grantedName = this.selected.name
			this.status = 'granted'
		},
	},
}
</script>
