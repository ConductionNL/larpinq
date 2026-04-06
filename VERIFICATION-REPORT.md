# OpenSpec Restructure Verification

**Date**: 2026-03-20
**Verifier**: Automated audit via Claude Code

## Summary

| App | Specs | Changes | Wrong Status | Missing Status | Delta Format in specs/ | Missing Purpose | Issues |
|-----|-------|---------|-------------|----------------|----------------------|-----------------|--------|
| openregister | 49 | 2 | 17 | 15 | 39 | 6 | CRITICAL |
| pipelinq | 29 | 2 | 0 | 29 | 8 | 0 | CRITICAL |
| procest | 37 | 1 | 0 | 37 | 11 | 0 | CRITICAL |
| larpingapp | 9 | 0 | 0 | 0 | 0 | 0 | PASS |
| mydash | 9 | 0 | 0 | 1 | 0 | 0 | MINOR |
| opencatalogi | 3 | 0 | 0 | 3 | 1 | 0 | WARNING |
| nldesign | 8 | 0 | 0 | 2 | 1 | 1 | WARNING |
| docudesk | 13 | 0 | 3 | 1 | 0 | 1 | WARNING |
| openconnector | 4 | 0 | 3 | 1 | 0 | 0 | WARNING |
| softwarecatalog | 0 | 0 | 0 | 0 | 0 | 0 | N/A (no openspec) |
| nextcloud-vue | 23 | 1 | 0 | 23 | 13 | 0 | CRITICAL |
| root openspec | 4 | 17 | 0 | 0 | 0 | 0 | PASS |

**Legend:**
- **Wrong Status**: specs/ contains `status: draft`, `status: ready`, `status: active`, or `status: proposed` (should only be `implemented`, `reviewed`, or `partial`)
- **Missing Status**: No `status:` field in YAML frontmatter at all
- **Delta Format**: Uses `## ADDED Requirements` (change format) instead of `## Requirements` (main spec format)

---

## Spot Checks

### openregister -- specs/

- **faceting-configuration**: OK -- `status: implemented`, proper format with `## Purpose`, `## Requirements`, `### Requirement:`, `#### Scenario:`
- **workflow-integration**: OK -- `status: implemented`, proper format
- **no-code-app-builder**: FAIL -- `status: draft`, uses `## ADDED Requirements` (delta format). Describes entirely unimplemented feature ("Gap identified in cross-platform analysis"). Should be in changes/
- **api-test-coverage**: FAIL -- `status: draft`, missing `## Purpose`. Describes aspirational 100% coverage target, not implemented feature
- **auth-system**: FAIL -- no status in frontmatter, uses `## ADDED Requirements`. Describes SSO/JWT features that may not be fully implemented
- **archivering-vernietiging**: FAIL -- `status: draft`. Government archiving feature, not implemented

### openregister -- changes/

- **deprecate-published-metadata**: OK -- has .openspec.yaml, proposal.md, design.md, tasks.md, specs/deprecate-published-metadata/spec.md. Complete artifact chain
- **unit-test-coverage-phase2**: OK -- has .openspec.yaml, proposal.md, design.md, tasks.md, specs/unit-test-coverage/spec.md. Complete artifact chain

### pipelinq -- specs/

- **pipeline**: FAIL -- no status field. Content describes implemented kanban feature but lacks frontmatter metadata
- **admin-settings**: FAIL -- no status field. Likely implemented but missing metadata
- **prospect-discovery**: FAIL -- no status field but actually IS implemented (routes exist in code). Uses `## ADDED Requirements` (delta format)
- **kennisbank**: FAIL -- no status field, describes V1/Enterprise feature NOT implemented. Should be in changes/
- **public-intake-forms**: FAIL -- no status field, describes unimplemented feature. Uses `## ADDED Requirements`. Should be in changes/
- **kcc-werkplek**: FAIL -- no status field, describes unimplemented KCC agent feature. Should be in changes/

### pipelinq -- changes/

- **2026-03-20-prometheus-metrics**: FAIL -- missing .openspec.yaml, proposal.md, tasks.md. Only has empty specs/prometheus-metrics/ directory
- **2026-03-20-register-i18n**: FAIL -- missing .openspec.yaml, proposal.md, tasks.md. Only has empty specs/register-i18n/ directory

### procest -- specs/

- **case-management**: FAIL -- no status field. Content describes core implemented feature but lacks metadata
- **dashboard**: FAIL -- no status field. Likely implemented
- **ai-assisted-processing**: FAIL -- no status field. Describes unimplemented AI feature. Should be in changes/
- **appointment-scheduling**: FAIL -- no status field. Describes unimplemented appointment feature. Should be in changes/
- **bw-parafering**: FAIL -- no status field. Describes unimplemented approval workflow. Should be in changes/

