<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- Copyright (C) 2026 Conduction B.V. -->

<!--
 Larpinq app shell. Mounts CnAppRoot with the bundled manifest and the
 v2 kind-tagged registry (ADR-036); provides the `objectSidebarState` channel so
 detail pages (CnDetailPage) can drive a single host-rendered CnObjectSidebar
 through the #sidebar slot.

 This file follows the canonical Tier-4 scaffold for the JSON manifest
 renderer pattern (hydra ADR-024). The Settings menu entry uses
 action: "user-settings" → opens NcAppSettingsDialog via CnAppRoot's
 cnOpenUserSettings inject.

 The previous anchor here pointed at
 `openspec/changes/manifest-v2-vue-scaffold/...`, a change that has never
 existed in this repository — not under `openspec/changes/`, not under
 `openspec/changes/archive/`, and not as a canonical spec. It was the repo's
 only dangling @spec target. The requirement this file actually satisfies is
 "CnAppRoot SHALL be the boot entry point" in the Tier-4 graduation spec.

 @spec openspec/changes/larpinq-manifest-tier-4/specs/larpinq-manifest-tier-4/spec.md
-->
<template>
	<CnAppRoot
		:aiCompanion="true"
		:manifest="manifest"
		:registry="registry"
		:customComponents="customComponents"
		:pageTypes="pageTypes"
		appId="larpinq"
		:translate="translateForApp"
		:permissions="permissions">
		<template #sidebar="{ pageSidebarComponent }">
			<CnObjectSidebar
				v-if="objectSidebarState.active"
				:title="objectSidebarState.title"
				:subtitle="objectSidebarState.subtitle"
				:objectType="objectSidebarState.objectType"
				:objectId="objectSidebarState.objectId"
				:register="objectSidebarState.register"
				:schema="objectSidebarState.schema"
				:hiddenTabs="objectSidebarState.hiddenTabs"
				:tabs="objectSidebarState.tabs"
				:open="objectSidebarState.open"
				@update:open="objectSidebarState.open = $event" />
			<!-- The manifest page's own sidebar (pages[].sidebarComponent). Passed in
			     as a slot prop because filling this slot suppresses CnAppRoot's
			     fallback, which is what hid the flow sidebar. -->
			<component :is="pageSidebarComponent" v-if="pageSidebarComponent" />
		</template>
		<template #user-settings>
			<NcAppSettingsSection id="general" :name="t('larpinq', 'General')">
				<p class="app-root__settings-hint">
					{{ t('larpinq', 'Configure your Larpinq settings here.') }}
				</p>
			</NcAppSettingsSection>
		</template>
	</CnAppRoot>
</template>

<script>
// Multi-tenancy composable (multi-tenancy-context, ADR-025).
//
// This was previously pulled in with a `require('@conduction/nextcloud-vue')`
// inside a try/catch, to tolerate a nc-vue release that pre-dated the export
// ("Pre-release fallback" scenario in larpinq-adopt-or-abstractions/spec.md).
// Under Vue 3 that indirection is actively harmful: `require()` resolves the
// package's CJS build while the line above resolves its ESM build, so the app
// would hold TWO module instances of the library. provide/inject matches on
// injection-key IDENTITY, and `CnAppRoot` calls `provideTenantContext()` from
// the ESM copy — a `useTenantContext` read out of the CJS copy would look up a
// different key object, silently return the no-op fallback, and the tenant
// watcher would never fire. Green, and dead.
//
// The dependency is pinned exactly (`@conduction/nextcloud-vue@2.1.0-vue3.13`),
// which exports the composable, so a static import is correct and a version
// without it now fails loudly at BUILD time instead of degrading at runtime.
// The spec's "absence MUST NOT crash" contract still holds: the composable
// itself returns a no-op fallback when no provider is mounted.
import {
	CnAppRoot,
	CnObjectSidebar,
	useTenantContext,
} from '@conduction/nextcloud-vue'
import { translate as ncT } from '@nextcloud/l10n'
import { NcAppSettingsSection } from '@nextcloud/vue'
import { reactive } from 'vue'
import { setObjectStoreTenantUuid, useObjectStore } from './store/modules/object.js'

