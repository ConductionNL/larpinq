<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  The language switch on the phone pages (platform-baseline-on-nextcloud,
  REQ-PLB-007; board LrTelefoon). Offers Nederlands and English, starts on the
  account language and saves a choice as the user's Nextcloud language, then
  reloads so the page and the rest of Nextcloud follow.
-->
<template>
	<div class="larpinq-language-switch" data-testid="language-switch">
		<NcSelect
			:modelValue="selectedOption"
			:options="options"
			:inputLabel="t('larpinq', 'Language')"
			:clearable="false"
			:disabled="saving"
			label="label"
			class="larpinq-language-switch__select"
			data-testid="language-switch-select"
			@update:modelValue="onSelect" />
		<p
			v-if="failed"
			class="larpinq-language-switch__error"
			role="alert"
			data-testid="language-switch-error">
			{{ t('larpinq', 'Could not change the language.') }}
		</p>
	</div>
</template>

<script>
import { getLanguage, translate as t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import {
	LANGUAGES,
	offeredLanguage,
	switchLanguage,
} from '../services/userLanguage.js'

export default {
	name: 'LanguageSwitch',

	components: { NcSelect },

	data() {
		const current = offeredLanguage(getLanguage())
		return {
			current,
			selected: current,
			saving: false,
			failed: false,
		}
	},

	computed: {
		/**
		 * The offered languages.
		 *
		 * @return {Array<{id: string, label: string}>}
		 * @spec openspec/changes/platform-baseline-on-nextcloud
		 */
		options() {
			return LANGUAGES
		},

		/**
		 * The option that is selected.
		 *
		 * @return {{id: string, label: string}|null}
		 * @spec openspec/changes/platform-baseline-on-nextcloud
		 */
		selectedOption() {
			return (
				LANGUAGES.find((language) => language.id === this.selected) || null
			)
		},
	},

	methods: {
		t,

		/**
		 * Save the chosen language; on failure go back to the one in use.
		 *
		 * @param {{id: string}|null} option The chosen option.
		 * @return {Promise<void>}
		 * @spec openspec/changes/platform-baseline-on-nextcloud
		 */
		async onSelect(option) {
			if (!option) {
				return
			}
			this.selected = option.id
			this.saving = true
			this.failed = false
			const outcome = await switchLanguage(option.id, this.current)
			this.saving = false
			if (outcome === 'failed') {
				this.failed = true
				this.selected = this.current
			}
		},
	},
}
</script>

<style scoped>
.larpinq-language-switch__select {
	min-width: 10em;
}

.larpinq-language-switch__error {
	color: var(--color-error-text);
	margin: 0;
}
</style>
