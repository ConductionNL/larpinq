<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Apply a build to its character (characters-multiple-builds REQ-CMB-003):
  show what the character gains and loses, then replace the character's
  skills, items and conditions with the build's in one write. The normal
  requirement check runs on that write; a refusal shows here. Opened by the
  BuildDetail header action "Apply to character", game masters only.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Apply to character')"
		data-testid="apply-build-dialog"
		@closing="$emit('close')">
		<p v-if="status === 'loading'">
			{{ t('larpinq', 'Checking the build') }}
		</p>
		<NcNoteCard
			v-else-if="status === 'failed'"
			type="error"
			data-testid="apply-build-load-failed">
			{{ t('larpinq', 'Could not check the build.') }}
		</NcNoteCard>
		<NcNoteCard
			v-else-if="status === 'applied'"
			type="success"
			data-testid="apply-build-done">
			{{
				t('larpinq', '{character} now has this build.', {
					character: result.character.name,
				})
			}}
		</NcNoteCard>
		<template v-else>
			<p>
				{{
					t(
						'larpinq',
						'This replaces the skills, items and conditions of {character}.',
						{ character: result.character.name },
					)
				}}
			</p>
			<p v-if="!changed" data-testid="apply-build-nothing">
				{{ t('larpinq', 'The character already has this build.') }}
			</p>
			<div
				v-for="row in rows"
				:key="row.list"
				:data-testid="`apply-build-${row.list}`">
				<h3>{{ row.label }}</h3>
				<p v-if="row.added.length > 0">
					{{
						t('larpinq', 'Gains: {names}', {
							names: row.added.join(', '),
						})
					}}
				</p>
				<p v-if="row.removed.length > 0">
					{{
						t('larpinq', 'Loses: {names}', {
							names: row.removed.join(', '),
						})
					}}
				</p>
			</div>
			<NcNoteCard
				v-if="!result.report.valid"
				type="warning"
				data-testid="apply-build-invalid">
				{{
					t(
						'larpinq',
						'This build does not fit the rules or the XP of {character} yet.',
						{ character: result.character.name },
					)
				}}
			</NcNoteCard>
			<NcNoteCard
				v-if="refusal"
				type="error"
				data-testid="apply-build-refused">
				{{ refusal }}
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton data-testid="apply-build-cancel" @click="$emit('close')">
				{{
					status === 'applied'
						? t('larpinq', 'Close')
						: t('larpinq', 'Cancel')
				}}
			</NcButton>
			<NcButton
				v-if="status === 'ready'"
				data-testid="apply-build-confirm"
				variant="primary"
				:disabled="busy || !changed"
				@click="apply">
				{{
					busy
						? t('larpinq', 'Applying')
						: t('larpinq', 'Apply to character')
				}}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import {
	applyBuild,
	BUILD_LISTS,
	fetchBuildReport,
	firstId,
	hasChanges,
} from '../services/characterBuilds.js'

export default {
	name: 'ApplyBuildDialog',

	components: { NcButton, NcDialog, NcNoteCard },

	props: {
		/**
		 * The build to apply. Falls back to the route: an `open-modal` header
		 * action forwards its props verbatim.
		 */
		buildId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			status: 'loading',
			result: null,
			busy: false,
			refusal: '',
		}
	},

	computed: {
		/**
		 * The build this dialog applies.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		id() {
			return firstId(this.buildId, this.$route?.params?.id)
		},

		/**
		 * Whether applying changes anything.
		 *
		 * @return {boolean} True when a list gains or loses an entry.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		changed() {
			return hasChanges(this.result?.changes)
		},

		/**
		 * The gains and losses per list, as names; lists that do not change are left out.
		 *
		 * @return {Array<{list: string, label: string, added: Array<string>, removed: Array<string>}>} The rows.
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		rows() {
			const labels = {
				skills: t('larpinq', 'Skills'),
				items: t('larpinq', 'Items'),
				conditions: t('larpinq', 'Conditions'),
			}
			return BUILD_LISTS.map((list) => ({
				list,
				label: labels[list],
				added: (this.result?.changes?.[list]?.added ?? []).map(
					(entry) => entry.name,
				),
				removed: (this.result?.changes?.[list]?.removed ?? []).map(
					(entry) => entry.name,
				),
			})).filter((row) => row.added.length > 0 || row.removed.length > 0)
		},
	},

	/**
	 * Load what applying would change.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	async mounted() {
		this.result = await fetchBuildReport(this.id)
		this.status = this.result ? 'ready' : 'failed'
	},

	methods: {
		t,

		/**
		 * Write the build's lists to the character.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/specs/character-builds/spec.md
		 */
		async apply() {
			this.busy = true
			this.refusal = ''
			const outcome = await applyBuild(
				this.result.character.id,
				this.result.lists,
			)
			this.busy = false
			if (outcome.ok) {
				this.status = 'applied'
				return
			}
			this.refusal =
				outcome.shortfall > 0
					? t(
							'larpinq',
							'The character sheet was not changed: short by {xp} XP.',
							{ xp: outcome.shortfall },
						)
					: t('larpinq', 'The character sheet was not changed: {reason}', {
							reason: outcome.message || t('larpinq', 'unknown error'),
						})
		},
	},
}
</script>
