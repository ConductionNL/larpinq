<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  SPDX-License-Identifier: EUPL-1.2

  TranslatedNameCell — an index column's value with the library's
  "(translated from {language})" badge beside it when OpenRegister served the
  row in another language than it was written in
  (larpinq-adopt-or-abstractions, Phase 3).

  Registered as the `translatedName` cell widget (App.vue `cellWidgets`) and
  used on a list's primary column through the manifest:
  `{ "key": "name", "widget": "translatedName" }`. The badge is the canonical
  `CnTranslatedBadge`, which hides itself for a row without
  `_translationMeta.translatedFrom`: a row in its source language, or one
  that did not come from OpenRegister.
-->
<template>
	<span class="translated-name-cell">
		<span class="translated-name-cell__value">{{ shown }}</span>
		<CnTranslatedBadge :object="row" />
	</span>
</template>

<script>
import { CnTranslatedBadge } from '@conduction/nextcloud-vue'
import { cellContextProps, formattedText } from './cellValue.js'

export default {
	name: 'TranslatedNameCell',

	components: {
		CnTranslatedBadge,
	},

	props: {
		/** The raw cell value. */
		value: {
			type: [Boolean, String, Number, Object, Array],
			default: null,
		},

		...cellContextProps,
	},

	computed: {
		/**
		 * The text shown: the formatted value, else the raw one, else nothing.
		 *
		 * @return {string}
		 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md#requirement-larpinq-lists-must-display-translated-from-lang-badge-when-served-language-differs-from-source
		 */
		shown() {
			const text = formattedText(this.formatted)
			if (text !== null) {
				return text
			}
			if (this.value === null || this.value === undefined || typeof this.value === 'object') {
				return ''
			}
			return String(this.value)
		},
	},
}
</script>

<style scoped>
.translated-name-cell {
	display: inline-flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 6px;
}
</style>
