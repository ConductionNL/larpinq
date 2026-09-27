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
- The register slug is frozen as `larpingapp` (`register.d/README.md`).

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
