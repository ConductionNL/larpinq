<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Casting tab on the registration page, reached from My registrations
 (events-casting-and-crew-roles): the player ranks up to five characters open
 for casting on the event until its casting deadline, and sees the crew and
 NPC roles they were assigned. The ranking is stored as casting preferences
 through the OpenRegister objects API; larpinq refuses a ranking after the
 deadline or on someone else's registration.

 @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 @visual exclude Sidebar-tab section (not a routed page); covered by tests/vitest/eventCastingView.spec.js and the planned tests/e2e/event-casting.spec.ts.
-->
<template>
	<div class="registration-casting" data-testid="registration-casting">
		<p v-if="loading">
			{{ t('larpinq', 'Loading casting') }}
		</p>
		<p v-else-if="view.state !== 'ok'" role="alert">
			{{ t('larpinq', 'Could not load the casting.') }}
		</p>
		<template v-else>
			<template v-if="view.characters.length > 0">
				<p>
					{{
						t(
							'larpinq',
							'Rank the characters you would like to play, 1 for the one you want most.',
						)
					}}
				</p>
				<p
					v-if="closed"
					role="status"
					data-testid="registration-casting-closed">
					{{ t('larpinq', 'The casting deadline has passed.') }}
				</p>
				<form @submit.prevent="save">
					<NcSelect
						v-for="character in view.characters"
						:key="idOf(character)"
						:modelValue="optionOf(ranks[idOf(character)])"
						:inputLabel="character.name"
						:options="rankOptions"
						:clearable="false"
						:disabled="closed"
						:data-testid="`registration-casting-rank-${idOf(character)}`"
						@update:modelValue="rankChanged(idOf(character), $event)" />
					<p
						v-if="!valid"
						role="alert"
						data-testid="registration-casting-invalid">
						{{ t('larpinq', 'Give each rank to one character only.') }}
					</p>
					<NcButton
						type="submit"
						variant="primary"
						:disabled="closed || saving || !valid"
						data-testid="registration-casting-save">
						{{
							saving
								? t('larpinq', 'Saving')
								: t('larpinq', 'Save ranking')
						}}
					</NcButton>
					<p
						v-if="message"
						role="alert"
						data-testid="registration-casting-refused">
						{{ message }}
					</p>
					<p
						v-if="saved"
						role="status"
						data-testid="registration-casting-saved">
						{{ t('larpinq', 'Your ranking is saved.') }}
					</p>
				</form>
			</template>
			<p v-else>
				{{ t('larpinq', 'This event has no characters open for casting.') }}
			</p>

			<template v-if="view.crew.length > 0">
				<h3>{{ t('larpinq', 'Your crew and NPC roles') }}</h3>
				<ul data-testid="registration-casting-crew">
					<li v-for="role in view.crew" :key="idOf(role)">
						{{ role.name }}
					</li>
				</ul>
			</template>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { objectTab } from '../mixins/objectTab.js'
import {
	fetchRegistrationCasting,
	idOf,
	MAX_RANK,
	rankingClosed,
	rankingIsValid,
	saveRanking,
} from '../services/eventCasting.js'

export default {
	name: 'RegistrationCasting',

	components: { NcButton, NcSelect },

	mixins: [objectTab('registrationId')],

	data() {
		return {
			loading: true,
			view: { state: 'ok', characters: [], preferences: [], crew: [] },
			ranks: {},
			saving: false,
			saved: false,
			message: '',
		}
	},

	computed: {
		/**
		 * "Not ranked", then ranks 1 to 5.
		 *
		 * @return {Array<object>} The options.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		rankOptions() {
			const ranks = Array.from({ length: MAX_RANK }, (_, i) => ({
				id: i + 1,
				label: t('larpinq', 'Rank {rank}', { rank: i + 1 }),
			}))
			return [{ id: 0, label: t('larpinq', 'Not ranked') }, ...ranks]
		},

		/**
		 * Whether the casting deadline has passed.
		 *
		 * @return {boolean} True when ranking has closed.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
		 */
		closed() {
			return rankingClosed(this.view.event)
		},

		/**
		 * Whether every rank is given once at most.
		 *
		 * @return {boolean} True when the ranking can be saved.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		valid() {
			return rankingIsValid(this.ranks)
		},
	},

	/**
	 * Load the ranking.
	 *
	 * @return {Promise<void>}
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		t,
		idOf,

		/**
		 * Load the open characters and the player's stored ranking.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
		 */
		async load() {
			this.loading = true
			this.view = await fetchRegistrationCasting(this.registrationId)
			const ranks = {}
			for (const character of this.view.characters ?? []) {
				ranks[idOf(character)] = 0
			}
			for (const preference of this.view.preferences ?? []) {
				ranks[String(preference.character)] = Number(preference.rank) || 0
			}
			this.ranks = ranks
			this.loading = false
		},

		/**
		 * The option of a rank.
		 *
		 * @param {number} rank The rank, 0 for none.
		 * @return {object} The option.
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		optionOf(rank) {
			return (
				this.rankOptions.find((option) => option.id === Number(rank || 0))
				?? this.rankOptions[0]
			)
		},

		/**
		 * Keep a changed rank.
		 *
		 * @param {string} characterId The character uuid.
		 * @param {object|null} option The chosen option.
		 * @return {void}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
		 */
		rankChanged(characterId, option) {
			this.ranks = { ...this.ranks, [characterId]: Number(option?.id ?? 0) }
			this.saved = false
		},

		/**
		 * Save the ranking.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
		 */
		async save() {
			if (!this.valid || this.closed) {
				return
			}
			this.saving = true
			this.saved = false
			const result = await saveRanking(
				this.view.registration,
				this.view.preferences,
				this.ranks,
			)
			this.message = result.ok
				? ''
				: result.message || t('larpinq', 'Could not save the ranking.')
			this.saving = false
			if (result.ok) {
				await this.load()
				this.saved = true
			}
		},
	},
}
</script>

<style scoped>
.registration-casting form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
}
</style>
