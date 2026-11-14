<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Event check-in roster surface (event-checkin-roster).

 Lists every confirmed participant of an event with player name, character
 type and current attendance status, built from the shared
 @conduction/nextcloud-vue CnDataTable (ADR-012 — no bespoke table). Game
 masters get per-row check-in / no-show / check-out controls and an
 on-site-now count; players see the roster
 read-only. The attendance state and the write both go through the
 OR-delegated larping_attendance endpoints on EventsController — the surface
 never re-derives attendance. Degrades gracefully: when the attendance schema
 is absent the participant list still renders read-only with the controls
 hidden (DocuDesk / Forms-leaf degradation pattern), never throwing.

 @spec openspec/specs/event-checkin-roster/spec.md
 @visual exclude Sidebar-tab section (not a routed page); the roster GM check-in
 flow is covered by the EventsController attendance unit tests + the gate-19
 e2e-exclude scenarios on the spec, pending a live-servable frontend bundle.
-->
<template>
	<div class="event-roster" data-testid="event-roster">
		<div
			v-if="degraded"
			class="event-roster__notice"
			data-testid="event-roster-degraded">
			{{
				t(
					'larpinq',
					'Attendance tracking is unavailable — showing the participant list read-only.',
				)
			}}
		</div>
		<p
			v-if="attendanceAvailable && !loadFailed"
			class="event-roster__on-site"
			data-testid="event-roster-on-site">
			{{ t('larpinq', 'On site now: {count}', { count: onSiteCount }) }}
		</p>
		<CnDataTable
			:columns="columns"
			:rows="participants"
			:loading="loading"
			:emptyText="
				t('larpinq', 'No confirmed participants for this event yet.')
			"
			data-testid="event-roster-table">
			<template #column-status="{ row }">
				<CnStatusBadge
					:label="statusLabel(row.status)"
					:variant="statusVariant(row.status)" />
				<span
					v-if="checkedOutLabel(row)"
					class="event-roster__checked-out"
					data-testid="event-roster-checked-out">
					{{ checkedOutLabel(row) }}
				</span>
			</template>
			<template v-if="canCheckIn" #row-actions="{ row }">
				<!--
				  @nextcloud/vue v9 repurposed NcButton's `type` prop as the
				  NATIVE button type ('submit' | 'reset' | 'button'); the visual
				  style moved to `variant`. The Vue-2 spelling would render
				  `<button type="secondary">` with no warning and no lint error.
				-->
				<NcButton
					variant="secondary"
					:disabled="
						saving === row.character || row.status === 'checked-in'
					"
					data-testid="event-roster-checkin"
					@click="setStatus(row, 'checked-in')">
					{{ t('larpinq', 'Check in') }}
				</NcButton>
				<NcButton
					variant="tertiary"
					:disabled="saving === row.character || row.status === 'no-show'"
					data-testid="event-roster-noshow"
					@click="setStatus(row, 'no-show')">
					{{ t('larpinq', 'No-show') }}
				</NcButton>
				<NcButton
					v-if="row.status === 'checked-in'"
					variant="tertiary"
					:disabled="saving === row.character"
					data-testid="event-roster-checkout"
					@click="setStatus(row, 'checked-out')">
					{{ t('larpinq', 'Check out') }}
				</NcButton>
			</template>
		</CnDataTable>
	</div>
</template>

