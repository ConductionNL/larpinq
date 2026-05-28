---
kind: code
depends_on:
  - manifest-v2-schema
---

# Proposal: manifest-v2-codemod

## Why

Fleet apps currently carry v1.x manifests. Once the v2 schema (spec 1) and the v2 renderer (spec 2) land on `beta`, every app in the Conduction fleet — procest, pipelinq, softwarecatalog, decidesk, and more — will need its `src/manifest.json` migrated before it can take advantage of unified widgets, per-slot grid coordinates, and typed action discriminators. Hand-migrating eleven page-type variations across six+ apps is error-prone and slow. A CLI codemod gives every consumer a zero-friction automated upgrade path: one command to transform, validate, and flag what needs a human touch. This is spec 3 of 3 in the v2 manifest chain (see hydra ADR-036, PR #305).

**Chain summary:**

| # | Change | Kind | Dependency |
|---|--------|------|------------|
| 1 | `manifest-v2-schema` | config | — |
| 2 | `manifest-v2-renderer` | code | manifest-v2-schema |
| 3 | **`manifest-v2-codemod`** (this spec) | code | manifest-v2-schema |

Specs 2 and 3 are parallel; neither depends on the other.

## What Changes

- **New file** `src/cli/manifest-migrate.js` — CLI entry point compiled to `dist/cli/manifest-migrate.js`
- **New bin entry** `"manifest-migrate"` in `package.json` pointing to the compiled output
- **CLI flags**: `--input <path>`, `--output <path>` (default: overwrite input), `--validate-only`, `--report <path>`, `--dry-run`
- **Transformation functions** for every row in the hydra `specs/manifest-v2/spec.md` migration matrix:
  - Merge `widgets[]` + `layout[]` per dashboard page
  - Lift `sidebarTabs[].widgets[]` to `slot: "sidebar"` with `tabGroup`
  - Flatten settings `sections[].widgets[]` to `slot: "section:<id>"`
  - Flatten settings `tabs[]` to `slot: "tab:<id>"`
  - Migrate `cardComponent` → `card-grid` built-in widget entry
  - Add explicit `type: "handler"` on v1.3.0 actions where omitted
  - Carry forward `dataSource`, `@resolve:`, `dynamicSource`, named-view sidebar verbatim
  - Convert trivial `type: "custom"` → `kind: "page"` registry entry suggestion in report
  - Flag non-trivial `type: "custom"` with `_note` TODO marker
  - Insert/update `$schema` to v2 schema URL
  - Map `customComponents` → `registry` with `kind` prompts
- **Test corpus** `tests/fixtures/v1-manifests/` — captured HEAD snapshots of `procest`, `pipelinq`, `softwarecatalog`, and `decidesk` manifests
- **Jest tests** — per-transformation unit tests + corpus integration tests asserting v2 schema validity post-transform
- **New doc** `docs/migrating-to-v2.md` — full migration guide (CLI usage, manual touch-ups, common pitfalls)
- **Updated doc** `docs/architecture/manifest.md` — adds codemod reference

## Capabilities

### New Capabilities

- `manifest-v2-codemod`: CLI binary `manifest-migrate` that transforms v1 manifests to v2, validates input/output against their declared schemas, and produces a human-readable report of items requiring manual attention

### Modified Capabilities

(none — codemod is entirely additive; no existing library behavior changes)

## Impact

- **`src/cli/manifest-migrate.js`** — new file; compiled and exposed as `bin`
- **`package.json`** — new `bin` entry and `commander` devDependency (or production dep if packaged standalone)
- **`tests/fixtures/v1-manifests/`** — four captured manifest snapshots added as test corpus
- **`tests/cli/`** — new jest test files
- **`docs/migrating-to-v2.md`** — new migration guide
- **`docs/architecture/manifest.md`** — codemod section added
- **Consumers** — zero runtime impact; CLI is a build/migration tool, not imported by the library bundle
- **Tree-shaking** — CLI entry is not part of the library's barrel (`src/index.js`); no bundle size impact
