# Design — larpingapp-adopt-or-abstractions

## Reuse analysis

| Capability | Reuse from | Why |
|------------|-----------|-----|
| App-manifest schema + loader | `@conduction/nextcloud-vue` (`src/schemas/app-manifest.schema.json`, `useAppManifest`) | Single source of truth per `hydra/openspec/changes/adopt-app-manifest/`. Do not fork. |
| Index / detail page-type renderers | nc-vue `CnIndexPage`, `CnDetailPage` (already used in LarpingApp views) | LarpingApp views already wrap these; manifest just declares which schema each page consumes. |
| Register / schema ID resolution | OR `RegisterResolverService` (`openregister/openspec/changes/register-resolver-service/`) | Eliminates two duplicated `getValueString(...register/schema...)` call shapes in `RegisterObjectFetcher` + `SettingsService`. |
| Tenant context | `useTenantContext()` from nc-vue (`nextcloud-vue/openspec/changes/multi-tenancy-context/`) | Cache invalidation + write header stamping. Reuse rather than roll local org-scope logic. |
| i18n source-of-truth metadata | OR `sourceLanguage` metadata on translation rows (`openregister/openspec/changes/i18n-source-of-truth/`) | Read-only consumption — display "(translated from)" badge. Do not duplicate the metadata. |
| API language negotiation | OR `?_lang=` + `X-Translation-Target-Language` (`openregister/openspec/changes/i18n-api-language-negotiation/`) | Wire into a single `orClient.js` composable so every fetch / write is consistent. |
| Pinia stores | LarpingApp's existing entity-typed Pinia stores | Keep. Migrate fetch URL building into `orClient.js`; stores still own state shape and hydration. |
| PDF export | Existing `CharacterService` + mPDF + Twig pipeline | Untouched. Manifest declares the action via `actionsComponent` slot override; the export endpoint stays where it is. |

### What we deliberately do NOT reuse

- **Hydra per-app `adr-000`** — per ADR-022, apps MUST NOT carry an
  ADR that re-asserts cross-app conventions. LarpingApp does not
  have an `adr-000` and this change does NOT introduce one.
- **OR's lifecycle annotations** — character / event status fields
  do not have a state machine that benefits from
  `x-openregister-lifecycle`. Stat calculation and PDF export are
  independent of object lifecycle.
- **OR's notification engine** — LarpingApp does not currently send
  notifications on character / event changes. Adopting
  `x-openregister-notifications` is a follow-up if a product reason
  emerges.
- **OR's `RegisterResolverService` for non-register keys** —
  `SettingsService` has feature-flag and tunable keys that are NOT
  register/schema pairs. Those stay on `IAppConfig` directly.

## Public API / migration shape

### `src/manifest.json` (new file)

```json
{
  "$schema": "https://unpkg.com/@conduction/nextcloud-vue@latest/dist/schemas/app-manifest.schema.json",
  "version": "0.1.0",
  "dependencies": ["openregister"],
  "menu": [
    { "id": "characters", "label": "larpingapp.menu.characters", "icon": "icon-user", "route": "/characters", "section": "main", "order": 10 },
    { "id": "events", "label": "larpingapp.menu.events", "icon": "icon-calendar", "route": "/events", "section": "main", "order": 20 },
    { "id": "items", "label": "larpingapp.menu.items", "icon": "icon-package", "route": "/items", "section": "main", "order": 30 },
    { "id": "skills", "label": "larpingapp.menu.skills", "icon": "icon-star", "route": "/skills", "section": "main", "order": 40 },
    { "id": "abilities", "label": "larpingapp.menu.abilities", "icon": "icon-flash", "route": "/abilities", "section": "main", "order": 50 },
    { "id": "conditions", "label": "larpingapp.menu.conditions", "icon": "icon-warning", "route": "/conditions", "section": "main", "order": 60 },
    { "id": "effects", "label": "larpingapp.menu.effects", "icon": "icon-magic", "route": "/effects", "section": "main", "order": 70 },
    { "id": "templates", "label": "larpingapp.menu.templates", "icon": "icon-template", "route": "/templates", "section": "main", "order": 80 },
    { "id": "players", "label": "larpingapp.menu.players", "icon": "icon-users", "route": "/players", "section": "main", "order": 90 },
    { "id": "settings", "label": "larpingapp.menu.settings", "icon": "icon-settings", "route": "/settings", "section": "settings", "permission": "admin" }
  ],
  "pages": [
    { "id": "characters-index", "route": "/characters", "type": "index", "title": "larpingapp.pages.characters", "config": { "register": "@resolve:character_register", "schema": "@resolve:character_schema", "columns": ["name", "player", "status"] } },
    { "id": "characters-detail", "route": "/characters/:id", "type": "detail", "title": "larpingapp.pages.character", "config": { "register": "@resolve:character_register", "schema": "@resolve:character_schema" }, "actionsComponent": "PdfExportAction" }
    // ... other entity pages follow the same shape
  ]
}
```

Notes:

- The `@resolve:{key}` sentinel tells the renderer to call
  `RegisterResolverService` (or its frontend equivalent) at render
  time. Avoids hardcoding numeric register / schema IDs that vary
  across deployments.
- `actionsComponent: "PdfExportAction"` is registered via
  `customComponents` prop on `CnAppRoot` (Tier 4) or directly on
  `CnDetailPage` (Tier 2/3).

### `src/composables/orClient.js` (new file)

Centralised OR fetch / write helper:

