<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Casting tab on the event page (events-casting-and-crew-roles): how often each
 character open for casting was ranked at each rank, the proposed casting as
 an editable table with a confirm button, and the crew and NPC roles with
 filled and open places and an assign action. Game masters only; the
 proposal comes from larpinq, everything else from the OpenRegister objects API.

 @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by tests/vitest/eventCastingView.spec.js and the planned tests/e2e/event-casting.spec.ts.
-->
<template>
	<div class="event-casting" data-testid="event-casting">
		<p v-if="loading">
			{{ t('larpinq', 'Loading casting') }}
		</p>
		<p v-else-if="view.state === 'refused'">
			{{ t('larpinq', 'Only game masters see the casting proposal.') }}
		</p>
		<p v-else-if="view.state !== 'ok'" role="alert">
			{{ t('larpinq', 'Could not load the casting.') }}
		</p>
		<template v-else>
			<h3>{{ t('larpinq', 'Characters open for casting') }}</h3>
			<CnDataTable
				:columns="rankColumns"
				:rows="rankRows"
				:emptyText="
					t('larpinq', 'This event has no characters open for casting.')
				"
				data-testid="event-casting-ranks" />

			<h3>{{ t('larpinq', 'Proposal') }}</h3>
			<CnDataTable
				:columns="proposalColumns"
				:rows="proposalRows"
				data-testid="event-casting-proposal">
				<template #column-character="{ row }">
					<NcSelect
						:modelValue="cast[row.id] ?? notCast"
						:inputLabel="t('larpinq', 'Character')"
						:options="characterOptions"
						:clearable="false"
						:data-testid="`event-casting-pick-${row.id}`"
						@update:modelValue="pick(row.id, $event)" />
				</template>
			</CnDataTable>
			<p v-if="unmatchedNames !== ''" data-testid="event-casting-unmatched">
				{{
					t('larpinq', 'Players without a ranked character: {players}', {
						players: unmatchedNames,
					})
				}}
			</p>
			<NcButton
				variant="primary"
				:disabled="confirming || proposalRows.length === 0"
				data-testid="event-casting-confirm"
				@click="confirm">
				{{
					confirming
						? t('larpinq', 'Saving')
						: t('larpinq', 'Confirm casting')
				}}
			</NcButton>
			<p v-if="confirmed" role="status" data-testid="event-casting-confirmed">
				{{ t('larpinq', 'The casting is confirmed.') }}
			</p>
			<p
				v-if="refusedNames !== ''"
				role="alert"
				data-testid="event-casting-refused">
				{{ t('larpinq', 'Not confirmed: {pairs}', { pairs: refusedNames }) }}
			</p>

			<h3>{{ t('larpinq', 'Crew and NPC roles') }}</h3>
			<p v-if="crew.length === 0">
				{{ t('larpinq', 'This event has no crew or NPC roles yet.') }}
			</p>
			<section
				v-for="role in crew"
				:key="role.id"
				class="event-casting__role"
				:data-testid="`event-casting-role-${role.id}`">
				<h4>{{ role.name }} · {{ kindLabel(role.kind) }}</h4>
				<p data-testid="event-casting-places">
					{{
						t('larpinq', '{filled} of {places} places filled', {
							filled: role.filled,
							places: role.places,
						})
					}}
				</p>
				<ul v-if="role.assignments.length > 0">
					<li
						v-for="assignment in role.assignments"
						:key="assignment.registration">
						{{ nameOf(assignment.registration) }}
					</li>
				</ul>
				<div v-if="role.open > 0" class="event-casting__assign">
					<NcSelect
						v-model="crewChoice[role.id]"
						:inputLabel="t('larpinq', 'Volunteer')"
						:options="volunteerOptions(role)"
						:clearable="true" />
					<NcButton
						:disabled="!crewChoice[role.id] || assigning === role.id"
						:data-testid="`event-casting-assign-${role.id}`"
						@click="assign(role)">
						{{ t('larpinq', 'Assign') }}
					</NcButton>
				</div>
			</section>
			<p
				v-if="crewMessage"
				role="alert"
				data-testid="event-casting-crew-refused">
				{{ crewMessage }}
			</p>
		</template>
	</div>
