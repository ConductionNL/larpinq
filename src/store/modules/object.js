/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Object store for Larpinq — powered by @conduction/nextcloud-vue.
 *
 * Uses createObjectStore('object') to maintain the same Pinia store ID.
 * The full implementation (CRUD, pagination, caching, resolveReferences)
 * lives in the shared library.
 *
 * Plugins add sub-resource support for files, audit trails, and relations.
 *
 * ## Multi-tenancy wiring (multi-tenancy-context, ADR-025)
 *
 * The factory accepts an `organisationUuidGetter` closure. nc-vue's
 * `useObjectStore.js` calls it on every outgoing request and stamps the
 * returned UUID into the `X-OpenRegister-Organisation` header (see
 * `_buildHeaders` + `_resolveOrganisationUuid` in the library).
 *
 * We expose a module-level setter (`setObjectStoreTenantUuid`) that
 * `App.vue` calls when `useTenantContext().activeOrganisationUuid`
 * changes. The getter form (closure over a module-local var) avoids
 * pulling a Vue ref through the Pinia state at definition time, which
 * keeps the store SSR-safe and unit-testable.
 *
 * On switch, `App.vue` ALSO calls `store.setActiveTenantOrganisation(uuid)`
 * which clears the in-memory collection / object / pagination caches so
 * the next fetch hits the new tenant.
 *
 * ## Language wiring (i18n-language-negotiation-getters, OR ADR-025)
 *
 * `languageGetter` stamps `?_lang=<user language>` on reads, so OpenRegister
 * returns the user's translation of each translatable property.
 * `targetLanguageGetter` stamps `X-Translation-Target-Language` on writes, so
 * an edit made in a German UI is stored as the German translation instead of
 * overwriting the source. OpenRegister files a target equal to the
 * register's default language as the source itself, so a user writing in
 * the default language still updates the source row.
 */
import {
	auditTrailsPlugin,
	createObjectStore,
	filesPlugin,
	relationsPlugin,
} from '@conduction/nextcloud-vue'
import { userLanguage } from '../../services/userLanguage.js'

// Module-local tenant UUID — written by `setObjectStoreTenantUuid()` from
// the host App.vue once `useTenantContext()` resolves an active tenant.
// Null means "single-tenant mode"; the header is omitted in that case.
let _activeTenantUuid = null

/**
 * Mutator called by App.vue on tenant switch. Updates the closure value
 * read by the factory's `organisationUuidGetter` so every subsequent
 * fetch carries the new tenant UUID.
 *
 * @param {string|null} uuid New active tenant UUID, or null to clear.
 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md
 */
export function setObjectStoreTenantUuid(uuid) {
	_activeTenantUuid = typeof uuid === 'string' && uuid.length > 0 ? uuid : null
}

export const useObjectStore = createObjectStore('object', {
	plugins: [filesPlugin(), auditTrailsPlugin(), relationsPlugin()],
	// Library reads this on every outgoing request → header
	// `X-OpenRegister-Organisation`. Falls back to the store's own
	// `activeTenantOrganisationUuid` when null (also written by us via
	// `setActiveTenantOrganisation()` so cache-clear stays atomic with
	// the header switch).
	organisationUuidGetter: () => _activeTenantUuid,
	// Reads: `?_lang=<user language>` (REQ: OR fetches pass the locale).
	languageGetter: userLanguage,
	// Writes: `X-Translation-Target-Language: <user language>`.
	targetLanguageGetter: userLanguage,
})
