# Proposal: larpinq-manifest-tier-4

## Why

Larpinq is in practice past Tier 2 — its nav/router are rendered FROM
`src/manifest.json` via `CnPageRenderer` in `src/main.js`, page types are
typed primitives (`index` / `detail` / `dashboard` / `settings`), and slot
overrides resolve through the ADR-036 `src/registry.js`. The remaining
Tier-4 step is adopting `CnAppRoot` with `customComponents.js` fully
retired (see `larpinq-adopt-or-abstractions §5.2`).

The prerequisites have all landed:

1. **ADR-036 kind-agnostic slot resolver** shipped in nextcloud-vue
   (`nextcloud-vue#459`) — `resolveCustomComponent` is split so slot /
   actions / section / header lookups accept any `kind` with a
   `component` field, removing the page-only constraint that previously
   blocked dropping `customComponents.js`.
2. **`useAppStatus('openregister')`** verified live on the dev
   `nextcloud` container (`larpinq-adopt-or-abstractions §1.5`, W24
   2026-06-12): `occ app:list` reports `openregister 0.2.13-unstable.90`
   enabled, OCS capabilities expose the `openregister` block, so
   dependency-check phase in `CnAppRoot` passes for Larpinq on the
   current baseline.
3. **`CnPageRenderer` mount path** already in `src/main.js` —
   `customComponents` is the only remaining bridge.

This change drops `src/customComponents.js`, mounts `CnAppRoot` directly
in `src/main.js`, and routes every slot/actions/section override through
the `registry.js` `kind:` field that ADR-036 now resolves.

## What Changes

### Mount `CnAppRoot`

1. Replace the bespoke `Vue.use(...)` + `CnPageRenderer` mount in
   `src/main.js` with `CnAppRoot` (the manifest + registry are passed as
   props, identical to other Tier-4 apps).
2. Remove `src/customComponents.js`.
3. Audit every entry in `src/registry.js` and ensure each non-page entry
   carries a `kind:` discriminator (`'widget'` / `'tab'` / `'header'` /
   `'actions'` / `'section'`).
4. Drop the local `Vue.component(...)` registrations the
   `customComponents.js` bridge used to do.

## Out of Scope

- The shared library updates themselves (`CnAppRoot`, the ADR-036
  resolver) — they ship under nextcloud-vue's own openspec changes.
- i18n / multi-tenancy badge rendering — tracked in
  `larpinq-adopt-or-abstractions §3.5 / §4.5`.

## Impact

- **Modified**: `src/main.js`, `src/registry.js`
- **Removed**: `src/customComponents.js`
- **Verification**: `npm run check:manifest` clean,
  `composer check:strict` clean, live-deploy regression check that all
  manifest-declared menu items, pages, widget slots, and detail tabs
  still render against the `nextcloud` container.
