<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Scan mode of the Check-in tab (events-qr-checkin): a camera preview where
 the browser reads QR codes, and always a code field that submits on Enter,
 which is what handheld scanners type. Each code goes to the check-in
 endpoint; the answer shows for a few seconds and the roster refreshes.

 @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 @visual exclude Part of the Check-in sidebar tab (not a routed page); covered by
 tests/vitest/checkinCode.spec.js and the qr-checkin e2e workflow.
-->
<template>
	<section class="checkin-scan" data-testid="checkin-scan">
		<video
			v-if="camera"
			ref="video"
			class="checkin-scan__video"
			muted
			playsinline
			:aria-label="
				t('larpinq', 'Camera preview for scanning check-in codes')
			" />
		<form class="checkin-scan__form" @submit.prevent="submit">
			<NcTextField
				v-model="code"
				:label="t('larpinq', 'Check-in code')"
				:helperText="
					t('larpinq', 'Scan or type the code, then press Enter.')
				"
				autocomplete="off"
				data-testid="checkin-scan-code" />
			<NcButton type="submit" variant="primary" :disabled="busy || !code">
				{{ t('larpinq', 'Check in') }}
			</NcButton>
		</form>
		<p
			v-if="message"
			class="checkin-scan__result"
			:class="`checkin-scan__result--${tone}`"
			role="status"
			data-testid="checkin-scan-result">
			{{ message }}
		</p>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { cameraScanSupported, checkInByCode } from '../services/checkinCode.js'

/**
 * How long an answer stays on screen, in milliseconds.
 */
const SHOW_FOR = 3000

/**
 * How often a camera frame is read, in milliseconds.
 */
const SCAN_EVERY = 400

export default {
	name: 'CheckinScanPanel',

	components: { NcButton, NcTextField },

	props: {
		/**
		 * The event whose gate this is.
		 *
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		eventId: {
			type: String,
			required: true,
		},
	},

	emits: ['checkedIn'],

	data() {
		return {
			code: '',
			busy: false,
			camera: false,
			message: '',
			tone: 'info',
			stream: null,
			timer: null,
			clear: null,
		}
	},

	/**
	 * Start the camera when this browser reads QR codes.
	 *
	 * @return {Promise<void>}
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	async mounted() {
		this.camera = await cameraScanSupported(window)
		if (this.camera) {
			await this.$nextTick()
			await this.startCamera()
		}
	},

	/**
	 * Release the camera and the answer timer.
	 *
	 * @return {void}
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	beforeUnmount() {
		this.stopCamera()
		clearTimeout(this.clear)
	},

	methods: {
		/**
		 * Check in the typed or scanned code.
		 *
		 * @param {string} scanned A code read from the camera, or empty for the field.
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		async submit(scanned) {
			const code = typeof scanned === 'string' && scanned ? scanned : this.code
			if (!code || this.busy) {
				return
			}
			this.busy = true
			const answer = await checkInByCode(this.eventId, code)
			this.busy = false
			this.code = ''
			this.show(answer)
			if (answer.status === 'checked-in') {
				this.$emit('checkedIn', answer)
			}
		},

		/**
		 * Show an answer for a few seconds.
		 *
		 * @param {object} answer The endpoint's answer.
		 * @return {void}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		show(answer) {
			const messages = {
				'checked-in': [
					this.t('larpinq', 'Checked in: {name}', {
						name: [answer.name, answer.character]
							.filter(Boolean)
							.join(', '),
					}),
					'success',
				],

				already: [
					this.t('larpinq', 'Already checked in at {at} by {by}', {
						at: this.time(answer.at),
						by: answer.by,
					}),
					'warning',
				],

				'not-accepted': [
					this.t(
						'larpinq',
						'This registration is not accepted. Nobody was checked in.',
					),
					'error',
				],

				'no-character': [
					this.t(
						'larpinq',
						'This registration has no character yet. Nobody was checked in.',
					),
					'error',
				],

				unknown: [
					this.t(
						'larpinq',
						'Unknown code for this event. Nobody was checked in.',
					),
					'error',
				],

				refused: [
					this.t(
						'larpinq',
						'Only game masters can check participants in.',
					),
					'error',
				],
			}
			const [message, tone] = messages[answer.status] || [
				this.t('larpinq', 'The check-in failed. Please try again.'),
				'error',
			]
			this.message = message
			this.tone = tone
			clearTimeout(this.clear)
			this.clear = setTimeout(() => {
				this.message = ''
			}, SHOW_FOR)
		},

		/**
		 * The time of day of a moment.
		 *
		 * @param {string} moment The moment (ISO 8601).
		 * @return {string} The time, or the moment itself when unreadable.
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		time(moment) {
			const date = new Date(moment)
			return Number.isNaN(date.getTime())
				? moment || ''
				: date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
		},

		/**
		 * Start the camera and read a frame every few hundred milliseconds.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		async startCamera() {
			try {
				this.stream = await navigator.mediaDevices.getUserMedia({
					video: { facingMode: 'environment' },
				})
				this.$refs.video.srcObject = this.stream
				await this.$refs.video.play()
			} catch {
				this.camera = false
				return
			}
			const detector = new window.BarcodeDetector({ formats: ['qr_code'] })
			this.timer = setInterval(async () => {
				if (this.busy || !this.$refs.video) {
					return
				}
				const found = await detector.detect(this.$refs.video).catch(() => [])
				if (found.length > 0) {
					await this.submit(found[0].rawValue)
				}
			}, SCAN_EVERY)
		},

		/**
		 * Stop reading frames and release the camera.
		 *
		 * @return {void}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		stopCamera() {
			clearInterval(this.timer)
			this.stream?.getTracks().forEach((track) => track.stop())
			this.stream = null
		},
	},
}
</script>

<style scoped>
.checkin-scan {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block-end: calc(var(--default-grid-baseline) * 4);
}

.checkin-scan__video {
	width: 100%;
	max-width: 360px;
	border-radius: var(--border-radius-large);
}

.checkin-scan__form {
	display: flex;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
}

.checkin-scan__result {
	padding: calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius);
	font-weight: bold;
}

.checkin-scan__result--success {
	background: var(--color-success);
	color: var(--color-primary-element-text);
}

.checkin-scan__result--warning {
	background: var(--color-warning);
	color: var(--color-primary-element-text);
}

.checkin-scan__result--error {
	background: var(--color-error);
	color: var(--color-primary-element-text);
}

@media (prefers-reduced-motion: reduce) {
	.checkin-scan__result {
		transition: none;
	}
}
</style>
