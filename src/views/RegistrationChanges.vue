<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Cancel or hand over tab on the registration page
 (registration-cancel-transfer-refund): cancel this registration before the
 cancel-by date (a game master at any time), choose a refund or credit when
 the event lets the player choose, add a participant to the booking, offer the
 registration to another player, and accept or withdraw an offer. larpinq
 answers what the signed-in user may do and refuses anything else.

 @spec openspec/specs/event-registration/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by tests/vitest/registrationChanges.spec.js and tests/e2e/workflows/registration-cancel-transfer.workflow.spec.ts.
-->
<template>
	<div class="registration-changes" data-testid="registration-changes">
		<p v-if="loading">
			{{ t('larpinq', 'Loading') }}
		</p>
		<p v-else-if="!allowed.loaded" role="alert">
			{{ t('larpinq', 'Could not load what you can change.') }}
		</p>
		<template v-else>
			<p v-if="nothingToDo" data-testid="registration-changes-none">
				{{
					t(
						'larpinq',
						'There is nothing you can change on this registration now.',
					)
				}}
			</p>

			<section
				v-if="allowed.canAcceptTransfer"
				class="registration-changes__part">
				<h3>{{ t('larpinq', 'A place is offered to you') }}</h3>
				<p>
					{{
						t(
							'larpinq',
							'Accept it, then pick your character on this page.',
						)
					}}
				</p>
				<NcButton
					variant="primary"
					data-testid="transfer-accept"
					:disabled="busy"
					@click="
						run(
							() => acceptTransfer(registrationId),
							t(
								'larpinq',
								'The registration is yours. Pick your character.',
							),
						)
					">
					{{ t('larpinq', 'Accept the place') }}
				</NcButton>
			</section>

			<section v-if="allowed.canCancel" class="registration-changes__part">
				<h3>{{ t('larpinq', 'Cancel') }}</h3>
				<p v-if="allowed.cancelBy">
					{{
						t('larpinq', 'You can cancel until {date}.', {
							date: formatDate(allowed.cancelBy),
						})
					}}
				</p>
				<fieldset
					v-if="allowed.paid && allowed.choosesMoneyBack"
					class="registration-changes__choice">
					<legend>{{ t('larpinq', 'What do you want back?') }}</legend>
					<NcCheckboxRadioSwitch
						v-model="settlement"
						type="radio"
						name="settlement"
						value="refund"
						data-testid="settlement-refund">
						{{ t('larpinq', 'A refund') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						v-model="settlement"
						type="radio"
						name="settlement"
						value="credit"
						data-testid="settlement-credit">
						{{ t('larpinq', 'Credit for a later event') }}
					</NcCheckboxRadioSwitch>
				</fieldset>
				<NcButton
					v-if="!confirming"
					data-testid="registration-cancel"
					:disabled="busy"
					@click="confirming = true">
					{{ t('larpinq', 'Cancel this registration') }}
				</NcButton>
				<div v-else class="registration-changes__confirm">
					<p>
						{{
							t(
								'larpinq',
								'The place goes to the waiting list. This cannot be undone.',
							)
						}}
					</p>
					<NcButton
						variant="error"
						data-testid="registration-cancel-confirm"
						:disabled="busy"
						@click="
							run(
								() => cancelRegistration(registrationId, settlement),
								t('larpinq', 'The registration is cancelled.'),
							)
						">
						{{ t('larpinq', 'Yes, cancel it') }}
					</NcButton>
					<NcButton :disabled="busy" @click="confirming = false">
						{{ t('larpinq', 'Keep it') }}
					</NcButton>
				</div>
			</section>

			<section
				v-if="allowed.canOfferTransfer"
				class="registration-changes__part">
				<h3>{{ t('larpinq', 'Hand over to another player') }}</h3>
				<p>
					{{
						t(
							'larpinq',
							'Your place, ticket and payment go to them when they accept. The offer lapses after 7 days.',
						)
					}}
				</p>
				<NcTextField
					v-model="account"
					:label="t('larpinq', 'Their account name')"
					data-testid="transfer-account" />
				<NcButton
					data-testid="transfer-offer"
					:disabled="busy || account.trim() === ''"
					@click="
						run(
							() => offerTransfer(registrationId, account),
							t('larpinq', 'The offer is sent.'),
						)
					">
					{{ t('larpinq', 'Offer my place') }}
				</NcButton>
			</section>

			<section
				v-if="allowed.canWithdrawTransfer"
				class="registration-changes__part">
				<p>
					{{
						t(
							'larpinq',
							'This registration is offered to another player.',
						)
					}}
				</p>
				<NcButton
					data-testid="transfer-withdraw"
					:disabled="busy"
					@click="
						run(
							() => withdrawTransfer(registrationId),
							t('larpinq', 'The offer is withdrawn.'),
						)
					">
					{{ t('larpinq', 'Withdraw the offer') }}
				</NcButton>
			</section>

			<section
				v-if="allowed.canAddParticipant"
				class="registration-changes__part">
				<h3>{{ t('larpinq', 'Add someone to your booking') }}</h3>
				<p>
					{{
						t(
							'larpinq',
							'Each participant gets a registration of their own, so each can cancel alone.',
						)
					}}
				</p>
				<NcTextField
					v-model="participant"
					:label="t('larpinq', 'Their name')"
					data-testid="participant-name" />
				<NcButton
					data-testid="participant-add"
					:disabled="busy || participant.trim() === ''"
					@click="
						run(
							() => addParticipant(registrationId, participant),
							t('larpinq', 'The participant is added.'),
						)
					">
					{{ t('larpinq', 'Add to my booking') }}
				</NcButton>
			</section>

			<p
				v-if="message"
				role="alert"
				data-testid="registration-changes-refused">
				{{ message }}
			</p>
			<p v-if="done" role="status" data-testid="registration-changes-done">
				{{ done }}
			</p>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	acceptTransfer,
	addParticipant,
	cancelRegistration,
	fetchChanges,
	offerTransfer,
	withdrawTransfer,
} from '../services/registrationChanges.js'

export default {
	name: 'RegistrationChanges',

	components: { NcButton, NcCheckboxRadioSwitch, NcTextField },

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The registration UUID. Falls back to the injected object context or the route.
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			loading: true,
			busy: false,
			confirming: false,
			message: '',
			done: '',
			settlement: 'refund',
			account: '',
			participant: '',
			allowed: { loaded: false },
		}
	},

	computed: {
		/**
		 * The registration id from prop, injected context or route.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		registrationId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| String(this.$route?.params?.id ?? '')
			)
		},

		/**
		 * Whether the signed-in user can change nothing now.
		 *
		 * @return {boolean} True when every action is closed.
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		nothingToDo() {
			const a = this.allowed
			return !(
				a.canCancel
				|| a.canOfferTransfer
				|| a.canWithdrawTransfer
				|| a.canAcceptTransfer
				|| a.canAddParticipant
			)
		},
	},

	/**
	 * Load what the user may change.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		t,
		acceptTransfer,
		addParticipant,
		cancelRegistration,
		offerTransfer,
		withdrawTransfer,

		/**
		 * Ask larpinq what the user may change now.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		async load() {
			const result = await fetchChanges(this.registrationId)
			this.allowed = result.ok
				? { ...result.data, loaded: true }
				: { loaded: false }
			this.loading = false
		},

		/**
		 * Run one change, show larpinq's answer, and read what may change next.
		 *
		 * @param {Function} change The change, returning `{ok, message}`.
		 * @param {string} success What to say when it worked.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		async run(change, success) {
			this.busy = true
			this.done = ''
			const result = await change()
			this.busy = false
			this.confirming = false
			this.message = result.ok
				? ''
				: result.message || t('larpinq', 'That did not work.')
			if (result.ok) {
				this.done = success
				this.account = ''
				this.participant = ''
				await this.load()
			}
		},

		/**
		 * A stored moment as a date in the user's locale.
		 *
		 * @param {string} value The ISO moment.
		 * @return {string} The date.
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		formatDate(value) {
			const date = new Date(value)
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},
	},
}
</script>

<style scoped>
.registration-changes__part {
	margin-bottom: calc(var(--default-grid-baseline, 4px) * 6);
}

.registration-changes__choice {
	border: none;
	padding: 0;
}

.registration-changes__confirm {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}
</style>
