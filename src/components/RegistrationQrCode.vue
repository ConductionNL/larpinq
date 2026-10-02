<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 The check-in code of a registration as a QR code with the code as text
 beneath it (events-qr-checkin). Shown to the registration's player and to
 game masters; printing the page prints only the code, the name, the
 character and the event.

 @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by
 tests/vitest/checkinCode.spec.js and the qr-checkin e2e workflow.
-->
<template>
	<section class="registration-qr" data-testid="registration-qr">
		<p v-if="loading" class="registration-qr__note">
			{{ t('larpinq', 'Loading your check-in code…') }}
		</p>
		<p v-else-if="failed" class="registration-qr__note">
			{{ t('larpinq', 'The check-in code could not be loaded.') }}
		</p>
		<p
			v-else-if="!code"
			class="registration-qr__note"
			data-testid="registration-qr-none">
			{{
				t(
					'larpinq',
					'You get a check-in code when your registration is accepted.',
				)
			}}
		</p>
		<div v-else class="registration-qr__ticket">
			<!-- The SVG is generated locally by the qrcode library from a code that matches ^[A-Z2-7]{26}$. -->
			<!-- eslint-disable-next-line vue/no-v-html -->
			<div
				class="registration-qr__image"
				role="img"
				:aria-label="t('larpinq', 'QR code of your check-in code')"
				v-html="svg" />
			<p class="registration-qr__code" data-testid="registration-qr-code">
				{{ code }}
			</p>
			<p v-if="name" class="registration-qr__line">
				{{ name }}<template v-if="character">, {{ character }}</template>
			</p>
			<p v-if="event" class="registration-qr__line">
				{{ event }}
			</p>
			<NcButton
				variant="secondary"
				class="registration-qr__print"
				@click="print">
				{{ t('larpinq', 'Print') }}
			</NcButton>
		</div>
	</section>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { fetchCheckinCode, qrSvg } from '../services/checkinCode.js'

export default {
	name: 'RegistrationQrCode',

	components: { NcButton },

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The registration UUID. Falls back to the injected object context or the route.
		 *
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},

		/**
		 * Manifest config passthrough.
		 *
		 * @spec exclude Config passthrough, no product logic.
		 */
		config: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			loading: true,
			failed: false,
			code: '',
			svg: '',
			name: '',
			character: '',
			event: '',
		}
	},

	computed: {
		/**
		 * The registration id from prop, injected context or route.
		 *
		 * @return {string}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		registrationId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| this.$route?.params?.id
				|| ''
			)
		},
	},

	async mounted() {
		await this.load()
	},

	methods: {
		/**
		 * Read the code and draw it.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		async load() {
			const result = await fetchCheckinCode(this.registrationId)
			this.failed = result.state !== 'ok'
			if (!this.failed && result.status === 'accepted') {
				this.code = result.code
				this.name = result.name
				this.character = result.character
				this.event = result.event
				this.svg = await qrSvg(result.code)
			}
			this.loading = false
		},

		/**
		 * Print the page; the print style keeps only the ticket.
		 *
		 * @return {void}
		 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
		 */
		print() {
			window.print()
		},
	},
}
</script>

<style scoped>
.registration-qr__ticket {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
}

.registration-qr__image {
	width: 220px;
	max-width: 100%;
	background: var(--color-main-background);
}

.registration-qr__code {
	font-family: var(--font-face-monospace, monospace);
	letter-spacing: 0.1em;
	word-break: break-all;
}

.registration-qr__note {
	color: var(--color-text-maxcontrast);
}

@media print {
	.registration-qr__print {
		display: none;
	}

	.registration-qr__image {
		width: 6cm;
	}
}
</style>

<style>
@media print {
	body * {
		visibility: hidden;
	}

	.registration-qr__ticket,
	.registration-qr__ticket * {
		visibility: visible;
	}

	.registration-qr__ticket {
		position: absolute;
		inset-block-start: 0;
		inset-inline-start: 0;
	}
}
</style>