export default {
	name: 'App',

	components: {
		CnAppRoot,
		CnObjectSidebar,
		NcAppSettingsSection,
	},

	/**
	 * @spec exclude Vue provide() lifecycle hook — exposes the reactive
	 * objectSidebarState channel to descendants; framework glue, no behavior.
	 */
	provide() {
		return {
			// Channel for CnDetailPage → host-rendered CnObjectSidebar.
			// `reactive()` (Vue 3's replacement for `Vue.observable()`) makes
			// the plain object reactive, so descendants that mutate it drive
			// this component's `#sidebar` slot.
			objectSidebarState: this.objectSidebarState,
		}
	},

	props: {
		/**
		 * Manifest object — passed from main.js bootstrap. CnAppRoot reads
		 * `manifest.dependencies` for the dependency-check phase and
		 * `manifest.menu` for the default CnAppNav.
		 */
		manifest: {
			type: Object,
			required: true,
		},

		/**
		 * v2 kind-tagged component registry (ADR-036). Passed to CnAppRoot's
		 * `registry` prop. Each entry: `{ kind, component, ...kindMetadata }`
		 * where `kind` is one of "page" | "widget" | "actions" | "section" |
		 * "modal" | "form-field" | "cell-renderer". CnPageRenderer keys page
		 * dispatch off `kind === "page"` entries; other kinds serve
		 * dashboard widgets, settings sections, action menus, etc. Replaces
		 * the deprecated `customComponents` prop.
		 */
		registry: {
			type: Object,
			default: () => ({}),
		},

		/**
		 * Page-type registry — `{ index, detail, dashboard, settings, ... }`.
		 * Wired through to descendant `CnPageRenderer` instances via
		 * provide/inject.
		 */
		pageTypes: {
			type: Object,
			default: null,
		},
	},

	/**
	 * Mount the tenant-context bridge in setup() so the watcher fires
	 * inside the component's reactive scope (auto-cleanup on unmount).
	 *
	 * CnAppRoot itself calls `provideTenantContext()` in ITS own setup(),
	 * so by the time descendants — including this consumer — render,
	 * the provider is mounted. We consume via `useTenantContext()` here.
	 *
	 * On every tenant change we:
	 *   1. Update the module-local UUID closure read by the object store
	 *      factory's `organisationUuidGetter` (stamps the next request's
	 *      `X-OpenRegister-Organisation` header).
	 *   2. Call `store.setActiveTenantOrganisation(uuid)` to clear the
	 *      in-memory collection / object / pagination caches so the next
	 *      fetch hits the new tenant cleanly.
	 *   3. If the current route is a `:id` detail view, navigate back to
	 *      the parent index — the object may not exist in the new tenant
	 *      (spec scenario: "Tenant switch on detail view navigates back").
	 *
	 * The composable returns a no-op fallback when no provider is mounted,
	 * so this code is safe even if `useTenantContext` is missing — the
	 * watcher just never fires.
	 *
	 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md
	 *
	 * @return {object} Setup return — none needed externally.
	 */
	setup() {
		// `useTenantContext()` returns a no-op fallback (a null-valued ref)
		// when no provider is mounted, so this stays safe on a single-tenant
		// deployment — the watcher simply never fires.
		const { activeOrganisationUuid } = useTenantContext()

		return { cnActiveOrganisationUuid: activeOrganisationUuid }
	},

	data() {
		return {
			objectSidebarState: reactive({
				active: false,
				open: true,
				objectType: '',
				objectId: '',
				title: '',
				subtitle: '',
				register: '',
				schema: '',
				hiddenTabs: [],
				tabs: undefined,
			}),

			// Tracks the last tenant UUID we wired into the object store so
			// the watcher (which fires immediate: true) can no-op on the
			// initial undefined→null transition.
			tenantSyncedUuid: undefined,
		}
	},

	computed: {
		/**
		 * @spec exclude Trivial passthrough of window.OC.currentUser.permissions
		 * to CnAppRoot — reads framework global, no business logic.
		 */
		permissions() {
			return window.OC?.currentUser?.permissions ?? []
		},

		/**
		 * Flat name→component map derived from the v2 `registry` prop.
		 *
		 * `CnPageRenderer.effectiveCustomComponents` resolves `actionsComponent`,
		 * `headerComponent`, and `page.slots.*` keys against the legacy
		 * `customComponents` inject, not against `cnRegistry`. Until the library
		 * unifies both resolution paths, we derive a flat shim here so that
		 * slot-override lookups (e.g. a detail page's `slots.photos-leaf:
		 * "ObjectDetail"`) continue to work when the registry uses the v2
		 * kind-tagged format.
		 *
		 * Entries that carry a `component` field are included; pure-metadata
		 * entries without a `component` field are skipped.
		 *
		 * @spec exclude Framework shim — bridges v2 registry to legacy
		 * customComponents inject used by CnPageRenderer slot resolution.
		 */
		customComponents() {
			const result = {}
			for (const [key, entry] of Object.entries(this.registry || {})) {
				if (entry && typeof entry.component !== 'undefined') {
					result[key] = entry.component
				}
			}
			return result
		},
	},

	watch: {
		/**
		 * React to tenant switches via the nc-vue multi-tenancy-context
		 * composable (`useTenantContext().activeOrganisationUuid`).
		 * Watcher only mounts when `setup()` returned a value (i.e. when
		 * nc-vue exports the composable); the data-default `undefined`
		 * sentinel keeps the very first call a no-op.
		 *
		 * Steps on every actual change:
		 *   1. Update the module-local closure read by the object store
		 *      factory so the next request stamps the new UUID.
		 *   2. Call `setActiveTenantOrganisation()` on the store to wipe
		 *      the collection / object / pagination caches.
		 *   3. If the current route includes `:id` segments (detail view),
		 *      navigate back to the parent index, since the object may
		 *      not be visible in the new tenant.
		 *
		 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md
		 */
		cnActiveOrganisationUuid: {
			immediate: true,
			/**
			 * Apply one tenant switch. Carries its own `@spec` because gate-16
			 * parses `handler()` as a method in its own right — the tag on the
			 * enclosing watcher entry above does not reach it.
			 *
			 * @param {string|undefined} uuid The newly active organisation UUID.
			 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md
			 */
			handler(uuid) {
				const next =
					typeof uuid === 'string' && uuid.length > 0 ? uuid : null
				if (this.tenantSyncedUuid === next) {
					return
				}
				const previous = this.tenantSyncedUuid
				this.tenantSyncedUuid = next

				// 1. Update header stamping for the object store factory.
				setObjectStoreTenantUuid(next)

				// 2. Clear caches via the store action.
				try {
					const store = useObjectStore()
					if (
						store
						&& typeof store.setActiveTenantOrganisation === 'function'
					) {
						store.setActiveTenantOrganisation(next)
					}
				} catch {
					// Optional catch binding: NC's config sets
					// `caughtErrors: 'all'`, so a named-but-unused error
					// parameter reports as an unused variable.
					// Pinia not active yet on first immediate fire — safe
					// to ignore; subsequent fires will reach the store.
				}

				// 3. Detail view → navigate back to parent index on switch.
				// Skip the initial mount (previous === undefined) so a deep
				// link into a detail page doesn't redirect on first paint.
				if (
					previous !== undefined
					&& this.$route
					&& /:|\//.test(this.$route.path)
				) {
					const params = this.$route.params || {}
					const hasIdParam = Object.keys(params).some(
						(k) =>
							params[k] !== undefined
							&& params[k] !== null
							&& params[k] !== '',
					)
					if (hasIdParam) {
						// Find the parent index route by stripping the
						// trailing `/:id` segment from the matched path.
						const matched = this.$route.matched && this.$route.matched[0]
						const indexPath = matched
							? matched.path.replace(/\/:[^/]+$/, '')
							: this.$route.path.replace(/\/[^/]+\/?$/, '')
						const target = indexPath || '/'
						if (this.$router && target !== this.$route.path) {
							this.$router.push(target).catch(() => {})
						}
					}
				}
			},
		},
	},

	methods: {
		/**
		 * Translate function passed down to CnAppRoot / CnAppNav /
		 * CnPageRenderer. Closes over the Nextcloud `translate` import so
		 * the lib never has to know our app id.
		 *
		 * @param {string} key Translation key.
		 * @return {string} Translated string (or the key on miss).
		 *
		 * @spec exclude Trivial translate passthrough closing over the app id
		 * so the shared lib never needs to know it — framework glue.
		 */
		translateForApp(key) {
			return ncT('larpinq', key)
		},
	},
}
</script>
