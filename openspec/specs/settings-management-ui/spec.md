---
retrofit: true
---

# Settings Management UI

## Purpose

SPA mount fixed in #202 — settings panel load scenario covered by tests/e2e/spec-coverage/spa-ui.spec.ts; Pinia store lifecycle scenarios annotated @e2e exclude below

LarpingApp exposes its data-source configuration through a Vue settings surface
backed by a Pinia settings store. The admin `Settings.vue` panel reads and writes
per-object-type source/register/schema selections, the `UserSettings.vue` dialog
offers a re-import action, and the settings store (`store/modules/settings.js`)
plus the bootstrap helper (`store/store.js`) own the REST calls and store
hydration. The backend REST contract and the admin-panel REQs already exist
(`admin-settings`, `user-settings`); this capability specifies the observed
behavior of the frontend store + UI layer that drives them.

**Key source files:**
- `src/store/modules/settings.js` — Pinia settings store (fetch/save/reimport)
- `src/store/store.js` — store bootstrap + object-type registration
- `src/views/settings/Settings.vue` — admin data-storage configuration panel
- `src/views/settings/UserSettings.vue` — in-app re-import dialog

## Requirements

### REQ-001: Settings Store Lifecycle

@e2e exclude JS unit-test scope — Pinia store fetch/persist lifecycle is tested via Jest with mocked fetch; internal store state not browser-navigable

The settings store MUST fetch the current configuration from `GET /api/settings`,
persist updates via `POST /api/settings`, and trigger a forced re-import via
`POST /api/settings/reimport` — tracking loading/error state and exposing the
resulting configuration, OpenRegister availability, and admin flag.

#### Scenario: Fetch hydrates store state

- WHEN `fetchSettings()` resolves successfully
- THEN the store MUST set `openRegisters`, `isAdmin`, and `config` from the response
- AND `initialized` MUST become true
- WHEN any settings request fails
- THEN the store MUST capture the error message and clear the loading flag

### REQ-002: Store Bootstrap and Object-Type Registration

@e2e exclude JS unit-test scope — store bootstrap and object-type registration is tested via Jest mocks; internal Pinia state not browser-navigable

The store bootstrap helper MUST fetch settings on startup and, for each of the
nine LARP object types that has both a configured register and a per-type schema,
register that object type with the object store so subsequent CRUD resolves to
the correct OpenRegister mapper.

#### Scenario: Configured types are registered

- GIVEN the fetched configuration has a `register` plus a `character_schema`
- WHEN `initializeStores()` runs
- THEN the object store MUST register the `character` type with its schema and register
- AND object types lacking a register or schema MUST NOT be registered

### REQ-003: Admin Settings Panel Load and Persist

The admin settings panel MUST load the current configuration on creation, build
per-type source/register/schema selections from it, and persist all changes in a
single `POST /api/settings` request, mapping the selected options back to the flat
`{type}_source` / `{type}_register` / `{type}_schema` config keys.

#### Scenario: Panel loads then saves all types

- WHEN the panel is created
- THEN it MUST GET the settings and seed each object type's source/register/schema selectors from the returned configuration
- WHEN the admin clicks "Save All"
- THEN a single POST MUST be sent containing each type's source, and the register/schema only when the source is `openregister`

### REQ-004: Cascading Source and Register Selection

The admin panel MUST clear dependent selections when a parent selection changes:
switching a type's source to "Internal" MUST clear its register and schema, and
changing a type's register MUST clear its schema. Register and schema dropdown
options MUST be derived from the available OpenRegister registers and their schemas.

#### Scenario: Cascading clears

- GIVEN a type is set to "Open Register" with a register and schema selected
- WHEN the source is changed to "Internal"
- THEN the register and schema selections MUST be cleared
- WHEN the register is changed
- THEN the schema selection MUST be cleared
- AND the register/schema dropdown options MUST be populated from the available registers and the selected register's schemas

### REQ-005: Re-import Configuration Action

Both the admin panel and the in-app user-settings dialog MUST offer a re-import
action that triggers a forced configuration re-import, shows a loading state while
in flight, and reports success or failure feedback to the user.

#### Scenario: Re-import reports outcome

- WHEN the user triggers re-import
- THEN a forced re-import request MUST be sent and a loading state MUST be shown
- AND on success a success message MUST be displayed and the admin panel MUST reload its settings
- AND on failure the returned error message MUST be displayed
