# OpenSpec Structure Verification Report

**Date:** 2026-03-20
**Scope:** All 12 apps + root openspec directory

---

## Summary

| App | Specs | Missing `status:` | `draft`/`proposed` | `## ADDED Requirements` | Changes | Missing `.openspec.yaml` |
|-----|------:|-------------------:|--------------------:|------------------------:|--------:|-------------------------:|
| openregister | 49 | 15 | 14 draft | 39 | 2 | 0 |
| pipelinq | 29 | 29 | 0 | 8 | 2 | **2** |
| procest | 37 | 37 | 0 | 11 | 1 | 0 |
| larpingapp | 9 | 0 | 0 | 0 | 0 | 0 |
| mydash | 9 | 1 | 0 | 0 | 0 | 0 |
| opencatalogi | 3 | 3 | 0 | 1 | 0 | 0 |
| nldesign | 8 | 2 | 0 | 1 | 0 | 0 |
| docudesk | 13 | 1 | 3 proposed | 0 | 0 | 0 |
| openconnector | 4 | 1 | 3 proposed | 0 | 0 | 0 |
| softwarecatalog | -- | -- | -- | -- | -- | -- |
| zaakafhandelapp | -- | -- | -- | -- | -- | -- |
| nextcloud-vue | 23 | 23 | 0 | 13 | 1 | 0 |
| **root openspec** | 4 | 0 | 0 | 0 | 17 | 0 |
| **TOTALS** | **188** | **112** | **20** | **73** | **23** | **2** |

---

## Apps Without openspec/ Directory

- **softwarecatalog** -- No `openspec/` directory exists
- **zaakafhandelapp** -- No `openspec/` directory exists

---

## CLEAN Apps (No Issues)

- **larpingapp** -- 9 specs, all have `status:`, no `ADDED Requirements`, no changes. Fully clean.
- **root openspec** -- 4 specs, all have `status:`, 17 changes all with `.openspec.yaml`. Fully clean.

---

## Issue 1: Specs Missing `status:` Frontmatter (112 specs)

### openregister (15 missing)
- `data-sync-harvesting`, `notificatie-engine`, `openapi-generation`, `rbac-zaaktype`, `mock-registers`, `rbac-scopes`, `built-in-dashboards`, `realtime-updates`, `production-observability`, `oas-validation`, `reference-existence-validation`, `row-field-level-security`, `auth-system`, `event-driven-architecture`, `data-import-export`

### pipelinq (29 missing -- ALL specs)
- Every spec in pipelinq lacks `status:` frontmatter.

### procest (37 missing -- ALL specs)
- Every spec in procest lacks `status:` frontmatter.

### mydash (1 missing)
- `prometheus-metrics`

### opencatalogi (3 missing -- ALL specs)
- `register-i18n`, `prometheus-metrics`, `woo-transparency`

### nldesign (2 missing)
- `prometheus-metrics`, `docs-content`

### docudesk (1 missing)
- `woo-transparency`

### openconnector (1 missing)
- `prometheus-metrics`

### nextcloud-vue (23 missing -- ALL specs)
- Every spec in nextcloud-vue lacks `status:` frontmatter.

---

## Issue 2: Specs with `status: draft` or `status: proposed` (20 specs)

### openregister -- 14 draft
- `api-test-coverage`, `archivering-vernietiging`, `avg-verwerkingsregister`, `besluiten-management`, `document-zaakdossier`, `dso-omgevingsloket`, `geo-metadata-kaart`, `larping-skill-widget`, `no-code-app-builder`, `open-raadsinformatie`, `product-service-catalog`, `rapportage-bi-export`, `urn-resource-addressing`, `zgw-api-mapping`

### docudesk -- 3 proposed
- `document-creatie-sjablonen`, `document-signing`, `register-i18n`

### openconnector -- 3 proposed
- `dso-omgevingsloket`, `ibabs-notubiz-connector`, `stuf-adapter`

---

## Issue 3: Specs Using `## ADDED Requirements` (73 specs)

This format belongs only in change-level delta specs, NOT in `specs/` canonical specs.

| App | Count | Affected Specs |
|-----|------:|----------------|
| openregister | 39 | audit-trail-immutable, auth-system, avg-verwerkingsregister, besluiten-management, built-in-dashboards, computed-fields, content-versioning, data-import-export, data-sync-harvesting, deletion-audit-trail, document-zaakdossier, dso-omgevingsloket, event-driven-architecture, geo-metadata-kaart, graphql-api, larping-skill-widget, mcp-discovery, no-code-app-builder, notificatie-engine, oas-validation, open-raadsinformatie, openapi-generation, product-service-catalog, production-observability, rapportage-bi-export, rbac-scopes, rbac-zaaktype, realtime-updates, reference-existence-validation, referential-integrity, register-i18n, row-field-level-security, schema-hooks, urn-resource-addressing, webhook-payload-mapping, workflow-in-import, workflow-integration, zoeken-filteren, archivering-vernietiging |
| pipelinq | 8 | contact-relationship-mapping, crm-workflow-automation, dashboard, lead-product-link, product-catalog-quoting, product-catalog, prospect-discovery, public-intake-forms |
| procest | 11 | case-email-integration, case-sharing-collaboration, mijn-overheid-integration, multi-tenant-saas, pipelinq-app-scaffold, pipelinq-client-management, pipelinq-object-store, procest-app-scaffold, procest-case-management, procest-object-store, woo-case-type |
| opencatalogi | 1 | woo-transparency |
| nldesign | 1 | docs-content |
| nextcloud-vue | 13 | architecture-guide, component-reference, dashboard-grid, dashboard-page, docs-site-infrastructure, getting-started-guide, integration-guides, tile-widget, use-dashboard-view, use-detail-view, use-list-view, widget-renderer, widget-wrapper |

---

## Issue 4: Changes Missing `.openspec.yaml` (2 changes)

### pipelinq
- `openspec/changes/2026-03-20-prometheus-metrics/` -- missing `.openspec.yaml`
- `openspec/changes/2026-03-20-register-i18n/` -- missing `.openspec.yaml`

---

## Overall Assessment

| Check | Pass/Fail | Details |
|-------|-----------|---------|
| Every spec has `status:` frontmatter | **FAIL** | 112 of 188 specs (59.6%) lack `status:` |
| No `## ADDED Requirements` in `specs/` | **FAIL** | 73 of 188 specs (38.8%) contain change-only format |
| Every change has `.openspec.yaml` | **FAIL** | 2 of 23 changes missing `.openspec.yaml` |
| No `draft`/`proposed` specs in `specs/` | **FAIL** | 20 specs have `draft` or `proposed` status |
| All apps have `openspec/` directory | **FAIL** | 2 apps missing: softwarecatalog, zaakafhandelapp |

### Severity Ranking
1. **Critical:** 112 specs missing `status:` frontmatter (pipelinq, procest, nextcloud-vue entirely)
2. **Critical:** 73 specs using `## ADDED Requirements` in canonical spec location
3. **High:** 20 specs still in `draft`/`proposed` status
4. **Medium:** 2 pipelinq changes missing `.openspec.yaml`
5. **Low:** 2 apps (softwarecatalog, zaakafhandelapp) have no `openspec/` directory
