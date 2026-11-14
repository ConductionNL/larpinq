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
			<section
				v-if="historyLines.length > 0"
				class="character-stat-sheet__history"
				data-testid="character-stat-xp-history">
				<h3 class="character-stat-sheet__history-title">
					{{ t('larpinq', 'XP history') }}
				</h3>
				<ol class="character-stat-sheet__history-list">
					<li
						v-for="(line, index) in historyLines"
						:key="`${line.skillId}-${line.date}-${index}`">
						<span class="character-stat-sheet__change--positive">{{
							refundText(line)
						}}</span>
						<span class="character-stat-sheet__history-meta">{{
							historyMeta(line)
						}}</span>
					</li>
				</ol>
			</section>
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
							{{ modifierText(modifier) }}
						</li>
					</ol>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { objectTab } from '../mixins/objectTab.js'
import {
	changeTone,
	fetchCharacterStats,
	formatChange,
	formatHistoryDate,
	hasModifiers,
	xpHistory,
} from '../services/characterStats.js'

export default {
	name: 'CharacterStatSheet',

	mixins: [objectTab('characterId')],

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
		 * The XP history lines: the refunds of removed skills, newest first.
		 *
		 * @return {Array<object>} The lines.
		 *
		 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
		 */
		historyLines() {
			return xpHistory(this.sheet)
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

		/**
		 * One refund as the history reads it: "Kruidenkunde removed: 6 XP returned".
		 *
		 * @param {{skillName: string, xp: number}} line The refund line.
		 * @return {string} The text.
		 *
		 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
		 */
		refundText(line) {
			return t('larpinq', '{skill} removed: {xp} XP returned', {
				skill: line.skillName || t('larpinq', 'Unknown skill'),
				xp: Number(line.xp) || 0,
			})
		},

		/**
		 * When and by whom a refund happened: "14-11-2026 · Anna".
		 *
		 * @param {{date: string, user: string}} line The refund line.
		 * @return {string} The text.
		 *
		 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
		 */
		historyMeta(line) {
			return [formatHistoryDate(line.date), line.user].filter(Boolean).join(' · ')
		},

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
				skill: t('larpinq', 'skill'),
				item: t('larpinq', 'item'),
				condition: t('larpinq', 'condition'),
				event: t('larpinq', 'event'),
				xpAward: t('larpinq', 'XP award'),
			}
			return labels[source] ?? source
		},

		/**
		 * One modifier as the sheet reads it: "from Swordsmanship (skill)".
		 * The signed change is printed before it, in its own tone.
		 *
		 * @param {{source: string, sourceName: string, effectName: string}} modifier The modifier.
		 * @return {string} The text.
		 *
		 * @spec openspec/specs/character-management/spec.md
		 */
		modifierText(modifier) {
			const name = modifier.sourceName || modifier.effectName
			if (!modifier.source) {
				return t('larpinq', 'from {name}', { name })
			}
			return t('larpinq', 'from {name} ({type})', {
				name,
				type: this.sourceLabel(modifier.source),
			})
		},
	},
}
</script>

<style scoped>
.character-stat-sheet__history-title {
	font-size: 1em;
	margin: 8px 0 4px;
}

.character-stat-sheet__history-list {
	margin: 0 0 8px;
	padding-inline-start: 1.2em;
}

.character-stat-sheet__history-meta {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

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
