<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Tickets tab on the registration page (registration-ticket-types-and-options):
 the player picks a ticket type and options and can enter a code that unlocks
 a hidden ticket type. The list comes from larpinq's offer, because players
 cannot read hidden ticket types or codes; larpinq writes the price lines.

 @spec openspec/specs/event-registration/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by tests/vitest/registrationChoices.spec.js and tests/e2e/workflows/registration-tickets.workflow.spec.ts.
-->
<template>
	<div class="registration-choices" data-testid="registration-choices">
		<p v-if="loading">
			{{ t('larpinq', 'Loading tickets') }}
		</p>
		<p v-else-if="offer.state === 'refused'">
			{{
				t(
					'larpinq',
					'Only game masters and the player can choose tickets for this registration.',
				)
			}}
		</p>
		<p v-else-if="offer.state !== 'ok'" role="alert">
			{{ t('larpinq', 'Could not load the tickets.') }}
		</p>
		<form v-else @submit.prevent="save">
			<fieldset class="registration-choices__group">
				<legend>{{ t('larpinq', 'Ticket type') }}</legend>
				<p v-if="offer.ticketTypes.length === 0">
					{{ t('larpinq', 'This event offers no tickets right now.') }}
				</p>
				<NcCheckboxRadioSwitch
					v-for="ticket in offer.ticketTypes"
					:key="ticket.id"
					v-model="ticketType"
					type="radio"
					name="ticketType"
					:value="ticket.id"
					:disabled="ticket.full && ticket.id !== offer.chosen.ticketType"
					:data-testid="`ticket-${ticket.id}`">
					{{ ticket.name }} ·
					{{ formatPrice(ticket.amount, ticket.currency) }}
					<template v-if="ticket.full">
						({{ t('larpinq', 'full, you join the waiting list') }})
					</template>
				</NcCheckboxRadioSwitch>
			</fieldset>

			<fieldset
				v-if="offer.options.length > 0"
				class="registration-choices__group">
				<legend>{{ t('larpinq', 'Options') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="option in offer.options"
					:key="option.id"
					v-model="options"
					type="checkbox"
					:value="option.id"
					:disabled="option.full && !options.includes(option.id)"
					:data-testid="`option-${option.id}`">
					{{ option.name }} ·
					{{ formatPrice(option.amount, option.currency) }}
					<template v-if="option.full">
						({{ t('larpinq', 'full') }})
					</template>
				</NcCheckboxRadioSwitch>
			</fieldset>

			<div class="registration-choices__code">
				<NcTextField
					v-model="code"
					:label="t('larpinq', 'Code')"
					data-testid="registration-code" />
				<NcButton data-testid="registration-code-apply" @click="load">
					{{ t('larpinq', 'Use code') }}
				</NcButton>
			</div>
			<p v-if="offer.code === 'valid'" role="status">
				{{ t('larpinq', 'The code works. Its tickets are listed above.') }}
			</p>
			<p v-else-if="offer.code === 'invalid'" role="alert">
				{{ t('larpinq', 'This code does not work for this event.') }}
			</p>

			<p class="registration-choices__note">
				{{
					t(
						'larpinq',
						'Prices are the listed prices when you choose. The amount to pay comes with the payment request.',
					)
				}}
			</p>
			<NcButton
				type="submit"
				variant="primary"
				data-testid="registration-choices-save"
				:disabled="saving">
				{{ saving ? t('larpinq', 'Saving') : t('larpinq', 'Save choices') }}
			</NcButton>
			<p
				v-if="message"
				role="alert"
				data-testid="registration-choices-refused">
				{{ message }}
			</p>
			<p v-if="saved" role="status" data-testid="registration-choices-saved">
				{{ t('larpinq', 'Your choices are saved.') }}
			</p>
		</form>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	fetchOffer,
	formatPrice,
	saveChoices,
} from '../services/registrationChoices.js'

export default {
	name: 'RegistrationChoices',

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
			saving: false,
			saved: false,
			message: '',
			offer: {
				state: 'ok',
				ticketTypes: [],
				options: [],
				code: 'none',
				chosen: { ticketType: '', options: [] },
			},

			ticketType: '',
			options: [],
			code: '',
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
	},

	/**
	 * Load the offer.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	async mounted() {
		await this.load()
		this.ticketType = this.offer.chosen?.ticketType ?? ''
		this.options = [...(this.offer.chosen?.options ?? [])]
	},

	methods: {
		t,
		formatPrice,

		/**
		 * Read what the player may choose, with the typed code.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		async load() {
			this.offer = await fetchOffer(this.registrationId, this.code)
			this.loading = false
		},

		/**
		 * Save the ticket type, options and code; larpinq checks and prices them.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		async save() {
			this.saving = true
			this.saved = false
			const result = await saveChoices(this.registrationId, {
				ticketType: this.ticketType,
				options: this.options,
				code: this.code,
			})
			this.saving = false
			this.message = result.ok
				? ''
				: result.message || t('larpinq', 'Could not save the choices.')
			this.saved = result.ok
		},
	},
}
</script>

<style scoped>
.registration-choices__group {
	margin-bottom: calc(var(--default-grid-baseline, 4px) * 4);
	border: none;
	padding: 0;
}

.registration-choices__code {
	display: flex;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.registration-choices__note {
	color: var(--color-text-maxcontrast);
}
</style>
