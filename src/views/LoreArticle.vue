<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  LoreArticle: the read page of one lore page (worlds-lore-pages), the
  "wiki-aware wrapper" CnWikiPage expects. CnWikiPage renders an article and
  a tree but does not fetch; this component loads both through
  src/services/loreArticle.js and follows the tree to other pages.

  @spec openspec/specs/world-lore/spec.md
-->
<template>
	<div class="larpinq-lore-article">
		<NcLoadingIcon v-if="loading" :size="44" />
		<CnWikiPage
			v-else
			:article="article"
			:tree="tree"
			register="larpinq"
			schema="larping_lore_page"
			sidebarSchema="larping_lore_page"
			contentField="body"
			titleField="title"
			@treeClick="openPage" />
	</div>
</template>

<script>
import { CnWikiPage } from '@conduction/nextcloud-vue'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { fetchLoreArticle, fetchLoreTree } from '../services/loreArticle.js'

export default {
	name: 'LoreArticle',
	components: { CnWikiPage, NcLoadingIcon },
	data() {
		return {
			article: null,
			tree: [],
			loading: true,
		}
	},

	watch: {
		'$route.params.id': {
			immediate: true,
			/**
			 * Load the page named in the route, and its world's tree.
			 *
			 * @spec openspec/specs/world-lore/spec.md
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		/**
		 * Load the article and the tree of its world.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/world-lore/spec.md
		 */
		async load() {
			const id = this.$route?.params?.id
			this.loading = true
			this.article = id ? await fetchLoreArticle(id) : null
			this.tree = this.article
				? await fetchLoreTree(this.article.setting || null)
				: []
			this.loading = false
		},

		/**
		 * Open another page from the sidebar.
		 *
		 * @param {{id: string}} node The tree node.
		 * @spec openspec/specs/world-lore/spec.md
		 */
		openPage(node) {
			if (node?.id && node.id !== this.$route?.params?.id) {
				this.$router.push({ name: 'LoreArticle', params: { id: node.id } })
			}
		},
	},
}
</script>

<style scoped>
.larpinq-lore-article {
	padding: calc(var(--default-grid-baseline) * 4);
}
</style>
