<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Skill-tree visualization (skill-tree-visualization).

 A frontend-only, read-only page that renders the skill prerequisite graph:
 nodes are OpenRegister `skill` objects, directed edges are `requiredSkills`
 prerequisites. When a character is selected each node is coloured
 owned / available / locked using the SERVER-AUTHORITATIVE
 CharactersController::requirementReport result — the page never re-derives
 prerequisites or the XP budget client-side (ADR-022), so the tree and the
 write-time veto can never disagree. Selecting a node surfaces its full
 requirement set resolved to names. Nodes scope to the active setting.

 Cycle / self-reference tolerant (visited-set tier assignment — never recurses
 unbounded). Degrades without erroring: empty state when no skills exist; an
 uncoloured tree when OpenRegister or the requirement report is unavailable.

 The graph is drawn from @conduction/nextcloud-vue primitives + NcSelect
 (ADR-012) — a bespoke graph/charting dependency is avoided (shared-deps rule).
 Directed edges are rendered as each node's named "requires" prerequisite
 chips (the incoming edge), so the dependency direction reads prereq → dependent
 without a bespoke layout engine.

 @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
 @visual exclude Read-only graph view; the render + owned/available/locked
 colouring is covered by the SkillTree component unit tests + the gate-19
 e2e-exclude scenarios on the spec, pending a live-servable frontend bundle.
-->
<template>
	<div class="skill-tree" data-testid="skill-tree">
		<div class="skill-tree__controls">
			<!--
			  `@input` is NOT an event NcSelect emits under @nextcloud/vue v9 —
			  its emits list is open / close / update:modelValue / search* /
			  option:*. The Vue-2 spelling silently never fired, so picking a
			  character would never have fetched the requirement report and the
			  tree would have stayed uncoloured with no error. The compiler
			  merges this listener with the v-model handler into an array and
			  runs the v-model assignment first, so the handler sees the NEW
			  selection.
			-->
			<NcSelect
				v-model="selectedCharacter"
				:options="characterOptions"
				:input-label="t('larpingapp', 'Character')"
				:placeholder="t('larpingapp', 'No character (uncoloured)')"
				label="label"
				data-testid="skill-tree-character"
				@update:modelValue="onCharacterChange" />
			<!--
			  "World", not "Setting". This selector scopes the tree to a LARP
			  game world. `Setting` reads as a UI preference to anyone who is
			  not already a LARPer, which is exactly how all 35 non-Dutch
			  locales translated it (Einstellung, Paramètre, Configuración).
			  The English source string carries the domain word now; the
			  underlying OpenRegister property is still `skill.setting`.
			-->
			<NcSelect
				v-model="selectedSetting"
				:options="settingOptions"
				:input-label="t('larpingapp', 'World')"
				:placeholder="t('larpingapp', 'All worlds')"
				label="label"
				data-testid="skill-tree-setting" />
		</div>

		<div class="skill-tree__legend" aria-hidden="false">
			<span class="skill-tree__legend-item"><span class="skill-tree__dot skill-tree__dot--owned" /> {{ t('larpingapp', 'Owned') }}</span>
			<span class="skill-tree__legend-item"><span class="skill-tree__dot skill-tree__dot--available" /> {{ t('larpingapp', 'Available') }}</span>
			<span class="skill-tree__legend-item"><span class="skill-tree__dot skill-tree__dot--locked" /> {{ t('larpingapp', 'Locked') }}</span>
		</div>

		<NcEmptyContent
			v-if="!loading && nodes.length === 0"
			:name="t('larpingapp', 'No skills to show')"
			:description="t('larpingapp', 'No skills exist yet for the selected world.')"
			data-testid="skill-tree-empty">
			<template #icon>
				<CnIcon name="Sitemap" :size="48" />
			</template>
		</NcEmptyContent>

		<div v-else class="skill-tree__body">
			<div class="skill-tree__graph" data-testid="skill-tree-graph">
				<div v-for="(tier, tierIndex) in tiers" :key="tierIndex" class="skill-tree__tier">
					<button
						v-for="node in tier"
						:key="node.id"
						type="button"
						class="skill-tree__node"
						:class="`skill-tree__node--${node.state}`"
						:aria-pressed="node.id === selectedNodeId"
						:data-testid="`skill-tree-node-${node.id}`"
						:data-state="node.state"
						@click="selectNode(node)">
						<span class="skill-tree__node-name">{{ node.name }}</span>
						<span class="skill-tree__node-requires">
							{{ node.requiredSkills.length
								? t('larpingapp', 'Requires: {list}', { list: node.requiredSkills.map(s => s.name).join(', ') })
								: t('larpingapp', 'No prerequisites') }}
						</span>
					</button>
				</div>
			</div>

			<div v-if="selectedNode" class="skill-tree__detail" data-testid="skill-tree-detail">
				<h3>{{ selectedNode.name }}</h3>
				<CnStatusBadge :label="stateLabel(selectedNode.state)" :variant="stateVariant(selectedNode.state)" />

				<dl class="skill-tree__reqs">
					<template v-if="selectedNode.requiredSkills.length">
						<dt>{{ t('larpingapp', 'Required skills') }}</dt>
						<dd>{{ selectedNode.requiredSkills.map(s => s.name).join(', ') }}</dd>
					</template>
					<template v-if="selectedNode.requiredStats.length">
						<dt>{{ t('larpingapp', 'Required abilities') }}</dt>
						<dd>{{ selectedNode.requiredStats.map(s => s.name).join(', ') }} {{ t('larpingapp', '(score ≥ {n})', { n: selectedNode.requiredScore }) }}</dd>
					</template>
					<template v-if="selectedNode.requiredConditions.length">
						<dt>{{ t('larpingapp', 'Required conditions') }}</dt>
						<dd>{{ selectedNode.requiredConditions.map(s => s.name).join(', ') }}</dd>
					</template>
					<template v-if="selectedNode.requiredEffects.length">
						<dt>{{ t('larpingapp', 'Required effects') }}</dt>
						<dd>{{ selectedNode.requiredEffects.map(s => s.name).join(', ') }}</dd>
					</template>
					<template v-if="!hasAnyRequirement(selectedNode)">
						<dt>{{ t('larpingapp', 'Prerequisites') }}</dt>
						<dd>{{ t('larpingapp', 'None — this is a root skill.') }}</dd>
					</template>
				</dl>
			</div>
		</div>
	</div>
