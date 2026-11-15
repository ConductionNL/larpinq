/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Lore pages (worlds-lore-pages): load one page and the sidebar tree of its
 * world for the LoreArticle read page.
 *
 * Both requests go to OpenRegister's objects API, which applies the
 * lorePage read rule in the query: a player gets a page only when it is for
 * players and revealed, and the tree holds only such pages. This file never
 * filters by visibility itself, so it cannot disagree with that rule.
 *
 * @spec openspec/specs/world-lore/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const LORE_API = '/apps/openregister/api/objects/larpinq/larping_lore_page'

/**
 * The GET options every lore request uses.
 *
 * @return {object} Fetch options.
 */
function requestOptions() {
	return {
		headers: { requesttoken: getRequestToken(), Accept: 'application/json' },
	}
}

/**
 * The id of a lore page row, whichever shape OpenRegister sent.
 *
 * @param {object} page The row.
 * @return {string} The id, or an empty string.
 *
 * @spec openspec/specs/world-lore/spec.md
 */
export function lorePageId(page) {
	return String(page?.id || page?.['@self']?.id || '')
}

/**
 * The tree node of one lore page.
 *
 * @param {object} page The row.
 * @param {string} id Its id.
 * @return {object} The node, with its parent id and an empty child list.
 */
function loreNode(page, id) {
	return {
		id,
		title: page.title || '',
		order: Number(page.order) || 0,
		parent: page.parent || null,
		children: [],
	}
}

/**
 * The nodes of the pages that carry an id, by id; a later page with the same
 * id replaces the earlier one.
 *
 * @param {Array<object>|null|undefined} pages The rows.
 * @return {Map<string, object>} The nodes.
 */
function loreNodesById(pages) {
	const nodes = new Map()
	for (const page of pages || []) {
		const id = lorePageId(page)
		if (id !== '') {
			nodes.set(id, loreNode(page, id))
		}
	}
	return nodes
}

/**
 * Whether following a node's parents ends at a top-level page or a parent the
 * reader cannot see, rather than in a cycle.
 *
 * @param {Map<string, object>} nodes The nodes by id.
 * @param {object} node The node.
 * @return {boolean} True when no cycle is met.
 */
function reachesRoot(nodes, node) {
	const seen = new Set([node.id])
	let parent = node.parent
	while (parent && nodes.has(parent)) {
		if (seen.has(parent)) {
			return false
		}
		seen.add(parent)
		parent = nodes.get(parent).parent
	}
	return true
}

/**
 * Hang every node under its visible parent and return the top-level nodes.
 *
 * @param {Map<string, object>} nodes The nodes by id.
 * @return {Array<object>} The top-level nodes, in insertion order.
 */
function attachLoreNodes(nodes) {
	const roots = []
	for (const node of nodes.values()) {
		if (node.parent && nodes.has(node.parent) && reachesRoot(nodes, node)) {
			nodes.get(node.parent).children.push(node)
		} else {
			roots.push(node)
		}
	}
	return roots
}

/**
 * Sort a node list and every child list below it by `order`, then title.
 *
 * @param {Array<object>} list The nodes (sorted in place).
 * @return {Array<object>} The same list.
 */
function sortLoreNodes(list) {
	list.sort((a, b) => a.order - b.order || a.title.localeCompare(b.title))
	for (const node of list) {
		sortLoreNodes(node.children)
	}
	return list
}

/**
 * A node as the tree shows it: id, title and children.
 *
 * @param {object} node The node.
 * @return {{id: string, title: string, children: Array}} The tree entry.
 */
function treeEntry(node) {
	return {
		id: node.id,
		title: node.title,
		children: node.children.map(treeEntry),
	}
}

/**
 * Nest pages under their parent, ordered by `order` then title. A page whose
 * parent the reader cannot see, or that sits in a cycle, goes to the top.
 *
 * @param {Array<object>} pages The pages the reader may see.
 * @return {Array<{id: string, title: string, children: Array}>} The tree.
 *
 * @spec openspec/specs/world-lore/spec.md
 */
export function buildLoreTree(pages) {
	return sortLoreNodes(attachLoreNodes(loreNodesById(pages))).map(treeEntry)
}

/**
 * Load one lore page.
 *
 * @param {string} id The page id.
 * @return {Promise<object|null>} The page, or null when it is not there or not readable.
 *
 * @spec openspec/specs/world-lore/spec.md
 */
export async function fetchLoreArticle(id) {
	const response = await fetch(
		generateUrl(`${LORE_API}/${encodeURIComponent(id)}`),
		requestOptions(),
	)
	if (!response.ok) {
		return null
	}
	return response.json()
}

/**
 * Load the sidebar tree: the readable pages of one world.
 *
 * @param {string|null} settingId The world id; without one, every readable page.
 * @return {Promise<Array<object>>} The tree, empty when the list fails.
 *
 * @spec openspec/specs/world-lore/spec.md
 */
export async function fetchLoreTree(settingId) {
	const query = new URLSearchParams({ _limit: '500' })
	if (settingId) {
		query.set('setting', settingId)
	}
	const response = await fetch(
		`${generateUrl(LORE_API)}?${query.toString()}`,
		requestOptions(),
	)
	if (!response.ok) {
		return []
	}
	const body = await response.json()
	return buildLoreTree(Array.isArray(body?.results) ? body.results : [])
}
