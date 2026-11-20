<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Email the participants of an event (communication-mail-participants). A game
  master writes a subject and a body, picks who gets it (registration status,
  checked in, open payment, ticket role), sees how many people that is and the
  first ten names, and sends after a confirmation. Larpinq mails each recipient
  on their own and keeps the message with the event. Opened by the EventDetail
  header action "Email participants", shown to game masters only.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Email participants')"
		size="large"
		data-testid="email-participants-dialog"
		@closing="$emit('close')">
		<NcNoteCard v-if="result" type="success" data-testid="email-participants-sent">
			{{ t('larpinq', 'The message is being sent.') }}
		</NcNoteCard>
		<template v-else>
			<NcTextField
				v-model="subject"
				data-testid="email-participants-subject"
				:label="t('larpinq', 'Subject')"
				:disabled="busy" />
			<NcTextArea
				v-model="body"
				data-testid="email-participants-body"
				:label="t('larpinq', 'Message')"
				:helperText="t('larpinq', '{event} and {date} are replaced by the event\'s name and date.')"
				resize="vertical"
				:disabled="busy" />

			<fieldset class="email-participants__audience">
				<legend>{{ t('larpinq', 'Registration status') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="status in statusOptions"
					:key="status.value"
					v-model="statuses"
					:value="status.value"
					type="checkbox"
					:disabled="busy"
					@update:modelValue="onAudienceChange">
					{{ status.label }}
				</NcCheckboxRadioSwitch>
			</fieldset>
			<fieldset class="email-participants__audience">
				<legend>{{ t('larpinq', 'Ticket role') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="role in roleOptions"
					:key="role.value"
					v-model="ticketRoles"
					:value="role.value"
					type="checkbox"
					:disabled="busy"
					@update:modelValue="onAudienceChange">
					{{ role.label }}
				</NcCheckboxRadioSwitch>
			</fieldset>
			<NcCheckboxRadioSwitch
				v-model="checkedIn"
				:disabled="busy"
				@update:modelValue="onAudienceChange">
				{{ t('larpinq', 'Checked in only') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-model="paymentOpen"
				:disabled="busy"
				@update:modelValue="onAudienceChange">
				{{ t('larpinq', 'Payment open only') }}
			</NcCheckboxRadioSwitch>

			<div v-if="preview" class="email-participants__preview" data-testid="email-participants-preview">
				<p>
					<strong>{{ t('larpinq', 'Recipients: {count}', { count: preview.count }) }}</strong>
					<span v-if="preview.names.length > 0">: {{ preview.names.join(', ') }}<span v-if="preview.count > preview.names.length">, …</span></span>
				</p>
				<p v-if="preview.noAddressCount > 0" data-testid="email-participants-no-address">
					{{ t('larpinq', 'Without an email address: {count}', { count: preview.noAddressCount }) }} —
					{{ preview.noAddressNames.join(', ') }}
				</p>
			</div>
			<NcNoteCard v-if="confirming" type="warning" data-testid="email-participants-confirm">
				{{ t('larpinq', 'Send this message? Recipients: {count}', { count: preview ? preview.count : 0 }) }}
			</NcNoteCard>
			<NcNoteCard v-if="error" type="error" data-testid="email-participants-error">
				{{ error }}
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton v-if="!result" :disabled="busy" @click="loadPreview">
				{{ t('larpinq', 'Preview recipients') }}
			</NcButton>
			<NcButton
				v-if="!result && !confirming"
				variant="primary"
				data-testid="email-participants-send"
				:disabled="!canSend"
				@click="confirming = true">
				{{ t('larpinq', 'Send') }}
			</NcButton>
			<NcButton
				v-if="!result && confirming"
				variant="primary"
				data-testid="email-participants-confirm-send"
				:disabled="busy"
				@click="send">
				{{ t('larpinq', 'Send') }}
			</NcButton>
			<NcButton v-if="result" @click="$emit('close')">
				{{ t('larpinq', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { previewAudience, sendMessage } from '../services/eventMessages.js'

export default {
	name: 'EmailParticipantsDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcNoteCard,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The event UUID; the route's id when the action passes none. */
		eventId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			subject: '',
			body: '',
			statuses: ['accepted'],
			ticketRoles: [],
			checkedIn: false,
			paymentOpen: false,
			preview: null,
			confirming: false,
			busy: false,
			error: '',
			result: null,
		}
	},

	computed: {
		/**
		 * The event this dialog writes to.
		 *
		 * @return {string} The event UUID.
		 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
		 */
		id() {
			const fromProp = this.eventId.startsWith('@') ? '' : this.eventId
			return fromProp || String(this.$route?.params?.id ?? '')
		},

		/**
		 * The audience as the endpoints take it.
		 *
		 * @return {object} The audience.
		 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
		 */
		audience() {
			return {
				statuses: this.statuses,
				checkedIn: this.checkedIn,
				paymentOpen: this.paymentOpen,
				ticketRoles: this.ticketRoles,
			}
		},

		/**
		 * Sending needs a subject, a body and a preview with at least one recipient.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
		 */
		canSend() {
			return !this.busy
				&& this.subject.trim() !== ''
				&& this.body.trim() !== ''
				&& this.preview !== null
				&& this.preview.count > 0
		},

		/** @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001 */
		statusOptions() {
			return [
				{ value: 'accepted', label: t('larpinq', 'Accepted') },
				{ value: 'waitlisted', label: t('larpinq', 'Waitlisted') },
				{ value: 'pending', label: t('larpinq', 'Pending') },
			]
		},

		/** @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001 */
		roleOptions() {
			return [
				{ value: 'player', label: t('larpinq', 'Player') },
				{ value: 'crew', label: t('larpinq', 'Crew') },
				{ value: 'npc', label: t('larpinq', 'NPC') },
			]
		},
	},

	methods: {
		t,

		/**
		 * A changed audience needs a new preview before it can be sent.
		 *
		 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
		 */
		onAudienceChange() {
			this.preview = null
			this.confirming = false
		},

		/**
		 * Ask how many people the message reaches.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
		 */
		async loadPreview() {
			this.busy = true
			this.error = ''
			this.confirming = false
			const preview = await previewAudience(this.id, this.audience)
			this.busy = false
			if (!preview.ok) {
				this.preview = null
				this.error = t('larpinq', 'The recipients could not be loaded: {error}', { error: preview.error })
				return
			}
			this.preview = preview
		},

		/**
		 * Save and send the message after the confirmation.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
		 */
		async send() {
			this.busy = true
			this.error = ''
			const outcome = await sendMessage(this.id, {
				subject: this.subject.trim(),
				body: this.body,
				audience: this.audience,
			})
			this.busy = false
			this.confirming = false
			if (!outcome.ok) {
				this.error = t('larpinq', 'The message was not sent: {error}', { error: outcome.error })
				return
			}
			this.result = outcome.message || { recipientCount: this.preview?.count ?? 0 }
		},
	},
}
</script>

<style scoped>
.email-participants__audience {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 16px;
	margin: 12px 0 0;
	border: 0;
	padding: 0;
}

.email-participants__audience legend {
	font-weight: bold;
	margin-bottom: 4px;
}

.email-participants__preview {
	margin-top: 12px;
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-dark);
	color: var(--color-main-text);
}
</style>