</template>

<script>
import { CnIcon, CnStatusBadge, useObjectStore } from '@conduction/nextcloud-vue'
// @nextcloud/vue v9 ships an `exports` map; the v8 `dist/Components/*.js`
// deep paths are no longer exported and throw ERR_PACKAGE_PATH_NOT_EXPORTED.
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import { generateUrl } from '@nextcloud/router'
import { idList, indexNames, computeStateBySkill, buildNodes, computeTiers } from './skillTreeGraph.js'

const STATE_META = {
	owned: { label: 'Owned', variant: 'success' },
	available: { label: 'Available', variant: 'primary' },
	locked: { label: 'Locked', variant: 'error' },
	unknown: { label: 'Unknown', variant: 'default' },
}

export default {
	name: 'SkillTree',

	components: {
		CnIcon,
		CnStatusBadge,
		NcSelect,
		NcEmptyContent,
	},

	data() {
		return {
			skills: [],
			characters: [],
			abilityNames: {},
			conditionNames: {},
			effectNames: {},
			selectedCharacter: null,
			selectedSetting: null,
			selectedNodeId: null,
			report: null,
			loading: false,
			// Live-updates handles for the or-collection-{register}-{schema}
			// subscriptions of the skill + character collections
			// (adopt-live-updates-ui). Managed by syncLiveSubscriptions();
			// liveEpoch invalidates in-flight subscribes after a release
			// (destroy) so awaited handles are dropped instead of leaked.
			liveHandles: [],
			liveActive: false,
			liveEpoch: 0,
			liveUnwatch: null,
		}
	},

	computed: {
		/**
		 * The owned skill id set for the selected character.
		 *
		 * @return {Set<string>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		ownedSkillIds() {
			const character = this.characters.find((c) => String(c.id) === this.selectedCharacter?.value)
			return new Set(idList(character?.skills))
		},

		/**
		 * Per-skill availability state derived SOLELY from the server report.
		 * Grouped requirement entries per skill: owned → membership; all
		 * passed/overridden + budget ok → available; any unmet/unresolvable →
		 * locked; skills the report does not classify stay uncoloured (unknown).
		 * No prerequisite or XP-budget logic is re-implemented here.
		 *
		 * @return {Record<string, string>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		stateBySkill() {
			return computeStateBySkill({
				skills: this.skills,
				ownedIds: this.selectedCharacter ? this.ownedSkillIds : null,
				report: this.report,
			})
		},

		/**
		 * The setting-scoped node set (nodes = skills, edges = requiredSkills).
		 * When no setting is active every skill is shown.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		nodes() {
			return buildNodes({
				skills: this.skills,
				activeSetting: this.selectedSetting?.value || null,
				names: { ability: this.abilityNames, condition: this.conditionNames, effect: this.effectNames },
				stateMap: this.stateBySkill,
			})
		},

		/**
		 * Nodes arranged into dependency tiers (roots first). Cycle-tolerant: a
		 * visited set caps the passes so a self-reference / cycle can never spin
		 * unbounded — remaining cyclic nodes drop into a final tier.
		 *
		 * @return {Array<Array<object>>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		tiers() {
			return computeTiers(this.nodes)
		},

		/**
		 * Options for the character selector.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		characterOptions() {
			return this.characters.map((c) => ({ value: String(c.id), label: c.name || String(c.id) }))
		},

		/**
		 * Options for the setting selector (distinct settings on the skills).
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		settingOptions() {
			const seen = new Set()
			const options = []
			for (const s of this.skills) {
				const id = s.setting ? String(s.setting) : ''
				if (id && !seen.has(id)) {
					seen.add(id)
					options.push({ value: id, label: id })
				}
			}
			return options
		},

		/**
		 * The currently selected node object.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		selectedNode() {
			return this.nodes.find((n) => n.id === this.selectedNodeId) || null
		},
	},

	async mounted() {
		await this.load()
		this.syncLiveSubscriptions()
	},

	/**
	 * Lifecycle hook: release the live collection subscriptions on unmount.
	 *
	 * Vue 3 renamed `beforeDestroy` to `beforeUnmount` and does NOT call — or
	 * warn about — the old name. Left as `beforeDestroy` this hook would never
	 * run, leaking both live collection subscriptions and the `$watch` handle
	 * for the lifetime of the page.
	 *
	 * @spec openspec/specs/realtime-updates/spec.md
	 * @return {void}
	 */
	beforeUnmount() {
		this.releaseLiveSubscriptions()
	},

	methods: {
		/**
		 * Subscribe to live updates for the skill and character collections
		 * (adopt-live-updates-ui). Events are refetch hints only: the
		 * liveUpdatesPlugin re-runs fetchCollection with the last-used
		 * params, so the store cache refreshes; the watcher installed here
		 * bridges the fresh collections into this view's local copies.
		 * Idempotent (single-shot per mount — the scope is fixed). Uses
		 * notify_push when available, visibility-gated polling otherwise.
		 * The reference-data collections (ability/condition/effect names)
		 * change rarely and are intentionally not subscribed.
		 *
		 * @spec openspec/specs/realtime-updates/spec.md
		 * @return {Promise<void>}
		 */
		async syncLiveSubscriptions() {
			const store = useObjectStore()
			if (typeof store.subscribe !== 'function' || this.liveActive) {
				return
			}
			// Only subscribe to types that initializeStores() registered —
			// subscribe() throws on unregistered types (settings not loaded).
			const types = ['skill', 'character'].filter(
				(type) => Array.isArray(store.objectTypes) && store.objectTypes.includes(type),
			)
			if (types.length === 0) {
				return
			}
			this.liveActive = true
			const epoch = this.liveEpoch
			try {
				const handles = await Promise.all(types.map((type) => store.subscribe(type)))
				if (this.liveEpoch !== epoch) {
					// Released while awaiting (component destroyed) — drop the
					// now-stale subscriptions instead of leaking them.
					handles.forEach((handle) => store.unsubscribe(handle))
					return
				}
				this.liveHandles = handles
				// Bridge: event → plugin refetch → store collection cache →
				// local copies (which the tree computeds render from).
				this.liveUnwatch = this.$watch(
					() => types.map((type) => store.getCollection(type)),
					() => this.syncFromStore(store),
				)
			} catch (e) {
				this.liveActive = false
				this.liveHandles = []
				// eslint-disable-next-line no-console
				console.warn('[larpingapp] skill-tree live subscription failed:', e?.message ?? e)
			}
		},

		/**
		 * Refresh the local skill/character copies from the store's
		 * collection cache after a live-update refetch.
		 *
		 * @param {object} store The object store instance.
		 * @spec openspec/specs/realtime-updates/spec.md
		 * @return {void}
		 */
		syncFromStore(store) {
			const skills = store.getCollection('skill')
			const characters = store.getCollection('character')
			if (Array.isArray(skills)) {
				this.skills = skills
			}
			if (Array.isArray(characters)) {
				this.characters = characters
			}
		},

		/**
		 * Release the live collection subscriptions and their cache watcher,
		 * and invalidate any in-flight subscribe (its resolution unsubscribes
		 * itself via the epoch check).
		 *
		 * @spec openspec/specs/realtime-updates/spec.md
		 * @return {void}
		 */
		releaseLiveSubscriptions() {
			this.liveEpoch += 1
			if (this.liveUnwatch) {
				this.liveUnwatch()
				this.liveUnwatch = null
			}
			const store = useObjectStore()
			if (typeof store.unsubscribe === 'function') {
				this.liveHandles.forEach((handle) => store.unsubscribe(handle))
			}
			this.liveHandles = []
			this.liveActive = false
		},

		/**
		 * Load skills + reference data. Never throws — a fetch failure degrades
		 * to an empty / uncoloured tree (fetchCollection returns [] on error).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		async load() {
			this.loading = true
			try {
				const store = useObjectStore()
				const [skills, characters, abilities, conditions, effects] = await Promise.all([
					store.fetchCollection('skill', { _limit: 1000 }),
					store.fetchCollection('character', { _limit: 1000 }),
					store.fetchCollection('ability', { _limit: 1000 }),
					store.fetchCollection('condition', { _limit: 1000 }),
					store.fetchCollection('effect', { _limit: 1000 }),
				])
				this.skills = Array.isArray(skills) ? skills : []
				this.characters = Array.isArray(characters) ? characters : []
				this.abilityNames = indexNames(abilities)
				this.conditionNames = indexNames(conditions)
				this.effectNames = indexNames(effects)
			} catch (error) {
				// eslint-disable-next-line no-console
				console.warn('[larpingapp] skill-tree data unavailable', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Fetch the server requirement report for the selected character. On any
		 * failure the report stays null so the tree renders uncoloured rather
		 * than erroring (degradation).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		async onCharacterChange() {
			this.report = null
			const id = this.selectedCharacter?.value
			if (!id) {
				return
			}
			try {
				const response = await fetch(
					generateUrl('/apps/larpingapp/api/characters/{id}/requirement-report', { id }),
					{ headers: { requesttoken: OC.requestToken, 'OCS-APIREQUEST': 'true' } },
				)
				if (!response.ok) {
					throw new Error(`report failed: ${response.status}`)
				}
				this.report = await response.json()
			} catch (error) {
				// Degrade to an uncoloured tree — the nodes/edges still render.
				this.report = null
				// eslint-disable-next-line no-console
				console.warn('[larpingapp] requirement report unavailable', error)
			}
		},

		/**
		 * Select (or deselect) a node to show its requirement detail.
		 *
		 * @param {object} node The node to select.
		 * @return {void}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		selectNode(node) {
			this.selectedNodeId = this.selectedNodeId === node.id ? null : node.id
		},

		/**
		 * Whether a node declares any requirement at all.
		 *
		 * @param {object} node The node.
		 * @return {boolean}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		hasAnyRequirement(node) {
			return node.requiredSkills.length > 0
				|| node.requiredStats.length > 0
				|| node.requiredConditions.length > 0
				|| node.requiredEffects.length > 0
		},

		/**
		 * Localised label for a node state.
		 *
		 * @param {string} state The node state.
		 * @return {string}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		stateLabel(state) {
			return this.t('larpingapp', (STATE_META[state] || STATE_META.unknown).label)
		},

		/**
		 * Badge variant for a node state.
		 *
		 * @param {string} state The node state.
		 * @return {string}
		 * @spec openspec/changes/skill-tree-visualization/specs/skill-tree-visualization/spec.md
		 */
		stateVariant(state) {
			return (STATE_META[state] || STATE_META.unknown).variant
		},
	},
}
</script>

<style scoped>
.skill-tree {
	padding: 16px;
}

.skill-tree__controls {
	display: flex;
	gap: 16px;
	flex-wrap: wrap;
	margin-bottom: 12px;
}

.skill-tree__legend {
	display: flex;
	gap: 16px;
	margin-bottom: 16px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.skill-tree__legend-item {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.skill-tree__dot {
	width: 12px;
	height: 12px;
	border-radius: 50%;
	display: inline-block;
}

.skill-tree__dot--owned {
	background-color: var(--color-success);
}

.skill-tree__dot--available {
	background-color: var(--color-primary-element);
}

.skill-tree__dot--locked {
	background-color: var(--color-error);
}

.skill-tree__body {
	display: flex;
	gap: 24px;
	align-items: flex-start;
	flex-wrap: wrap;
}

.skill-tree__graph {
	flex: 1 1 60%;
	min-width: 280px;
}

.skill-tree__tier {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	margin-bottom: 16px;
}

.skill-tree__node {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 10px 14px;
	border-radius: var(--border-radius-large, 12px);
	border: 2px solid var(--color-border);
	background-color: var(--color-main-background);
	text-align: left;
	cursor: pointer;
	min-width: 160px;
}

.skill-tree__node[aria-pressed='true'] {
	box-shadow: inset 3px 0 0 0 var(--color-primary-element);
}

.skill-tree__node--owned {
	border-color: var(--color-success);
}

.skill-tree__node--available {
	border-color: var(--color-primary-element);
}

.skill-tree__node--locked {
	border-color: var(--color-error);
	opacity: 0.85;
}

.skill-tree__node-name {
	font-weight: bold;
}

.skill-tree__node-requires {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.skill-tree__detail {
	flex: 1 1 30%;
	min-width: 220px;
	padding: 16px;
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-background-hover);
}

.skill-tree__reqs dt {
	font-weight: bold;
	margin-top: 8px;
}
</style>
