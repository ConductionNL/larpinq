<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  SPDX-License-Identifier: EUPL-1.2

  RegistrationQrCode: the check-in code of an accepted registration as a QR
  code with the code as text beneath it (events-qr-checkin, REQ-EQC-002).

  Registered as the `checkinQr` cell widget (App.vue `cellWidgets`) and used
  on the My registrations list through the manifest:
  `{ "key": "checkinCode", "widget": "checkinQr" }`. A list read only carries
  the masked code, so the cell reads its own registration with
  `_reveal=checkinCode`, which the property's read rule allows for the
  player and game masters only. Rows that are not accepted show nothing.

  Printing the page shows the code at a scannable size beside the event and
  character columns, with the player's name; the app navigation and the
  header are left out of the print.

  @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
-->
<template>
	<figure v-if="drawing" class="registration-qr" data-testid="registration-qr">
		<svg
			class="registration-qr__image"
			:viewBox="viewBox"
			role="img"
			:aria-label="t('larpinq', 'QR code of your check-in code')"
			shape-rendering="crispEdges"
			xmlns="http://www.w3.org/2000/svg">
			<rect
				:x="-margin"
				:y="-margin"
				:width="drawing.size + 2 * margin"
				:height="drawing.size + 2 * margin"
				class="registration-qr__background" />
			<path :d="drawing.path" class="registration-qr__modules" />
		</svg>
		<figcaption class="registration-qr__code" data-testid="registration-qr-code">
			{{ code }}
		</figcaption>
		<p v-if="playerName" class="registration-qr__print-name">
			{{ playerName }}
		</p>
	</figure>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { fetchCheckinCode, qrDrawing } from '../services/checkinCode.js'

/**
 * The quiet zone around the QR code, in modules.
 */
const MARGIN = 2

export default {
	name: 'RegistrationQrCode',

	// The list also passes the cell's `value` and `formatted`: only the masked
	// code, which this cell does not show, so they are not taken as attributes.
	inheritAttrs: false,

	props: {
		/** The whole row: the registration. */
		row: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			code: '',
			margin: MARGIN,
		}
	},

	computed: {
		/**
		 * The registration's id, when it is accepted; '' otherwise.
		 *
		 * @return {string}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
		 */
		acceptedId() {
			if (this.row?.status !== 'accepted') {
				return ''
			}
			return String(this.row?.id ?? this.row?.uuid ?? '')
		},

		/**
		 * The QR drawing of the code, or null while there is none.
		 *
		 * @return {{size: number, path: string}|null}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
		 */
		drawing() {
			return qrDrawing(this.code)
		},

		/**
		 * The SVG view box: the code plus its quiet zone.
		 *
		 * @return {string}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
		 */
		viewBox() {
			const side = (this.drawing?.size ?? 0) + 2 * MARGIN
			return `${-MARGIN} ${-MARGIN} ${side} ${side}`
		},

		/**
		 * The name printed with the code: the signed-in player's.
		 *
		 * @return {string}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-the-player-sees-the-code-as-a-qr-code-req-eqc-002
		 */
		playerName() {
			const user = getCurrentUser()
			return String(user?.displayName || user?.uid || '')
		},
	},

	watch: {
		acceptedId: {
			immediate: true,
			/**
			 * Read the code of the registration when it is (or becomes) accepted.
			 *
			 * @param {string} id The accepted registration's id, or ''.
			 * @return {Promise<void>}
			 */
			async handler(id) {
				this.code = id === '' ? '' : await fetchCheckinCode(id)
			},
		},
	},
}
</script>

<style scoped>
.registration-qr {
	display: inline-flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	margin: 0;
}

.registration-qr__image {
	width: 96px;
	height: 96px;
}

.registration-qr {
	/*
	 * A QR code is read by a camera, not by a person: it needs dark modules on
	 * a light ground in every theme (an inverted code in the dark theme is not
	 * read by every scanner). These two are deliberately not theme variables.
	 */
	--registration-qr-dark: #000;
	--registration-qr-light: #fff;
}

.registration-qr__background {
	fill: var(--registration-qr-light);
}

.registration-qr__modules {
	fill: var(--registration-qr-dark);
}

.registration-qr__code {
	font-family: var(--font-face-monospace, monospace);
	font-size: 0.8em;
	letter-spacing: 0.05em;
	word-break: break-all;
	color: var(--color-main-text);
}

.registration-qr__print-name {
	display: none;
}

@media print {
	.registration-qr {
		break-inside: avoid;
	}

	.registration-qr__image {
		width: 40mm;
		height: 40mm;
	}

	.registration-qr__code {
		color: var(--registration-qr-dark);
		font-size: 10pt;
	}

	.registration-qr__print-name {
		display: block;
		margin: 0;
		color: var(--registration-qr-dark);
		font-weight: bold;
	}
}
</style>

<style>
/*
 * Print only the list itself: the app navigation, the Nextcloud header and the
 * list's toolbar would push the codes off the page.
 */
@media print {
	#header,
	#app-navigation,
	#app-navigation-vue,
	.app-navigation,
	.cn-index-page__header-actions {
		display: none !important;
	}
}
</style>
