<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Choices tab on the event page (registration-ticket-types-and-options): how
 many accepted registrations chose each ticket type and each option, so the
 kitchen knows how many vegan meals to cook. Counts only, no money total.
 Game masters only.

 @spec openspec/specs/event-registration/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by RegistrationChoicesControllerTest and tests/e2e/workflows/registration-tickets.workflow.spec.ts.
-->
<template>
	<div class="event-choice-counts" data-testid="event-choice-counts">
		<p v-if="loading">
			{{ t('larpinq', 'Loading choices') }}
		</p>
		<p v-else-if="counts.state === 'refused'">
			{{ t('larpinq', 'Only game masters see what participants chose.') }}
		</p>
		<p v-else-if="counts.state !== 'ok'" role="alert">
			{{ t('larpinq', 'Could not load the choices.') }}
		</p>
		<template v-else>
			<p>
				{{
					t(
						'larpinq',
						'Accepted registrations per ticket type and option.',
					)
				}}
			</p>
			<table class="event-choice-counts__table">
				<thead>
					<tr>
						<th scope="col">
							{{ t('larpinq', 'Choice') }}
						</th>
						<th scope="col">
							{{ t('larpinq', 'Registrations') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="row in rows"
						:key="row.id"
						data-testid="event-choice-count">
						<td>{{ row.name }}</td>
						<td>{{ row.count }}</td>
					</tr>
				</tbody>
			</table>
			<p v-if="rows.length === 0">
				{{ t('larpinq', 'This event has no ticket types or options yet.') }}
			</p>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { fetchChoiceCounts } from '../services/registrationChoices.js'

export default {
	name: 'EventChoiceCounts',

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The event UUID. Falls back to the injected object context or the route.
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
			counts: { state: 'ok', ticketTypes: [], options: [] },
		}
	},

	computed: {
		/**
		 * The event id from prop, injected context or route.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		eventId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| String(this.$route?.params?.id ?? '')
			)
		},

		/**
		 * Ticket types first, then options.
		 *
		 * @return {Array<object>} The rows.
		 *
		 * @spec openspec/specs/event-registration/spec.md
		 */
		rows() {
			return [
				...(this.counts.ticketTypes ?? []),
				...(this.counts.options ?? []),
			]
		},
	},

	/**
	 * Load the counts.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	async mounted() {
		this.counts = await fetchChoiceCounts(this.eventId)
		this.loading = false
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.event-choice-counts__table {
	width: 100%;
	border-collapse: collapse;
}

.event-choice-counts__table th,
.event-choice-counts__table td {
	text-align: start;
	padding: calc(var(--default-grid-baseline, 4px) * 2) 0;
	border-bottom: 1px solid var(--color-border);
}
</style>
