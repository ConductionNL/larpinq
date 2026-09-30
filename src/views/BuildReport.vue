<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Check tab on the build page (characters-multiple-builds): the skill
 requirement check and the XP budget of the character with this build's
 skills, items and conditions, and the stats that build gives. Runs each time
 the tab opens, against the rules as they are now. Nothing is written.

 @spec openspec/specs/character-builds/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by the report endpoint PHPUnit test and tests/e2e/workflows/character-builds.workflow.spec.ts.
-->
<template>
	<div class="build-report" data-testid="build-report">
		<p v-if="loading">
			{{ t('larpinq', 'Checking the build') }}
		</p>
		<p v-else-if="!result" role="alert">
			{{ t('larpinq', 'Could not check the build.') }}
		</p>
		<template v-else>
			<NcNoteCard
				:type="result.report.valid ? 'success' : 'warning'"
				data-testid="build-report-verdict">
				{{
					result.report.valid
						? t('larpinq', 'This build fits the rules and the XP of {character}.', { character: result.character.name })
						: t('larpinq', 'This build does not fit the rules or the XP of {character} yet.', { character: result.character.name })
				}}
			</NcNoteCard>
			<p
				v-if="result.report.budget.shortfall > 0"
				class="build-report__short"
				data-testid="build-report-short">
				{{ t('larpinq', 'Short by {xp} XP', { xp: result.report.budget.shortfall }) }}
			</p>
			<p
				v-if="result.stats.xp"
				data-testid="build-report-xp">
				{{ t('larpinq', 'XP earned') }}: {{ result.stats.xp.earned }} ·
				{{ t('larpinq', 'XP spent') }}: {{ result.stats.xp.spent }} ·
				{{ t('larpinq', 'XP left') }}: {{ result.stats.xp.left }}
			</p>
			<template v-if="unmet.length > 0">
				<h3>{{ t('larpinq', 'Requirements not met') }}</h3>
				<ul data-testid="build-report-unmet">
					<li v-for="(entry, index) in unmet" :key="index">
						{{ requirementText(entry) }}
					</li>
				</ul>
			</template>
			<h3>{{ t('larpinq', 'Stats with this build') }}</h3>
			<ul class="build-report__stats" data-testid="build-report-stats">
				<li v-for="ability in result.stats.abilities" :key="ability.id">
					<span>{{ ability.name }}</span>
					<span class="build-report__final">{{ ability.final }}</span>
				</li>
			</ul>
			<NcButton data-testid="build-report-recheck" @click="load">
				{{ t('larpinq', 'Check again') }}
			</NcButton>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { fetchBuildReport, firstId } from '../services/characterBuilds.js'

export default {
	name: 'BuildReport',

	components: { NcButton, NcNoteCard },

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The build UUID. Falls back to the injected object context or the route.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			loading: true,
			result: null,
		}
	},

	computed: {
		/**
		 * The build id from prop, injected context or route.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		buildId() {
			return firstId(this.objectId, this.cnObjectContext?.objectId, this.$route?.params?.id)
		},

		/**
		 * The requirements the build does not meet.
		 *
		 * @return {Array<object>} The entries.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		unmet() {
			return (this.result?.report?.requirements ?? []).filter(
				(entry) => entry.status === 'unmet' || entry.status === 'unresolvable',
			)
		},
	},

	/**
	 * Check the build when the tab opens.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	async mounted() {
		await this.load()
	},

	methods: {
		t,

		/**
		 * Run the check.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		async load() {
			this.loading = true
			this.result = await fetchBuildReport(this.buildId)
			this.loading = false
		},

		/**
		 * One unmet requirement as a line.
		 *
		 * @param {{type: string, targetName: string, required: number, current: number}} entry The entry.
		 * @return {string} The text.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		requirementText(entry) {
			if (entry.type === 'requiredStat') {
				return t('larpinq', '{name} must be {required}, the build gives {current}', {
					name: entry.targetName,
					required: entry.required,
					current: entry.current,
				})
			}
			return t('larpinq', 'Needs {name}', { name: entry.targetName || entry.target || entry.skill })
		},
	},
}
</script>

<style scoped>
.build-report__short {
	color: var(--color-error-text, var(--color-error));
	font-weight: bold;
}

.build-report__stats {
	list-style: none;
	padding: 0;
}

.build-report__stats li {
	display: flex;
	justify-content: space-between;
	padding: var(--default-grid-baseline, 4px) 0;
}

.build-report__final {
	font-weight: bold;
}
</style>