<script>
import { CnDataTable, CnStatusBadge } from '@conduction/nextcloud-vue'
import { getCanonicalLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
// @nextcloud/vue v9 ships an `exports` map; the v8 `dist/Components/*.js`
// deep paths are no longer exported and throw ERR_PACKAGE_PATH_NOT_EXPORTED.
import NcButton from '@nextcloud/vue/components/NcButton'
import { objectTabSource } from '../mixins/objectTab.js'
import { ocsHeaders, ocsJsonHeaders } from '../services/requestHeaders.js'

/**
 * Human-readable label + badge variant per attendance status.
 */
const STATUS_META = {
	registered: { label: 'Registered', variant: 'default' },
	'checked-in': { label: 'Checked in', variant: 'success' },
	'no-show': { label: 'No-show', variant: 'error' },
	'checked-out': { label: 'Checked out', variant: 'warning' },
}

/**
 * How many participants are on site now: checked in and not checked out
 * again. A participant checked in again after a check-out is `checked-in`.
 *
 * @param {Array<{status: string}>} rows The roster rows.
 * @return {number} The count.
 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-the-roster-shows-who-is-on-site-now-req-eco-002
 */
export function countOnSite(rows) {
	return rows.filter((row) => row.status === 'checked-in').length
}

/**
 * The short weekday and time of a check-out, in the user's locale and
 * time zone, or null when there is no valid stamp.
 *
 * @param {string} stamp The ISO-8601 `checkedOutAt`.
 * @param {string} locale The BCP 47 locale, e.g. `nl-NL`.
 * @return {{day: string, time: string}|null} The parts.
 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
 */
export function checkOutMoment(stamp, locale) {
	const date = stamp ? new Date(stamp) : null
	if (!date || Number.isNaN(date.getTime())) {
		return null
	}
	return {
		day: date.toLocaleDateString(locale, { weekday: 'short' }),
		time: date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }),
	}
}

/**
 * Fetch the roster of an event from the OR-delegated endpoint.
 *
 * @param {string} eventId The event UUID.
 * @return {Promise<object>} The roster body.
 * @throws {Error} When the server answers with an error status.
 */
async function fetchRosterData(eventId) {
	const response = await fetch(
		generateUrl('/apps/larpinq/api/events/{id}/roster', {
			id: eventId,
		}),
		{ headers: ocsHeaders() },
	)
	if (!response.ok) {
		throw new Error(`roster fetch failed: ${response.status}`)
	}
	return await response.json()
}

/**
 * Keep a fetched roster on the view.
 *
 * @param {object} view The roster view.
 * @param {object} data The roster body.
 * @return {void}
 */
function applyRoster(view, data) {
	view.participants = Array.isArray(data.participants)
		? data.participants
		: []
	view.attendanceAvailable = data.attendanceAvailable !== false
	view.isGm = data.isGm === true
	view.loadFailed = false
}

/**
 * Record one participant's attendance status.
 *
 * @param {string} eventId The event UUID.
 * @param {object} mark The `{character, status}` to record.
 * @return {Promise<object>} The stored record.
 * @throws {Error} When the server answers with an error status.
 */
async function postAttendance(eventId, mark) {
	const response = await fetch(
		generateUrl('/apps/larpinq/api/events/{id}/attendance', {
			id: eventId,
		}),
		{
			method: 'POST',
			headers: ocsJsonHeaders(),
			body: JSON.stringify(mark),
		},
	)
	if (!response.ok) {
		throw new Error(`attendance write failed: ${response.status}`)
	}
	return await response.json()
}

/**
 * The row fields a recorded status sets: the stored status (or the one
 * asked for), the check-in stamps and the check-out stamps.
 *
 * @param {object} saved The stored record.
 * @param {string} status The status asked for.
 * @return {{status: string, checkedInAt: string, checkedInBy: string, checkedOutAt: string, checkedOutBy: string}} The fields.
 */
function savedRowFields(saved, status) {
	return {
		status: saved.status || status,
		checkedInAt: saved.checkedInAt || '',
		checkedInBy: saved.checkedInBy || '',
		checkedOutAt: saved.checkedOutAt || '',
		checkedOutBy: saved.checkedOutBy || '',
	}
}

