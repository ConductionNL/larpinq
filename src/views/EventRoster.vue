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
		<div v-if="canCheckIn" class="event-roster__scan" data-testid="event-roster-scan">
			<NcButton
				variant="primary"
				:aria-expanded="scanOpen ? 'true' : 'false'"
				data-testid="event-roster-scan-toggle"
				@click="toggleScan">
				{{ scanOpen ? t('larpinq', 'Close scan') : t('larpinq', 'Scan') }}
			</NcButton>
			<div v-if="scanOpen" class="event-roster__scan-panel">
				<video
					v-show="cameraOn"
					ref="scanVideo"
					class="event-roster__scan-video"
					muted
					playsinline
					:aria-label="t('larpinq', 'Camera preview for scanning check-in codes')" />
				<form
					class="event-roster__scan-form"
					data-testid="event-roster-scan-form"
					@submit.prevent="submitCode(scanCode)">
					<NcTextField
						v-model="scanCode"
						:label="t('larpinq', 'Check-in code')"
						:helperText="t('larpinq', 'Type the code or use a handheld scanner, then press Enter.')"
						autocomplete="off"
						data-testid="event-roster-scan-code" />
					<NcButton
						type="submit"
						variant="secondary"
						:disabled="scanBusy || scanCode.trim() === ''"
						data-testid="event-roster-scan-submit">
						{{ t('larpinq', 'Check in') }}
					</NcButton>
				</form>
				<p
					v-if="scanResult"
					class="event-roster__scan-result"
					:class="{ 'event-roster__scan-result--ok': scanResult.ok }"
					role="status"
					aria-live="polite"
					data-testid="event-roster-scan-result">
					{{ scanResult.text }}
				</p>
			</div>
		</div>
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
					class="event-roster__action"
					variant="secondary"
					:disabled="
						saving === row.character || row.status === 'checked-in'
					"
					data-testid="event-roster-checkin"
					@click="setStatus(row, 'checked-in')">
					{{ t('larpinq', 'Check in') }}
				</NcButton>
				<NcButton
					class="event-roster__action"
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
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { objectTabSource } from '../mixins/objectTab.js'
import { checkInByCode, normaliseCode, resultMessage } from '../services/checkinCode.js'
import { ocsHeaders, ocsJsonHeaders } from '../services/requestHeaders.js'

/**
 * How long a scan result stays on the panel, in milliseconds.
 */
const RESULT_MS = 3000

/**
 * How often the camera frame is read for a QR code, in milliseconds.
 */
const DETECT_MS = 300

/**
 * A QR code reader of the browser, when it has one that reads QR codes.
 *
 * @param {object} scope The global scope (window).
 * @return {Promise<object|null>} A BarcodeDetector, or null.
 */
