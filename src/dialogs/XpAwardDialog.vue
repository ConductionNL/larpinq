<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Award XP to everyone at an event in one save (events-xp-batch-award). The
  roster comes ticked by attendance: checked in and not yet awarded. A default
  amount and reason apply to every ticked row unless the row has its own.
  Existing awards show under each character and open on their own page for a
  change or removal. Opened by the EventDetail header action "Award XP",
  shown to game masters only.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Award XP')"
		size="large"
		data-testid="xp-award-dialog"
		@closing="$emit('close')">
		<p v-if="status === 'loading'">
			{{ t('larpinq', 'Loading the participants') }}
		</p>
		<NcNoteCard
			v-else-if="status === 'forbidden'"
			type="warning"
			data-testid="xp-award-forbidden">
			{{ t('larpinq', 'Only game masters can award XP.') }}
		</NcNoteCard>
		<NcNoteCard
			v-else-if="status === 'failed'"
			type="error"
			data-testid="xp-award-load-failed">
			{{
				t(
					'larpinq',
					'The participants could not be loaded. Try again later.',
				)
			}}
		</NcNoteCard>
		<template v-else>
			<NcNoteCard v-if="rows.length === 0" type="info">
				{{ t('larpinq', 'No characters take part in this event yet.') }}
			</NcNoteCard>
			<template v-else>
				<div class="xp-award__defaults">
					<NcTextField
						v-model="defaultAmount"
						type="number"
						min="1"
						data-testid="xp-award-default-amount"
						:label="t('larpinq', 'XP for everyone ticked')"
						:disabled="busy" />
					<NcTextField
						v-model="defaultReason"
						data-testid="xp-award-default-reason"
						:label="t('larpinq', 'Award Reason')"
						:disabled="busy" />
				</div>
				<table class="xp-award__table" data-testid="xp-award-rows">
					<thead>
						<tr>
							<th>{{ t('larpinq', 'Award XP') }}</th>
							<th>{{ t('larpinq', 'Character') }}</th>
							<th>{{ t('larpinq', 'XP Amount') }}</th>
							<th>{{ t('larpinq', 'Award Reason') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="row in rows"
							:key="row.character"
							:data-testid="`xp-award-row-${row.character}`">
							<td>
								<NcCheckboxRadioSwitch
									v-model="row.ticked"
									:aria-label="
										t('larpinq', 'Award XP to {name}', {
											name: row.name,
										})
									"
									:disabled="busy" />
							</td>
							<td>
								<strong>{{ row.name }}</strong>
								<span v-if="row.playerName" class="xp-award__muted">
									{{ row.playerName }}
								</span>
								<span class="xp-award__muted">{{
									attendanceLabel(row)
								}}</span>
								<ul
									v-if="row.awards.length > 0"
									class="xp-award__existing">
									<li v-for="award in row.awards" :key="award.id">
										<NcButton
											variant="tertiary"
											:aria-label="
												t('larpinq', 'Open this award')
											"
											@click="openAward(award.id)">
											{{ awardLabel(award) }}
										</NcButton>
									</li>
								</ul>
							</td>
							<td>
								<NcTextField
									v-model="row.amount"
									type="number"
									min="1"
									:label="
										t('larpinq', 'XP for {name}', {
											name: row.name,
										})
									"
									:placeholder="String(defaultAmount)"
									:disabled="busy" />
							</td>
							<td>
								<NcTextField
									v-model="row.reason"
									:label="
										t('larpinq', 'Reason for {name}', {
											name: row.name,
										})
									"
									:disabled="busy" />
							</td>
						</tr>
					</tbody>
				</table>
				<NcNoteCard
					v-if="result"
					:type="
						result.ok && result.refused.length === 0
							? 'success'
							: 'warning'
					"
					data-testid="xp-award-result">
					<template v-if="result.ok">
						{{
							t('larpinq', 'Awards made: {count}', {
								count: result.created,
							})
						}}
						<ul v-if="result.refused.length > 0">
							<li
								v-for="refusal in result.refused"
								:key="refusal.character">
								{{ nameOf(refusal.character) }}:
								{{ refusalText(refusal.reason) }}
							</li>
						</ul>
					</template>
					<template v-else>
						{{
							t('larpinq', 'The awards could not be saved: {error}', {
								error: result.error,
							})
						}}
					</template>
				</NcNoteCard>
			</template>
		</template>

		<template #actions>
			<NcButton data-testid="xp-award-cancel" @click="$emit('close')">
				{{ t('larpinq', 'Close') }}
			</NcButton>
			<NcButton
				data-testid="xp-award-save"
				variant="primary"
				:disabled="toSave.length === 0 || busy"
				@click="save">
				{{
					busy
						? t('larpinq', 'Saving')
						: t('larpinq', 'Award XP ({count})', {
								count: toSave.length,
							})
				}}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	fetchAwardRoster,
	initialRows,
	refusalText,
	rowsToSave,
	saveAwards,
} from '../services/xpAwardBatch.js'

export default {
	name: 'XpAwardDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcNoteCard,
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
			status: 'loading',
			rows: [],
			defaultAmount: '',
			defaultReason: '',
			busy: false,
			result: null,
		}
	},

	computed: {
		/**
		 * The event this dialog awards for.
		 *
		 * @return {string} The event UUID.
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		id() {
			const fromProp = this.eventId.startsWith('@') ? '' : this.eventId
			return fromProp || String(this.$route?.params?.id ?? '')
		},

		/**
		 * The rows a save would send now.
		 *
		 * @return {Array<object>} The rows.
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		toSave() {
			return rowsToSave(this.rows, this.defaultAmount, this.defaultReason)
		},
	},

	/**
	 * Load the award roster.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		t,
		refusalText,

		/**
		 * Read the roster and set the default reason from the event's name.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		async load() {
			const state = await fetchAwardRoster(this.id)
			this.status = state.status
			this.rows = initialRows(state.rows)
			if (state.eventName && this.defaultReason === '') {
				this.defaultReason = t('larpinq', 'Attended {event}', {
					event: state.eventName,
				})
			}
		},

		/**
		 * The attendance of a row in words.
		 *
		 * @param {object} row The row.
		 * @return {string} The label.
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		attendanceLabel(row) {
			if (row.attendance === 'checked-in') {
				return t('larpinq', 'Checked in')
			}
			if (row.attendance === 'no-show') {
				return t('larpinq', 'No-show')
			}
			return t('larpinq', 'No check-in recorded')
		},

		/**
		 * An existing award in words.
		 *
		 * @param {object} award The award.
		 * @return {string} The label.
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		awardLabel(award) {
			const by = award.awardedBy || t('larpinq', 'Unknown')
			return award.reason
				? t('larpinq', '{amount} XP by {by}: {reason}', {
						amount: award.amount,
						by,
						reason: award.reason,
					})
				: t('larpinq', '{amount} XP by {by}', { amount: award.amount, by })
		},

		/**
		 * The name of a character on the roster.
		 *
		 * @param {string} characterId The character UUID.
		 * @return {string} The name.
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		nameOf(characterId) {
			return (
				this.rows.find((row) => row.character === characterId)?.name
				|| characterId
			)
		},

		/**
		 * Open an existing award on its own page, to change or remove it.
		 *
		 * @param {string} awardId The award UUID.
		 * @return {void}
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		openAward(awardId) {
			this.$router?.push(`/xp-awards/${encodeURIComponent(awardId)}`)
			this.$emit('close')
		},

		/**
		 * Save the ticked rows, then reload so new awards show and untick.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/event-xp-awards/spec.md
		 */
		async save() {
			if (this.toSave.length === 0 || this.busy) {
				return
			}
			this.busy = true
			this.result = await saveAwards(this.id, this.toSave)
			if (this.result.ok) {
				await this.load()
			}
			this.busy = false
		},
	},
}
</script>

<style scoped>
.xp-award__defaults {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 4);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.xp-award__table {
	width: 100%;
	border-collapse: collapse;
}

.xp-award__table th,
.xp-award__table td {
	padding: calc(var(--default-grid-baseline) * 2);
	text-align: start;
	vertical-align: top;
	border-block-end: 1px solid var(--color-border);
}

.xp-award__muted {
	display: block;
	color: var(--color-text-maxcontrast);
}

.xp-award__existing {
	margin: 0;
	padding: 0;
	list-style: none;
}
</style>
