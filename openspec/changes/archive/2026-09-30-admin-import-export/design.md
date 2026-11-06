# Design: admin-import-export

## Context

Read at development `77c85f0`.

- Larpinq's index pages (`src/manifest.json`: Characters, Players,
  Abilities, Skills, Items, Conditions, Effects, Events, Settings, XpAwards)
  set register and schema only. Nothing exports or imports user data; the
  `settings#reimport` route reloads larpinq's bundled register definition.
- `@conduction/nextcloud-vue` `CnIndexPage`: `allowExport` renders an Export
  menu when the schema is `exportable: true` and calls OpenRegister's export
  endpoint with the route query as filters; `showMassImport` and
  `showMassExport` default to true for the selection strip.
- OpenRegister `data-import-export`: CSV and Excel import and export per
  schema, whole-register Excel export (one sheet per schema slug), multi-sheet
  import matched by sheet title, upsert and deduplication, error reports,
  templates, property rules on export columns, RBAC on both directions.
- The register slug is `larpinq` (`lib/Settings/larpinq_register.json`); the
  register-level endpoints take its numeric id, which the larpinq settings
  carry (`setting_register`).

## Goals / Non-Goals

**Goals**: every list exports; characters and players import; a campaign goes
out and comes back in one file.

**Non-Goals**: files, foreign formats, schedules.

## Decisions

### D1. Exportable schemas

The fragment sets `exportable: true` on every larpinq schema. That is the
switch `allowExport` checks.

### D2. Lists

Every index page (in place) gets `allowExport: true` and `exportFormats:
["csv", "xlsx"]`. Characters and Players get `showMassImport: true` with
`importOptions` naming CSV and Excel and the template download; the import
action is shown to game masters only (larpinq's RBAC on create already refuses
others at OpenRegister).

### D3. The campaign

Two header actions on the Settings (Worlds) index for game masters: "Export
campaign" opens OpenRegister's register export in Excel for register
`larpingapp` (one sheet per schema); "Import campaign" opens OpenRegister's
register import dialog for the same register, which matches sheets to schema
slugs and upserts by id. The page text says files are not included and advises
exporting first.

Alternative: a larpinq ZIP exporter with JSON per schema and files. Rejected:
it would duplicate OpenRegister's export (hydra ADR-022), and files are
already in Nextcloud Files, which the group's Nextcloud backup covers.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Exportable schemas | Declarative, register fragment | A schema flag. |
| Export and import on lists and for the campaign | Declarative, manifest options and header actions over OpenRegister endpoints | OpenRegister owns import and export. |

No larpinq PHP.

## Seed data

No new schema. The Playwright test exports the Characters list as CSV and
imports a two-row players CSV built from the template.

## Risks / Trade-offs

- [OpenRegister spec in progress] Register-level import and templates are
  listed as implemented or in progress in `data-import-export`; the tasks verify
  each endpoint on the dev instance before the page wires it.

## Migration

None.

## Open Questions

None.

## Changes at build (2026-09-30, read at development `3231ec5`)

The design did not fit the code at HEAD in four places. Fixed here:

1. **Where the flag lives.** OpenRegister keeps the flag at
   `configuration.exportable` (Schema::jsonSerialize mirrors it; #4131), so the
   fragment sets it there, on all eleven schemas including the
   fragment-added `attendance`.
2. **Who may import.** CnIndexPage's list import and the campaign import both
   post to `POST /api/registers/{id}/import`, which requires permission to
   manage the register. The larpinq register has no `manage` rule, so that is
   administrators only, not game masters. The import actions are therefore
   removed for non-administrators (`applyImportGate` in
   `src/services/campaignPortability.js`, applied in `src/main.js` on
   `getCurrentUser().isAdmin`) rather than offered and refused. Widening this
   to the `gamemasters` group means a register `authorization.manage` rule,
   which also lets them change the register's schemas: a security default,
   asked of Ruben, not taken here.
3. **The import was on every list.** `showMassImport` defaults to true, so
   every index page already offered an import. Every page but Characters and
   Players now sets it false.
4. **The campaign actions.** CnIndexPage header actions cannot open an
   OpenRegister dialog and the register export takes a numeric id, so the two
   Worlds actions are `kind: 'handler'` registry entries
   (`src/services/campaignActions.js`): export navigates to
   `GET /api/registers/{id}/export?format=excel`; import picks a workbook,
   posts it with `type=excel` and shows the counts in a toast. The advice to
   export first lives in the feature documentation, because a header action
   has no page text. `exportFormats` is left at its default, which already
   names CSV and Excel. Templates are not wired: the list import dialog in
   nextcloud-vue 2.57.1 has no template link, and a CSV with the list's own
   column names (an export) imports.

So the change is not purely declarative: two small frontend modules, no
larpinq PHP.