</template>

<script>
import { CnDataTable } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { objectTab } from '../mixins/objectTab.js'
import {
	assignCrew,
	confirmCasting,
	crewPlaces,
	fetchEventCasting,
	idOf,
	MAX_RANK,
	registrationLabel,
	volunteersFor,
} from '../services/eventCasting.js'

export default {
	name: 'EventCasting',

	components: { CnDataTable, NcButton, NcSelect },

	mixins: [objectTab('eventId')],

	data() {
		return {
			loading: true,
			view: { state: 'ok' },
			cast: {},
			confirming: false,
			confirmed: false,
			refused: [],
			crewChoice: {},
			assigning: '',
			crewMessage: '',
		}
	},

	computed: {
		/**
		 * The option that leaves a player uncast.
		 *
		 * @return {object} The option.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		notCast() {
			return { id: '', label: t('larpinq', 'Not cast') }
		},

		/**
		 * The open characters as options, after "Not cast".
		 *
		 * @return {Array<object>} The options.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
		 */
		characterOptions() {
			const characters = this.view.proposal?.characters ?? []
			return [
				this.notCast,
				...characters.map((character) => ({
					id: character.id,
					label: character.name,
				})),
			]
		},

		/**
		 * The rank count columns: the character, then ranks 1 to 5.
		 *
		 * @return {Array<object>} The columns.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		rankColumns() {
			const ranks = Array.from({ length: MAX_RANK }, (_, i) => ({
				key: `rank${i + 1}`,
				label: t('larpinq', 'Rank {rank}', { rank: i + 1 }),
			}))
			return [{ key: 'name', label: t('larpinq', 'Character') }, ...ranks]
		},

		/**
		 * Per open character, how many players ranked it at each rank.
		 *
		 * @return {Array<object>} The rows.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
		 */
		rankRows() {
			return (this.view.proposal?.characters ?? []).map((character) => {
				const row = { id: character.id, name: character.name }
				for (let rank = 1; rank <= MAX_RANK; rank++) {
					row[`rank${rank}`] = Number(character.ranked?.[rank] ?? 0)
				}
				return row
			})
		},

		/**
		 * The proposal columns.
		 *
		 * @return {Array<object>} The columns.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		proposalColumns() {
			return [
				{ key: 'player', label: t('larpinq', 'Player') },
				{ key: 'character', label: t('larpinq', 'Character') },
				{ key: 'rank', label: t('larpinq', 'Rank') },
			]
		},

		/**
		 * One row per player who ranked an open character: the proposed pairs
		 * first, then the players left without a ranked match.
		 *
		 * @return {Array<object>} `{id, player, rank}` per row.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
		 */
		proposalRows() {
			const pairs = this.view.proposal?.pairs ?? []
			const unmatched = this.view.proposal?.unmatched ?? []
			return [
				...pairs.map((pair) => ({
					id: pair.registration,
					player: this.nameOf(pair.registration),
					rank: pair.rank,
				})),
				...unmatched.map((id) => ({
					id,
					player: this.nameOf(id),
					rank: '',
				})),
			]
		},

		/**
		 * The names of the players the proposal left without a ranked character.
		 *
		 * @return {string} The names, comma separated.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		unmatchedNames() {
			return (this.view.proposal?.unmatched ?? [])
				.map((id) => this.nameOf(id))
				.join(', ')
		},

		/**
		 * The refused pairs of the last confirmation, by player.
		 *
		 * @return {string} The names, comma separated.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		refusedNames() {
			return this.refused
				.map((pair) => this.nameOf(pair.registration))
				.join(', ')
		},

		/**
		 * The crew and NPC roles with filled and open places.
		 *
		 * @return {Array<object>} The roles.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-volunteers-fill-crew-and-npc-roles-req-ecc-004
		 */
		crew() {
			return crewPlaces(this.view.roles ?? [], this.view.assignments ?? [])
		},
	},

	/**
	 * Load the casting.
	 *
	 * @return {Promise<void>}
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		t,

		/**
		 * Load the proposal, the registrations and the crew; start from the proposal.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
		 */
		async load() {
			this.loading = true
			this.view = await fetchEventCasting(this.eventId)
			const options = new Map(
				this.characterOptions.map((option) => [option.id, option]),
			)
			const cast = {}
			for (const pair of this.view.proposal?.pairs ?? []) {
				cast[pair.registration] = options.get(pair.character) ?? this.notCast
			}
			this.cast = cast
			this.loading = false
		},

		/**
		 * The label of a role's kind.
		 *
		 * @param {string} kind `crew` or `npc`.
		 * @return {string} The label.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		kindLabel(kind) {
			return kind === 'npc' ? t('larpinq', 'npc') : t('larpinq', 'crew')
		},

		/**
		 * The name of a registration of the event.
		 *
		 * @param {string} registrationId The registration uuid.
		 * @return {string} The name.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		nameOf(registrationId) {
			const registration = (this.view.registrations ?? []).find(
				(row) => idOf(row) === String(registrationId),
			)
			return registration
				? registrationLabel(registration)
				: String(registrationId)
		},

		/**
		 * Change a player's character; a character goes to one player only.
		 *
		 * @param {string} registrationId The registration uuid.
		 * @param {object|null} option The chosen option.
		 * @return {void}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
		 */
		pick(registrationId, option) {
			const chosen = option ?? this.notCast
			const cast = { ...this.cast }
			if (chosen.id !== '') {
				for (const [other, value] of Object.entries(cast)) {
					if (other !== registrationId && value?.id === chosen.id) {
						cast[other] = this.notCast
					}
				}
			}
			cast[registrationId] = chosen
			this.cast = cast
		},

		/**
		 * Confirm the casting as it stands in the table.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
		 */
		async confirm() {
			this.confirming = true
			this.confirmed = false
			const pairs = this.proposalRows.map((row) => ({
				registration: row.id,
				character: this.cast[row.id]?.id ?? '',
			}))
			const result = await confirmCasting(this.eventId, pairs)
			this.refused = result.refused
			this.confirmed = result.ok
			this.confirming = false
			if (result.written.length > 0) {
				await this.load()
			}
		},

		/**
		 * The registrations that can still fill a role, as options.
		 *
		 * @param {object} role The role, with its assignments.
		 * @return {Array<object>} The options.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-volunteers-fill-crew-and-npc-roles-req-ecc-004
		 */
		volunteerOptions(role) {
			return volunteersFor(
				this.view.registrations,
				this.view.ticketTypes,
				role.assignments,
			).map((registration) => ({
				id: idOf(registration),
				label: registrationLabel(registration),
			}))
		},

		/**
		 * Assign the chosen volunteer to a role.
		 *
		 * @param {object} role The role.
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-volunteers-fill-crew-and-npc-roles-req-ecc-004
		 */
		async assign(role) {
			const choice = this.crewChoice[role.id]
			if (!choice) {
				return
			}
			this.assigning = role.id
			const result = await assignCrew(role.id, choice.id)
			this.crewMessage = result.ok ? '' : result.message
			this.assigning = ''
			if (result.ok) {
				this.crewChoice = { ...this.crewChoice, [role.id]: null }
				await this.load()
			}
		},
	},
}
</script>

<style scoped>
.event-casting {
	container-type: inline-size;
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

.event-casting__role {
	padding: calc(var(--default-grid-baseline, 4px) * 2) 0;
	border-bottom: 1px solid var(--color-border);
}

.event-casting__assign {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}

/*
 * Phone layout (admin-phone-friendly-pages, REQ-APF-001): long names wrap
 * inside their cell instead of widening the tab.
 */
@container (max-width: 600px) {
	.event-casting {
		overflow-wrap: anywhere;
	}
}
</style>
