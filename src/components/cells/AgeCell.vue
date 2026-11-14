<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  SPDX-License-Identifier: EUPL-1.2

  AgeCell — an index column's date with a marker when it lies more than a
  given number of days in the past, so a list can show what has waited too
  long (communication-help-desk, REQ-CHD-002: questions without activity for
  more than 3 days are marked on the Help desk).

  Registered as the `age` cell widget (App.vue `cellWidgets`) and used through
  the manifest: `{ "key": "lastMessageAt", "widget": "age",
  "widgetProps": { "olderThanDays": 3 } }`. Without `olderThanDays` the cell
  shows the date only.
-->
<template>
	<span class="age-cell">
		<span class="age-cell__value">{{ shown }}</span>
		<span v-if="isOld" class="age-cell__marker">
			{{ marker }}
		</span>
	</span>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

/** One day in milliseconds. */
const DAY = 24 * 60 * 60 * 1000

export default {
	name: 'AgeCell',

	props: {
		/** The raw cell value: a date or date-time string. */
		value: {
			type: [String, Number, Object],
			default: null,
		},

		/** The value as the column's formatter rendered it, when there is one. */
		formatted: {
			type: [String, Number],
			default: null,
		},

		/** The whole row. */
		row: {
			type: Object,
			default: null,
		},

		/** Mark the value when it is more than this many days old; 0 never marks. */
		olderThanDays: {
			type: Number,
			default: 0,
		},
	},

	computed: {
		/**
		 * The text shown: the formatted value, else the date as the browser writes it.
		 *
		 * @return {string}
		 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
		 */
		shown() {
			if (this.formatted !== null && this.formatted !== undefined && this.formatted !== '') {
				return String(this.formatted)
			}
			const time = this.timeOf(this.value)
			if (time === null) {
				return ''
			}
			return new Date(time).toLocaleDateString()
		},

		/**
		 * Whether the value lies more than `olderThanDays` days in the past.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
		 */
		isOld() {
			return this.isOlderThan(this.value, this.olderThanDays, Date.now())
		},

		/**
		 * The marker text. Not a plural: the limit comes from the manifest,
		 * and the browser catalogue carries no plural forms.
		 *
		 * @return {string}
		 */
		marker() {
			return t('larpinq', 'Waiting too long')
		},
	},

	methods: {
		/**
		 * The moment a value names, in milliseconds, or null when it names none.
		 *
		 * @param {unknown} value The value.
		 * @return {number|null}
		 */
		timeOf(value) {
			if (typeof value !== 'string' && typeof value !== 'number') {
				return null
			}
			if (value === '') {
				return null
			}
			const time = new Date(value).getTime()
			return Number.isNaN(time) ? null : time
		},

		/**
		 * Whether a value lies more than a number of days before a moment.
		 *
		 * @param {unknown} value The value.
		 * @param {number} days The number of days; 0 or less never marks.
		 * @param {number} now The moment to compare with, in milliseconds.
		 * @return {boolean}
		 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
		 */
		isOlderThan(value, days, now) {
			const time = this.timeOf(value)
			if (time === null || !(days > 0)) {
				return false
			}
			return now - time > days * DAY
		},
	},
}
</script>

<style scoped>
.age-cell {
	display: inline-flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 6px;
}

.age-cell__marker {
	padding: 0 6px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-warning);
	color: var(--color-warning-text);
	font-size: var(--font-size-small, 13px);
}
</style>
