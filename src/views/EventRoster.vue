<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Event check-in roster surface (event-checkin-roster).

 Lists every confirmed participant of an event with player name, character
 type and current attendance status, built from the shared
 @conduction/nextcloud-vue CnDataTable (ADR-012 — no bespoke table). Game
 masters get per-row check-in / no-show controls; players see the roster
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
		<div v-if="degraded" class="event-roster__notice" data-testid="event-roster-degraded">
			{{ t('larpingapp', 'Attendance tracking is unavailable — showing the participant list read-only.') }}
		</div>
		<CnDataTable
			:columns="columns"
			:rows="participants"
			:loading="loading"
			:empty-text="t('larpingapp', 'No confirmed participants for this event yet.')"
			data-testid="event-roster-table">
			<template #column-status="{ row }">
				<CnStatusBadge :label="statusLabel(row.status)" :variant="statusVariant(row.status)" />
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
					:disabled="saving === row.character || row.status === 'checked-in'"
					data-testid="event-roster-checkin"
					@click="setStatus(row, 'checked-in')">
					{{ t('larpingapp', 'Check in') }}
				</NcButton>
				<NcButton
					variant="tertiary"
					:disabled="saving === row.character || row.status === 'no-show'"
					data-testid="event-roster-noshow"
					@click="setStatus(row, 'no-show')">
					{{ t('larpingapp', 'No-show') }}
				</NcButton>
			</template>
		</CnDataTable>
	</div>
</template>

<script>
import { CnDataTable, CnStatusBadge } from '@conduction/nextcloud-vue'
// @nextcloud/vue v9 ships an `exports` map; the v8 `dist/Components/*.js`
// deep paths are no longer exported and throw ERR_PACKAGE_PATH_NOT_EXPORTED.
import NcButton from '@nextcloud/vue/components/NcButton'
import { generateUrl } from '@nextcloud/router'

/**
 * Human-readable label + badge variant per attendance status.
 */
const STATUS_META = {
	registered: { label: 'Registered', variant: 'default' },
	'checked-in': { label: 'Checked in', variant: 'success' },
	'no-show': { label: 'No-show', variant: 'error' },
}

export default {
	name: 'EventRoster',

	components: {
		CnDataTable,
		CnStatusBadge,
		NcButton,
	},

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The event UUID. Falls back to the injected object context or the
		 * route param when not passed explicitly by the manifest host.
		 *
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},
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
			return this.objectId
				|| this.cnObjectContext?.objectId
				|| this.$route?.params?.id
				|| ''
		},

		/**
		 * Column definitions for the roster table.
		 *
		 * @return {Array<{key: string, label: string}>}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		columns() {
			return [
				{ key: 'playerName', label: this.t('larpingapp', 'Player') },
				{ key: 'name', label: this.t('larpingapp', 'Character') },
				{ key: 'type', label: this.t('larpingapp', 'Type') },
				{ key: 'status', label: this.t('larpingapp', 'Attendance') },
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
				const response = await fetch(
					generateUrl('/apps/larpingapp/api/events/{id}/roster', { id: this.eventId }),
					{ headers: { requesttoken: OC.requestToken, 'OCS-APIREQUEST': 'true' } },
				)
				if (!response.ok) {
					throw new Error(`roster fetch failed: ${response.status}`)
				}
				const data = await response.json()
				this.participants = Array.isArray(data.participants) ? data.participants : []
				this.attendanceAvailable = data.attendanceAvailable !== false
				this.isGm = data.isGm === true
				this.loadFailed = false
			} catch (error) {
				// Graceful degradation — never break the event page.
				this.participants = []
				this.attendanceAvailable = false
				this.loadFailed = true
				// eslint-disable-next-line no-console
				console.warn('[larpingapp] roster unavailable', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Record a status change for one participant (GM only). Optimistically
		 * refreshes the row from the server response.
		 *
		 * @param {object} row    The participant row.
		 * @param {string} status The new status (checked-in | no-show).
		 * @return {Promise<void>}
		 * @spec openspec/specs/event-checkin-roster/spec.md
		 */
		async setStatus(row, status) {
			this.saving = row.character
			try {
				const response = await fetch(
					generateUrl('/apps/larpingapp/api/events/{id}/attendance', { id: this.eventId }),
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							requesttoken: OC.requestToken,
							'OCS-APIREQUEST': 'true',
						},
						body: JSON.stringify({ character: row.character, status }),
					},
				)
				if (!response.ok) {
					throw new Error(`attendance write failed: ${response.status}`)
				}
				const saved = await response.json()
				row.status = saved.status || status
				row.checkedInAt = saved.checkedInAt || ''
				row.checkedInBy = saved.checkedInBy || ''
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[larpingapp] attendance write failed', error)
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
			return this.t('larpingapp', meta.label)
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
.event-roster__notice {
	margin-bottom: 12px;
	padding: 8px 12px;
	border-radius: var(--border-radius, 8px);
	background-color: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