```js
export function useOrClient () {
  const baseUrl = '/index.php/apps/openregister/api'

  async function fetchObject ({ register, schema, uuid }) {
    const lang = OC.getLocale().split('_')[0]
    const url = `${baseUrl}/objects/${register}/${schema}/${uuid}?_lang=${lang}`
    return axios.get(url, { headers: buildHeaders() })
  }

  async function patchObject ({ register, schema, uuid, body, targetLang }) {
    const url = `${baseUrl}/objects/${register}/${schema}/${uuid}`
    const headers = buildHeaders()
    if (targetLang) {
      headers['X-Translation-Target-Language'] = targetLang
    }
    return axios.patch(url, body, { headers })
  }

  return { fetchObject, patchObject }
}
```

### `lib/Service/RegisterObjectFetcher.php` (edit)

Before:

```php
private function resolveRegisterAndSchema (string $objectType): array {
  $register = $this->config->getValueString($this->appName, $objectTypeLower.'_register', '');
  // ...
  $schema = $this->config->getValueString($this->appName, $objectTypeLower.'_schema', '');
  // ...
}
```

After:

```php
private function resolveRegisterAndSchema (string $objectType): array {
  return $this->resolver->resolveForObjectType($objectType);
}
```

The constructor gains
`OCA\OpenRegister\Service\RegisterResolverService $resolver` (DI via
`AppContainer`). `RegisterResolverService::resolveForObjectType()`
returns a typed `RegisterSchemaPair` object with `registerId` and
`schemaId` properties.

### Migration risk surface

| Risk | Mitigation |
|------|-----------|
| `RegisterResolverService` not yet deployed alongside LarpingApp | Phase 2 gates on a runtime version check at boot; logs a warning and falls back to direct `getValueString` if the service is absent. (Tracked as a tasks.md subtask.) |
| Manifest validation fails CI on first introduction | Tier 2 keeps router hand-wired; failed validation does NOT take the app down. |
| Tenant-switch refetch causes a UX flicker on small lists | Existing nc-vue `CnIndexPage` already shows a skeleton during refetch; flicker is the established UX. |
| `?_lang=` on POSTs accidentally treated as the source language by OR | Explicit: writes use `X-Translation-Target-Language`, not `?_lang=`. The `?_lang=` query parameter is read-only. |
| Locale code mismatch (NC `en_GB` vs OR `en`) | `orClient.js` strips region tags before passing to OR (`OC.getLocale().split('_')[0]`). Documented in the spec. |
| Dual data-source mode (internal mapper vs OR) breaks under resolver | Resolver is OR-side only. Internal-mapper paths in `RegisterObjectFetcher` MUST keep their existing behaviour; resolver only kicks in when `objectType` config points at OR. Phase 2.2 wraps in a feature check. |

## Open design questions

1. **Q1 — Manifest's `@resolve:{key}` sentinel.** This convention
   does not exist in the nc-vue manifest schema today. Should
   LarpingApp upstream the sentinel handling into the schema (via
   `hydra/openspec/changes/adopt-app-manifest/`) or implement it
   locally as a pre-processor that runs before
   `useAppManifest`? Recommend: implement locally for now, upstream
   when a second app needs it.

2. **Q2 — Multi-tenancy gating.** `useTenantContext()` ships in
   nc-vue PR #113 / `multi-tenancy-context` change but the
   versioned package release is unscheduled. Should Phase 4 block
   the whole change on the release, or ship Phases 1-3 and add a
   follow-up `larpingapp-multi-tenancy-wiring` change once nc-vue
   releases? Recommend: ship Phases 1-3 first; Phase 4 as a
   trailing follow-up if nc-vue is not released by Phase 3
   completion.

3. **Q3 — `(translated from {lang})` badge placement.** Index views
   show a list of characters with names. Where does the badge go —
   inline next to the name, or as a small icon with a tooltip?
   Inline is more discoverable; tooltip is denser. Recommend:
   icon-with-tooltip, since lists can have many translated rows
   and inline text would dominate the visual.

4. **Q4 — Internal-mapper objects with `sourceLanguage`.**
   Internal-mapper Entity classes (the non-OR data path) do not
   carry `sourceLanguage` metadata. Should the badge logic
   conditionally render only on OR-backed object types, or should
   internal mappers grow a `sourceLanguage` column? Recommend:
   render only on OR-backed types. Internal mappers are
   single-language by convention.

5. **Q5 — `actionsComponent` slot override naming.** The manifest
   declares `actionsComponent: "PdfExportAction"` as a registry
   string. Should the component name match the file name (kebab vs
   PascalCase) or follow the existing LarpingApp convention?
   Recommend PascalCase to match `customComponents` keys per
   nc-vue convention.

6. **Q6 — Settings page Tier upgrade.** Phase 1 keeps the settings
   page as `type: "custom"`. Could it become `type: "index"` over
   the per-type configuration objects (objectType + register +
   schema) stored in `IAppConfig`? It would need a new
   IAppConfig-as-OR-source adapter. Recommend: stay `custom`
   for now; revisit if nc-vue grows a generic `IAppConfig` data
   source.

7. **Q7 — Resolver feature flag.** Should
   `RegisterObjectFetcher` carry an `if-resolver-available` feature
   flag to keep BC during the transition (Phase 2.2 mitigation), or
   make resolver injection mandatory? Recommend feature flag for
   the first release; remove the flag in a follow-up once OR with
   the resolver is the deployed minimum.
