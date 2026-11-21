<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  A game master rejects a submitted character with a reason (REQ-CTA-001).
  Opened by the CharacterDetail header action "Reject", which only game
  masters see. The reason is required; the owner is notified and can change
  the character and submit it again.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Reject character')"
		data-testid="reject-dialog"
		@closing="$emit('close')">
		<NcNoteCard
			v-if="done"
			type="success"
			data-testid="reject-done">
			{{ t('larpinq', 'The character is rejected. Its owner is notified with your reason.') }}
		</NcNoteCard>
		<template v-else>
			<p>
				{{ t('larpinq', 'Tell the owner what to change. They can change the character and submit it again.') }}
			</p>
			<NcTextArea
				v-model="reason"
				data-testid="reject-reason"
				:label="t('larpinq', 'Rejection reason')"
				:required="true" />
			<NcNoteCard
				v-if="refusal"
				type="error"
				data-testid="reject-refused">
				{{ refusal }}
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton data-testid="reject-cancel" @click="$emit('close')">
				{{ done ? t('larpinq', 'Close') : t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				v-if="!done"
				data-testid="reject-confirm"
				variant="error"
				:disabled="!rejectable"
				@click="reject">
				{{ t('larpinq', 'Reject') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { rejectCharacter } from '../services/characterApproval.js'
import { firstId } from '../services/characterBuilds.js'

export default {
	name: 'RejectCharacterDialog',

	components: { NcButton, NcDialog, NcNoteCard, NcTextArea },

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
			reason: '',
			busy: false,
			refusal: '',
			done: false,
		}
	},

	computed: {
		/**
		 * The character this dialog rejects.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
		 */
		id() {
			return firstId(this.characterId, this.$route?.params?.id)
		},

		/**
		 * Whether Reject may be pressed: a reason that is more than spaces, and nothing posting.
		 *
		 * @return {boolean} True when it may.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
		 */
		rejectable() {
			return this.id !== '' && this.reason.trim() !== '' && !this.busy
		},
	},

	methods: {
		t,

		/**
		 * Reject the character with the reason.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
		 */
		async reject() {
			if (!this.rejectable) {
				return
			}
			this.busy = true
			this.refusal = ''
			const outcome = await rejectCharacter(this.id, this.reason)
			this.busy = false
			if (!outcome.ok) {
				this.refusal = t('larpinq', 'The character was not rejected: {reason}', {
					reason: outcome.message || t('larpinq', 'unknown error'),
				})
				return
			}
			this.done = true
		},
	},
}
</script>
