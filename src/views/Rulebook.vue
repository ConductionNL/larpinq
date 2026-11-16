<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Rulebook: the rules of one world as a book (rules-rulebook-and-spell-lists
  D3). The rules chapters come first, in order, rendered from markdown with
  the lore pages' renderer; then a reference of abilities, skill lists with
  their skills and costs, items and conditions. Objects hidden from the
  rulebook are left out. Printing starts each chapter on its own page.

  @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
-->
<template>
	<article class="larpinq-rulebook" data-testid="rulebook">
		<header class="larpinq-rulebook__header">
			<h1>{{ t('larpinq', 'Rulebook') }}</h1>
			<NcButton class="larpinq-rulebook__print" @click="print">
				{{ t('larpinq', 'Print') }}
			</NcButton>
		</header>

		<NcLoadingIcon v-if="loading" :size="44" />
		<NcNoteCard v-else-if="error" type="error">
			{{ t('larpinq', 'The rulebook could not be read.') }}
		</NcNoteCard>
		<NcEmptyContent
			v-else-if="empty"
			:name="t('larpinq', 'No rules yet')"
			:description="t('larpinq', 'Add lore pages of the category rules, or skills, to fill the rulebook.')" />
		<template v-else>
			<section
				v-for="chapter in book.chapters"
				:key="chapter.id"
				class="larpinq-rulebook__chapter"
				data-testid="rulebook-chapter">
				<h2>{{ chapter.title }}</h2>
				<!-- eslint-disable-next-line vue/no-v-html -- cnRenderMarkdown sanitises with DOMPurify -->
				<div class="larpinq-rulebook__body" v-html="render(chapter.body)" />
			</section>

			<section class="larpinq-rulebook__chapter larpinq-rulebook__reference" data-testid="rulebook-reference">
				<h2>{{ t('larpinq', 'Rules reference') }}</h2>

				<template v-if="book.abilities.length">
					<h3>{{ t('larpinq', 'Abilities') }}</h3>
					<dl>
						<template v-for="ability in book.abilities" :key="ability.id">
							<dt>{{ ability.name }}</dt>
							<dd>{{ ability.description }}</dd>
						</template>
					</dl>
				</template>

				<div
					v-for="list in book.lists"
					:key="list.id || 'general'"
					class="larpinq-rulebook__list"
					data-testid="rulebook-list">
					<h3>{{ list.name }}</h3>
					<p class="larpinq-rulebook__pool">
						{{ kindLabel(list.kind) }} · {{ t('larpinq', 'Paid from {pool}', { pool: list.pool }) }}
					</p>
					<p v-if="list.description">
						{{ list.description }}
					</p>
					<ul>
						<li v-for="skill in list.skills" :key="skill.id" data-testid="rulebook-skill">
							<strong>{{ skillLine(skill) }}</strong>
							<span v-if="skill.requires.length" class="larpinq-rulebook__requires">
								{{ t('larpinq', 'Requires {skills}', { skills: skill.requires.join(', ') }) }}
							</span>
							<span v-if="skill.effect">{{ skill.effect }}</span>
						</li>
					</ul>
				</div>

				<template v-if="book.items.length">
					<h3>{{ t('larpinq', 'Items') }}</h3>
					<dl>
						<template v-for="item in book.items" :key="item.id">
							<dt>{{ item.name }}</dt>
							<dd>{{ item.effect || item.description }}</dd>
						</template>
					</dl>
				</template>

				<template v-if="book.conditions.length">
					<h3>{{ t('larpinq', 'Conditions') }}</h3>
					<dl>
						<template v-for="condition in book.conditions" :key="condition.id">
							<dt>{{ condition.name }}</dt>
							<dd>{{ condition.effect || condition.description }}</dd>
						</template>
					</dl>
				</template>
			</section>
		</template>
	</article>
</template>

<script>
import { cnRenderMarkdown } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { activeWorld } from '../services/activeWorld.js'
import { buildRulebook, fetchRulebookData } from '../services/rulebook.js'

export default {
	name: 'RulebookPage',
	components: { NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard },

	props: {
		/** The reads; the specs pass a fake. */
		load: {
			type: Function,
			default: fetchRulebookData,
		},
	},

	data() {
		return {
			loading: true,
			error: false,
			book: { chapters: [], abilities: [], lists: [], items: [], conditions: [] },
		}
	},

	computed: {
		/**
		 * The world from the route, else the active world, else '' (every world).
		 *
		 * @return {string}
		 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
		 */
		world() {
			return String(this.$route?.params?.world || activeWorld.id || '')
		},

		/**
		 * Whether there is nothing to read.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
		 */
		empty() {
			return this.book.chapters.length === 0 && this.book.lists.length === 0 && this.book.abilities.length === 0
		},
	},

	watch: {
		world: {
			immediate: true,
			/**
			 * Read the rulebook of the world in view.
			 *
			 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
			 */
			handler() {
				this.read()
			},
		},
	},

	methods: {
		t,

		/**
		 * Read and build the rulebook.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
		 */
		async read() {
			this.loading = true
			this.error = false
			try {
				this.book = buildRulebook(await this.load(this.world), t('larpinq', 'XP'), t('larpinq', 'General skills'))
			} catch {
				this.error = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * @param {string} markdown A chapter body.
		 * @return {string} Sanitised HTML.
		 */
		render(markdown) {
			return cnRenderMarkdown(markdown || '')
		},

		/**
		 * A skill with its cost, as "Firebolt, 3 mana".
		 *
		 * @param {object} skill The reference entry.
		 * @return {string}
		 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
		 */
		skillLine(skill) {
			if (skill.costs.length === 0) return skill.name
			return skill.name + ', ' + skill.costs.map((cost) => `${cost.amount} ${cost.ability.toLowerCase()}`).join(', ')
		},

		/**
		 * The heading of a skill list kind.
		 *
		 * @param {string} kind The list kind.
		 * @return {string}
		 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
		 */
		kindLabel(kind) {
			return { skills: t('larpinq', 'Skills'), spells: t('larpinq', 'Spells'), powers: t('larpinq', 'Powers') }[kind] || kind
		},

		/**
		 * Print the rulebook, one chapter per page.
		 *
		 * @return {void}
		 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-players-read-the-rulebook-of-their-world-req-rrs-003
		 */
		print() {
			window.print()
		},
	},
}
</script>

<style scoped>
.larpinq-rulebook {
	max-width: 840px;
	margin: 0 auto;
	padding: 16px;
	color: var(--color-main-text);
}

.larpinq-rulebook__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.larpinq-rulebook__chapter {
	margin-block: 24px;
}

.larpinq-rulebook__pool,
.larpinq-rulebook__requires {
	color: var(--color-text-maxcontrast);
}

.larpinq-rulebook__list li {
	display: flex;
	flex-direction: column;
	gap: 2px;
	margin-block: 8px;
}

@media print {
	.larpinq-rulebook__print {
		display: none;
	}

	.larpinq-rulebook {
		max-width: none;
		padding: 0;
	}

	.larpinq-rulebook__chapter {
		break-before: page;
	}

	.larpinq-rulebook__chapter:first-of-type {
		break-before: auto;
	}

	.larpinq-rulebook__list {
		break-inside: avoid;
	}
}
</style>
