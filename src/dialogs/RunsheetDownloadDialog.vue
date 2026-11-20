<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Download an event's run sheet as PDF (pdf-export RUN-020): pick a template,
  then open the download. Opened by the EventDetail header action "Download
  run sheet" (open-modal), which only game masters see; the server refuses
  everyone else.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Download run sheet')"
		data-testid="runsheet-dialog"
		@closing="$emit('close')">
		<p v-if="loading">
			{{ t('larpinq', 'Loading templates') }}
		</p>
		<p
			v-else-if="!available"
			data-testid="runsheet-unavailable"
			role="alert">
			{{
				t(
					'larpinq',
					'PDF export needs the document app. Ask an administrator to enable it.',
				)
			}}
		</p>
		<p
			v-else-if="templates.length === 0"
			data-testid="runsheet-no-templates">
			{{
				t(
					'larpinq',
					'There is no run sheet template yet. Add one in the document app.',
				)
			}}
		</p>
		<NcSelect
			v-else
			v-model="selected"
			data-testid="runsheet-template"
			:inputLabel="t('larpinq', 'Template')"
			:options="templates"
			label="name"
			:clearable="false" />

		<template #actions>
			<NcButton data-testid="runsheet-cancel" @click="$emit('close')">
				{{ t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				data-testid="runsheet-download"
				variant="primary"
				:disabled="!downloadable"
				@click="download">
				{{ t('larpinq', 'Download PDF') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { firstId } from '../services/characterBuilds.js'
import { canDownload, fetchCharacterPdfTemplates } from '../services/characterPdf.js'
import { runsheetUrl } from '../services/eventRunsheet.js'

export default {
	name: 'RunsheetDownloadDialog',

	components: { NcButton, NcDialog, NcSelect },

	props: {
		/**
		 * The event to download. Falls back to the route: an `open-modal`
		 * header action forwards its props verbatim.
		 */
		eventId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			loading: true,
			available: false,
			templates: [],
			selected: null,
		}
	},

	computed: {
		/**
		 * The event this dialog downloads.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/pdf-export/spec.md
		 */
		id() {
			return firstId(this.eventId, this.$route?.params?.id)
		},

		/**
		 * Whether Download PDF may be pressed.
		 *
		 * @return {boolean} True when the list has loaded and a template is chosen.
		 *
		 * @spec openspec/specs/pdf-export/spec.md
		 */
		downloadable() {
			return this.id !== '' && canDownload({
				loading: this.loading,
				templateId: this.selected?.id ?? null,
			})
		},
	},

	/**
	 * Load the templates, then enable the picker.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	async mounted() {
		const state = await fetchCharacterPdfTemplates()
		this.available = state.available
		this.templates = state.templates
		this.loading = false
	},

	methods: {
		t,

		/**
		 * Open the run sheet in a new tab and close the dialog.
		 *
		 * @return {void}
		 *
		 * @spec openspec/specs/pdf-export/spec.md
		 */
		download() {
			if (!this.downloadable) {
				return
			}
			window.open(runsheetUrl(this.id, this.selected.id), '_blank', 'noopener')
			this.$emit('close')
		},
	},
}
</script>
