---
status: draft
---

# LarpingApp — manifest Tier-4 graduation

## Purpose

Specify the requirements for completing LarpingApp's adoption of the
fleet-wide app-manifest contract: drop `src/customComponents.js`,
mount `CnAppRoot` directly, and surface every slot / actions / section /
header override through the kind-tagged `src/registry.js`.

## ADDED Requirements

### Requirement: `CnAppRoot` SHALL be the boot entry point

LarpingApp SHALL mount `CnAppRoot` (from `@conduction/nextcloud-vue`)
directly in `src/main.js`, passing `manifest` and `registry` as props.
The legacy `CnPageRenderer` + `Vue.use(...)` boilerplate SHALL be
removed.

#### Scenario: App boots through CnAppRoot

- WHEN the app entry point loads
- THEN `CnAppRoot` is the root component
- AND `src/manifest.json` is the declarative source of nav, routes,
  and slot wiring
- AND `src/registry.js` is the only place app-local components are
  resolved from

### Requirement: `src/customComponents.js` SHALL be removed

The bespoke `customComponents.js` bridge SHALL be deleted. Every
component it used to register via `Vue.component(...)` SHALL be
resolved from `src/registry.js` via the ADR-036 kind-tagged lookup.

#### Scenario: customComponents.js is removed

- WHEN the working tree is searched (`grep -rn customComponents src/`)
- THEN no occurrences are returned
- AND every manifest slot reference resolves through `registry.js`

### Requirement: Every registry entry SHALL carry a `kind:` field

`src/registry.js` SHALL set `kind:` on every entry. Page entries keep
`kind:'page'`; non-page entries SHALL set one of `'widget'`, `'tab'`,
`'header'`, `'actions'`, `'section'` matching ADR-036.

#### Scenario: Slot lookup resolves a non-page entry

- WHEN the manifest references a `tab` component by name
- THEN `resolveCustomComponent(name, { kind:'tab' })` returns the
  matching `registry.js` entry
