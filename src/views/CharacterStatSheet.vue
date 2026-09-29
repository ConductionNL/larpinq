<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Stats tab on the character page (characters-stat-sheet-panel): every ability
 with its base, the modifiers that moved it and its final value, and the XP
 line. A row expands to the ordered list of modifiers with their source.

 @spec openspec/specs/character-management/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by the stats endpoint PHPUnit test and tests/e2e/workflows/character-stats.workflow.spec.ts.
-->
<template>
	<div class="character-stat-sheet" data-testid="character-stat-sheet">
		<p v-if="loading">
			{{ t('larpinq', 'Loading stats') }}
		</p>
		<p v-else-if="failed" role="alert">
			{{ t('larpinq', 'Could not load the stats.') }}
		</p>
		<template v-else>
			<p
				v-if="sheet.xp"
				class="character-stat-sheet__xp"
				data-testid="character-stat-xp">
				{{ t('larpinq', 'XP earned') }}: {{ sheet.xp.earned }} ·
				{{ t('larpinq', 'XP spent') }}: {{ sheet.xp.spent }} ·
				{{ t('larpinq', 'XP left') }}: {{ sheet.xp.left }}
			</p>
			<ul class="character-stat-sheet__list">
				<li
					v-for="ability in sheet.abilities"
					:key="ability.id"
					:data-testid="`character-stat-${ability.id}`">
					<button
						type="button"
						class="character-stat-sheet__row"
						:aria-expanded="String(open === ability.id)"
						:disabled="!hasModifiers(ability)"
						@click="toggle(ability.id)">
						<span class="character-stat-sheet__name">{{
							ability.name
						}}</span>
						<span>{{ t('larpinq', 'Base') }} {{ ability.base }}</span>
						<span class="character-stat-sheet__final">{{
							ability.final
						}}</span>
					</button>
					<p
						v-if="!hasModifiers(ability)"
						class="character-stat-sheet__none">
						{{ t('larpinq', 'No modifiers') }}
					</p>
					<ol
						v-else-if="open === ability.id"
						class="character-stat-sheet__modifiers">
						<li
							v-for="(modifier, index) in ability.modifiers"
							:key="index">
							<span
								:class="`character-stat-sheet__change--${changeTone(modifier.change)}`"
								>{{ formatChange(modifier.change) }}</span
							>
							{{ sourceLabel(modifier.source) }}:
							{{ modifier.sourceName || modifier.effectName }}
							<template
								v-if="modifier.effectName && modifier.sourceName"
								>({{ modifier.effectName }})</template
							>
						</li>
					</ol>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	changeTone,
	fetchCharacterStats,
	formatChange,
	hasModifiers,
} from '../services/characterStats.js'

export default {
	name: 'CharacterStatSheet',

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The character UUID. Falls back to the injected object context or the route.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},

	},

	data() {
		return {
			loading: true,
			failed: false,
			sheet: { abilities: [], xp: null },
			open: '',
		}
	},

	computed: {
		/**
		 * The character id from prop, injected context or route.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		characterId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| String(this.$route?.params?.id ?? '')
			)
		},
	},

	/**
	 * Load the sheet.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	async mounted() {
		const sheet = await fetchCharacterStats(this.characterId)
		this.failed = sheet === null
		if (sheet !== null) {
			this.sheet = sheet
		}
		this.loading = false
	},

	methods: {
		t,
		changeTone,
		formatChange,
		hasModifiers,

		/**
		 * Expand or collapse one ability's modifiers.
		 *
		 * @param {string} abilityId The ability id.
		 * @return {void}
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		toggle(abilityId) {
			this.open = this.open === abilityId ? '' : abilityId
		},

		/**
		 * The translated name of a modifier's source type.
		 *
		 * @param {string} source 'skill', 'item', 'condition', 'event' or 'xpAward'.
		 * @return {string} The label.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		sourceLabel(source) {
			const labels = {
				skill: t('larpinq', 'Skill'),
				item: t('larpinq', 'Item'),
				condition: t('larpinq', 'Condition'),
				event: t('larpinq', 'Event'),
				xpAward: t('larpinq', 'XP award'),
			}
			return labels[source] ?? source
		},
	},
}
</script>

<style scoped>
.character-stat-sheet__list {
	list-style: none;
	padding: 0;
}

.character-stat-sheet__row {
	display: grid;
	grid-template-columns: 1fr auto 3em;
	gap: var(--default-grid-baseline, 4px);
	width: 100%;
	text-align: start;
	background: none;
	border: none;
	padding: calc(var(--default-grid-baseline, 4px) * 2);
	color: var(--color-main-text);
}

.character-stat-sheet__final {
	font-weight: bold;
	text-align: end;
}

.character-stat-sheet__none {
	color: var(--color-text-maxcontrast);
	margin-inline-start: calc(var(--default-grid-baseline, 4px) * 2);
}

.character-stat-sheet__change--negative {
	color: var(--color-error-text, var(--color-error));
	font-weight: bold;
}

.character-stat-sheet__change--positive {
	color: var(--color-success-text, var(--color-success));
}
</style>