### procest -- changes/

- **zgw-business-rules-compliance**: PARTIAL -- has .openspec.yaml and proposal.md but MISSING specs/ directory and tasks.md. Incomplete artifact chain

### larpingapp -- specs/

- **character-management**: OK -- `status: reviewed`, proper format with `## Purpose`, `## Requirements`, tabular requirements with Status column
- **events-players**: OK -- `status: reviewed`, proper format

### larpingapp -- changes/

- No changes directory exists (acceptable if no pending changes)

### mydash -- specs/

- **dashboards**: OK -- `status: reviewed`, proper format
- **prometheus-metrics**: FAIL -- no status field (but feature IS implemented per code check)

### mydash -- changes/

- No changes directory exists

### opencatalogi -- specs/

- **woo-transparency**: FAIL -- no status field. Uses `## Requirements` (correct format) but no frontmatter
- **prometheus-metrics**: FAIL -- no status field (but IS implemented per code check)
- **register-i18n**: FAIL -- no status field (but IS implemented -- l10n/ files exist)

### nldesign -- specs/

- **theming-sync**: OK -- `status: reviewed`, proper format
- **docs-content**: FAIL -- no status field, no `## Purpose`, uses `## ADDED Requirements` (delta format)
- **prometheus-metrics**: FAIL -- no status field (but IS implemented per code check)

### nldesign -- changes/

- Only archive/ exists (no active changes, acceptable)

### docudesk -- specs/

- **pdf-generation**: OK -- `status: reviewed`, proper format
- **anonymization**: OK -- `status: reviewed`, proper format
- **document-creatie-sjablonen**: FAIL -- `status: proposed`. Describes unimplemented document creation workflow. Should be in changes/
- **document-signing**: FAIL -- `status: proposed`. Describes unimplemented signing feature (76% tender demand). Should be in changes/
- **register-i18n**: FAIL -- `status: proposed` but i18n IS implemented (l10n files exist). Wrong status value
- **woo-transparency**: FAIL -- no status field, missing metadata

### docudesk -- changes/

- Only archive/ exists

### openconnector -- specs/

- **dso-omgevingsloket**: FAIL -- `status: proposed`. Entirely unimplemented DSO integration. Should be in changes/
- **stuf-adapter**: FAIL -- `status: proposed`. Entirely unimplemented StUF adapter. Should be in changes/
- **ibabs-notubiz-connector**: FAIL -- `status: proposed`. Entirely unimplemented connector. Should be in changes/
- **prometheus-metrics**: FAIL -- no status field (but IS implemented per code check)

### openconnector -- changes/

- No changes directory exists (but 3 of 4 specs SHOULD be changes)

### nextcloud-vue -- specs/

- **store**: FAIL -- no status field. Content describes implemented Pinia store but lacks metadata
- **component-library**: FAIL -- no status field. Describes implemented component library
- **architecture-guide**: FAIL -- no status field, uses `## ADDED Requirements` (delta format)
- **widget-renderer**: FAIL -- no status field, uses `## ADDED Requirements` (delta format)
- All 23 specs lack status frontmatter

### nextcloud-vue -- changes/

- **unified-grid-widget-system**: OK -- has .openspec.yaml, proposal.md, design.md, tasks.md with checklist items, specs/detail-page-grid/ and specs/grid-widget-system/. Complete artifact chain

### root openspec -- specs/

- **i18n-infrastructure**: OK -- `status: reviewed`, proper format
- **i18n-dutch-translations**: OK -- `status: reviewed`, proper format
- All 4 specs have proper status

### root openspec -- changes/

- **no-code-app-builder**: OK -- complete artifact chain (.openspec.yaml, proposal.md, design.md, tasks.md, specs/)
- **activity-timeline**: OK -- complete artifact chain
- All 17 changes have complete artifact chains with .openspec.yaml, proposal.md, design.md, tasks.md, and specs/

---

## Issues Found

### CRITICAL Issues