async function qrDetector(scope) {
	const Detector = scope?.BarcodeDetector
	if (typeof Detector !== 'function') {
		return null
	}
	try {
		const formats = typeof Detector.getSupportedFormats === 'function'
			? await Detector.getSupportedFormats()
			: []
		return formats.includes('qr_code') ? new Detector({ formats: ['qr_code'] }) : null
	} catch {
		return null
	}
}

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
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-the-roster-shows-who-is-on-site-now-req-eco-002
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
 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
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
		NcTextField,
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
			scanOpen: false,
			scanCode: '',
			scanBusy: false,
			scanResult: null,
			cameraOn: false,
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
		 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-the-roster-shows-who-is-on-site-now-req-eco-002
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

	beforeUnmount() {
		this.stopCamera()
		clearTimeout(this.resultTimer)
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
		 * Open or close the scan panel. Opening starts the camera where the
		 * browser reads QR codes; the code field is there either way.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
		 */
		async toggleScan() {
			if (this.scanOpen) {
				this.scanOpen = false
				this.stopCamera()
				return
			}
			this.scanOpen = true
			await this.startCamera()
		},

		/**
		 * Start the camera preview and read its frames for a QR code. Does
		 * nothing where the browser has no QR reader or no camera, or the
		 * steward refuses it.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
		 */
		async startCamera() {
			const scope = typeof window === 'undefined' ? globalThis : window
			const detector = await qrDetector(scope)
			const media = scope?.navigator?.mediaDevices
			if (detector === null || typeof media?.getUserMedia !== 'function') {
				return
			}
			try {
				this.stream = await media.getUserMedia({
					video: { facingMode: 'environment' },
					audio: false,
				})
			} catch {
				return
			}
			if (this.scanOpen === false) {
				this.stopCamera()
				return
			}
			this.cameraOn = true
			await this.$nextTick()
			const video = this.$refs.scanVideo
			if (video) {
				video.srcObject = this.stream
				await video.play?.()
			}
			this.detectTimer = setInterval(() => this.detectFrame(detector), DETECT_MS)
		},

		/**
		 * Read one camera frame and check in the first QR code on it.
		 *
		 * @param {object} detector The BarcodeDetector.
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
		 */
		async detectFrame(detector) {
			const video = this.$refs.scanVideo
			if (this.scanBusy || !video) {
				return
			}
			try {
				const found = await detector.detect(video)
				const raw = found?.[0]?.rawValue ?? ''
				if (raw !== '') {
					await this.submitCode(raw)
				}
			} catch {
				// A frame that cannot be read yet; the next one is tried.
			}
		},

		/**
		 * Stop the camera and its frame reading.
		 *
		 * @return {void}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
		 */
		stopCamera() {
			clearInterval(this.detectTimer)
			this.detectTimer = null
			this.stream?.getTracks?.().forEach((track) => track.stop())
			this.stream = null
			this.cameraOn = false
		},

		/**
		 * Check in the participant a scanned or typed code belongs to, show the
		 * answer for three seconds and refresh the roster. The same code seen
		 * again by the camera while its answer is still shown is not resent.
		 *
		 * @param {string} raw What was scanned or typed.
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
		 */
		async submitCode(raw) {
			const code = normaliseCode(raw)
			if (this.scanBusy || (code !== '' && code === this.lastCode && this.scanResult !== null)) {
				return
			}
			this.scanBusy = true
			this.lastCode = code
			try {
				const result = await checkInByCode(this.eventId, code)
				this.showResult(resultMessage(this.t, result))
				this.scanCode = ''
				if (result.status === 'checked-in') {
					await this.fetchRoster()
				}
			} finally {
				this.scanBusy = false
			}
		},

		/**
		 * Show a scan answer, and clear it after three seconds.
		 *
		 * @param {{text: string, ok: boolean}} message The answer.
		 * @return {void}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-stewards-check-in-by-scanning-or-typing-a-code-req-eqc-003
		 */
		showResult(message) {
			clearTimeout(this.resultTimer)
			this.scanResult = message
			this.resultTimer = setTimeout(() => {
				this.scanResult = null
				this.lastCode = ''
			}, RESULT_MS)
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
		 * @spec openspec/specs/event-checkin-roster/spec.md#requirement-a-game-master-checks-a-participant-out-req-eco-001
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

/*
 * Phone layout (admin-phone-friendly-pages, REQ-APF-002): the roster sizes
 * itself to the width it gets, so the check-in actions grow to a 44 pixel
 * tap target with one hand at the gate. The table itself belongs to the
 * shared CnDataTable and keeps its own narrow-width behaviour (hydra ADR-012).
 */
.event-roster {
	container-type: inline-size;
}

@container (max-width: 600px) {
	.event-roster__action {
		min-width: 44px;
		min-height: 44px;
	}
}

.event-roster__scan {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 8px;
	margin-bottom: 12px;
}

.event-roster__scan-panel {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 100%;
	max-width: 480px;
}

.event-roster__scan-video {
	width: 100%;
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-background-dark);
}

.event-roster__scan-form {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.event-roster__scan-result {
	margin: 0;
	padding: 8px 12px;
	border-radius: var(--border-radius, 8px);
	background-color: var(--color-warning);
	color: var(--color-warning-text, var(--color-main-text));
	font-weight: bold;
}

.event-roster__scan-result--ok {
	background-color: var(--color-success);
	color: var(--color-success-text, var(--color-primary-element-text));
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