export default {
	name: 'EventRoster',

	components: {
		CnDataTable,
		CnStatusBadge,
		NcButton,
	},

	mixins: [objectTabSource],

	props: {
		/**
		 * Manifest config passthrough (unused product logic).
		 *
		 * @spec exclude Config passthrough — no product logic.
		 */
		config: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			participants: [],
			attendanceAvailable: true,
			isGm: false,
			loading: false,
			saving: null,
			loadFailed: false,
		}
	},

	computed: {
		/**
		 * Resolve the event id from prop → injected context → route param.
		 *
		 * @return {string}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		eventId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| this.$route?.params?.id
				|| ''
			)
		},

		/**
		 * Column definitions for the roster table.
		 *
		 * @return {Array<{key: string, label: string}>}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		columns() {
			return [
				{ key: 'playerName', label: this.t('larpinq', 'Player') },
				{ key: 'name', label: this.t('larpinq', 'Character') },
				{ key: 'type', label: this.t('larpinq', 'Type') },
				{ key: 'status', label: this.t('larpinq', 'Attendance') },
			]
		},

		/**
		 * Whether GM check-in controls should render — GM AND attendance storage
		 * present. Players and degraded deployments get a read-only roster.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		canCheckIn() {
			return this.isGm === true && this.attendanceAvailable === true
		},

		/**
		 * How many participants are on site now.
		 *
		 * @return {number}
		 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-the-roster-shows-who-is-on-site-now-req-eco-002
		 */
		onSiteCount() {
			return countOnSite(this.participants)
		},

		/**
		 * Whether to show the degraded notice (attendance storage absent).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		degraded() {
			return this.attendanceAvailable === false && this.loadFailed === false
		},
	},

	async mounted() {
		await this.fetchRoster()
	},

	methods: {
		/**
		 * Fetch the roster from the OR-delegated endpoint. Never throws — a
		 * failure degrades to an empty, uncoloured read-only list.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		async fetchRoster() {
			if (!this.eventId) {
				return
			}
			this.loading = true
			try {
				applyRoster(this, await fetchRosterData(this.eventId))
			} catch (error) {
				// Graceful degradation — never break the event page.
				this.participants = []
				this.attendanceAvailable = false
				this.loadFailed = true
				// eslint-disable-next-line no-console
				console.warn('[larpinq] roster unavailable', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Record a status change for one participant (GM only). Optimistically
		 * refreshes the row from the server response.
		 *
		 * @param {object} row    The participant row.
		 * @param {string} status The new status (checked-in | no-show | checked-out).
		 * @return {Promise<void>}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		async setStatus(row, status) {
			this.saving = row.character
			try {
				const saved = await postAttendance(this.eventId, {
					character: row.character,
					status,
				})
				Object.assign(row, savedRowFields(saved, status))
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[larpinq] attendance write failed', error)
			} finally {
				this.saving = null
			}
		},

		/**
		 * Localised label for an attendance status.
		 *
		 * @param {string} status The raw status.
		 * @return {string}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		statusLabel(status) {
			const meta = STATUS_META[status] || STATUS_META.registered
			return this.t('larpinq', meta.label)
		},

		/**
		 * "Checked out {day} {time}" for a checked-out row, else ''.
		 *
		 * @param {object} row The participant row.
		 * @return {string}
		 * @spec openspec/changes/events-check-out-and-on-site-count/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
		 */
		checkedOutLabel(row) {
			const moment = row.status === 'checked-out'
				? checkOutMoment(row.checkedOutAt, getCanonicalLocale())
				: null
			return moment ? this.t('larpinq', 'Checked out {day} {time}', moment) : ''
		},

		/**
		 * Badge variant for an attendance status.
		 *
		 * @param {string} status The raw status.
		 * @return {string}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		statusVariant(status) {
			return (STATUS_META[status] || STATUS_META.registered).variant
		},
	},
}
</script>

<style scoped>
.event-roster__on-site {
	margin: 0 0 8px;
	font-weight: bold;
}

.event-roster__checked-out {
	margin-inline-start: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.event-roster__notice {
	margin-bottom: 12px;
	padding: 8px 12px;
	border-radius: var(--border-radius, 8px);
	background-color: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
