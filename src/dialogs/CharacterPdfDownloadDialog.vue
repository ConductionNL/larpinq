<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Download a character sheet as PDF: pick a template, then open the download.
  Opened by the CharacterDetail header action "Download as PDF" (open-modal).
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Download as PDF')"
		data-testid="character-pdf-dialog"
		@closing="$emit('close')">
		<p v-if="loading">
			{{ t('larpinq', 'Loading templates') }}
		</p>
		<p
			v-else-if="!available"
			data-testid="character-pdf-unavailable"
			role="alert">
			{{
				t(
					'larpinq',
					'PDF export needs the document app. Ask an administrator to enable it.',
				)
			}}
		</p>
		<p v-else-if="forbidden" data-testid="character-pdf-forbidden" role="alert">
			{{
				t(
					'larpinq',
					'Only administrators can download character sheets for now.',
				)
			}}
		</p>
		<p
			v-else-if="templates.length === 0"
			data-testid="character-pdf-no-templates">
			{{
				t(
					'larpinq',
					'There is no character sheet template yet. Add one in the document app.',
				)
			}}
		</p>
		<NcSelect
			v-else
			v-model="selected"
			data-testid="character-pdf-template"
			:inputLabel="t('larpinq', 'Template')"
			:options="templates"
			label="name"
			:clearable="false" />

		<template #actions>
			<NcButton data-testid="character-pdf-cancel" @click="$emit('close')">
				{{ t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				data-testid="character-pdf-download"
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
import {
	canDownload,
	characterPdfUrl,
	fetchCharacterPdfTemplates,
} from '../services/characterPdf.js'

export default {
	name: 'CharacterPdfDownloadDialog',

	components: { NcButton, NcDialog, NcSelect },

	props: {
		/**
		 * The character to download. Falls back to the route: an `open-modal`
		 * header action forwards its props verbatim, so `@objectId` would
		 * arrive as that literal string.
		 */
		characterId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			loading: true,
			available: false,
			forbidden: false,
			templates: [],
			selected: null,
		}
	},

	computed: {
		/**
		 * The character this dialog downloads.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/pdf-export/spec.md
		 */
		id() {
			const fromProp = this.characterId.startsWith('@') ? '' : this.characterId
			return fromProp || String(this.$route?.params?.id ?? '')
		},

		/**
		 * Whether Download PDF may be pressed (PDF-044).
		 *
		 * @return {boolean} True when a template is chosen and the list has loaded.
		 *
		 * @spec openspec/specs/pdf-export/spec.md
		 */
		downloadable() {
			return canDownload({
				loading: this.loading,
				templateId: this.selected?.id ?? null,
			})
		},
	},

	async mounted() {
		const state = await fetchCharacterPdfTemplates()
		this.available = state.available
		this.forbidden = state.forbidden
		this.templates = state.templates
		this.loading = false
	},

	methods: {
		t,

		/**
		 * Open the PDF in a new tab and close the dialog.
		 *
		 * @return {void}
		 *
		 * @spec openspec/specs/pdf-export/spec.md
		 */
		download() {
			if (!this.downloadable) {
				return
			}
			window.open(
				characterPdfUrl(this.id, this.selected.id),
				'_blank',
				'noopener',
			)
			this.$emit('close')
		},
	},
}
</script>