1. **openregister: 17 specs with wrong status in specs/**
   - 14 with `status: draft` (api-test-coverage, archivering-vernietiging, avg-verwerkingsregister, besluiten-management, document-zaakdossier, dso-omgevingsloket, geo-metadata-kaart, larping-skill-widget, no-code-app-builder, open-raadsinformatie, product-service-catalog, rapportage-bi-export, urn-resource-addressing, zgw-api-mapping)
   - 2 with `status: ready` (computed-fields, referential-integrity)
   - 1 with `status: active` (unit-test-coverage)
   - These describe unimplemented or partially-implemented features and should be in changes/

2. **openregister: 39 of 49 specs use `## ADDED Requirements` (delta format)**
   - Main specs should use `## Requirements`, not `## ADDED Requirements`
   - Delta format is for change specs only

3. **pipelinq: All 29 specs missing status field**
   - Mix of implemented features (pipeline, dashboard, admin-settings, client-management) and unimplemented features (kennisbank, kcc-werkplek, public-intake-forms, email-calendar-sync)
   - Unimplemented features should be in changes/, not specs/

4. **procest: All 37 specs missing status field**
   - Mix of implemented features (case-management, dashboard, task-management) and unimplemented features (ai-assisted-processing, appointment-scheduling, bw-parafering, legesberekening, etc.)
   - Many unimplemented features should be in changes/

5. **nextcloud-vue: All 23 specs missing status field, 13 use delta format**
   - All specs describe implemented components/utilities but lack metadata

6. **Unimplemented features left in specs/ (should be changes/)**
   - openregister: ~14 draft specs (archivering-vernietiging, avg-verwerkingsregister, besluiten-management, etc.)
   - pipelinq: ~10 specs (kennisbank, kcc-werkplek, klantbeeld-360, public-intake-forms, email-calendar-sync, contactmomenten-rapportage, terugbel-taakbeheer, crm-workflow-automation, contact-relationship-mapping, product-catalog-quoting)
   - procest: ~15 specs (ai-assisted-processing, appointment-scheduling, bw-parafering, case-definition-portability, case-email-integration, complaint-management, consultation-management, legesberekening, mijn-overheid-integration, milestone-tracking, mobiel-inspectie, multi-tenant-saas, stuf-support, vth-module, woo-case-type)
   - docudesk: 2 specs (document-creatie-sjablonen, document-signing)
   - openconnector: 3 specs (dso-omgevingsloket, stuf-adapter, ibabs-notubiz-connector)

### WARNING Issues

7. **Incomplete changes in pipelinq**
   - `2026-03-20-prometheus-metrics`: empty spec directory, missing .openspec.yaml, proposal.md, tasks.md
   - `2026-03-20-register-i18n`: empty spec directory, missing .openspec.yaml, proposal.md, tasks.md

8. **Incomplete change in procest**
   - `zgw-business-rules-compliance`: has .openspec.yaml and proposal.md but missing specs/ directory and tasks.md

9. **opencatalogi: All 3 specs missing status field**
   - Features are implemented but specs lack metadata

10. **docudesk: register-i18n has `status: proposed` but IS implemented**
    - l10n/ directory exists with translations; status should be `implemented` or `reviewed`

11. **prometheus-metrics specs across 6 apps lack status field**
    - Feature IS implemented in all apps (MetricsController.php exists) but specs have no status metadata

### MINOR Issues

12. **openregister: 6 specs missing `## Purpose` section**
    - api-test-coverage, deep-link-registry, deprecate-published-metadata, larping-skill-widget, mariadb-ci-matrix, unit-test-coverage

13. **nldesign: docs-content spec missing `## Purpose` and status**
    - Uses `## ADDED Requirements` delta format

14. **docudesk: woo-transparency spec missing status field**

15. **softwarecatalog: No openspec directory at all**

16. **4 apps have no changes/ directory** (larpingapp, mydash, opencatalogi, openconnector)
    - May be acceptable if no pending work, but openconnector has 3 proposed specs that should be changes

---

## Conclusion

**FAIL** -- The restructure has significant issues across most apps.

**What went well:**
- larpingapp: Clean pass -- all 9 specs have `status: reviewed`, proper format, no issues
- root openspec: Clean pass -- 4 specs with proper status, 17 changes with complete artifact chains
- openregister changes: Both have complete artifact chains
- nextcloud-vue changes: unified-grid-widget-system has complete artifact chain
- Archive directories exist and contain historical changes

**What failed:**
- **Status metadata missing in 135 of 189 total specs** (71%) -- pipelinq (29/29), procest (37/37), nextcloud-vue (23/23), opencatalogi (3/3), and partially openregister, nldesign, docudesk, openconnector
- **~44 unimplemented feature specs still in specs/** instead of changes/ -- across openregister (14), pipelinq (10+), procest (15+), docudesk (2), openconnector (3)
- **73 specs use delta format (`## ADDED Requirements`) in main specs/** -- should use `## Requirements` for non-change specs
- **3 incomplete changes** missing required artifacts (pipelinq x2, procest x1)
- Only larpingapp and root openspec fully pass all checks
