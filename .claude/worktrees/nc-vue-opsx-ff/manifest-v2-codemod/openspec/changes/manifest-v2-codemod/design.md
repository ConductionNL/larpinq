# Design: manifest-v2-codemod

## Context

The `manifest-v2-schema` change (spec 1) establishes the v2 JSON Schema and `validateManifest()` export. Fleet apps (procest, pipelinq, softwarecatalog, decidesk at minimum) run v1.x manifests today. This spec adds a `manifest-migrate` CLI that automates the mechanical parts of the v1→v2 migration matrix defined in hydra ADR-036 and its `specs/manifest-v2/spec.md`.

The CLI lives inside the `@conduction/nextcloud-vue` repo rather than a separate npm package. It is a Node.js script compiled from TypeScript by the same Rollup/tsc build already in place, and exposed via a `bin` entry in `package.json`. It is a developer tool — never imported at runtime — so it has no impact on library bundle size or tree-shaking.

**Constraints:**
- Library uses Jest today (not Vitest); new CLI tests use Jest to stay consistent with the rest of the test suite
- Build is Rollup; CLI output must be an executable Node.js CJS module (not ESM) so `node dist/cli/manifest-migrate.js` works without `--experimental-vm-modules`
- The CLI must re-use `validateManifest()` from spec 1, not duplicate schema loading

## Goals / Non-Goals

**Goals:**
- Automate all mechanical v1→v2 transformation rules from the migration matrix
- Produce a machine-readable + human-readable report of items that require manual migration
- Validate the output against the v2 schema before writing it
- Be idempotent: running the CLI on a v2 manifest is a no-op (exits 0, writes nothing)
- Provide a test corpus of four real fleet manifests so regressions surface immediately

**Non-Goals:**
- Per-app migration execution (each app runs the codemod in its own opsx change)
- GUI / interactive wizard (CLI only)
- Automatic merging of app-supplied git branches
- Migrating non-manifest config files
- A separate `@conduction/manifest-migrate` npm package (can extract later if ecosystem demand appears; bin-in-nc-vue is simpler for now)

## Decisions

### D1: CLI library — `commander` as a minimal devDependency

**Decision:** Use `commander` as a devDependency for argument parsing.

**Rationale:** `commander` is the de-facto standard in the Node/TS ecosystem, has zero transitive dependencies, is well-typed (`commander` ships its own types), and is already familiar to maintainers. Hand-rolling flag parsing is brittle. `yargs` adds significant complexity that the four flags here don't justify. Because the CLI is a dev tool (not imported in the browser bundle), adding `commander` to `devDependencies` has zero production footprint.

**Alternative considered:** Hand-rolled `process.argv` parsing — rejected due to fragility and lack of `--help` generation.

### D2: Package location — bin entry in nc-vue, not a separate package

**Decision:** Add `"manifest-migrate": "dist/cli/manifest-migrate.js"` to the `bin` field of `@conduction/nextcloud-vue/package.json`. No separate npm package.

**Rationale:** The codemod shares schema files, type definitions, and `validateManifest()` directly with the library. Extracting it to a separate package creates a versioning dependency (codemod version must match schema version). The migration is a one-time operation per app; there's no ecosystem demand for installing the codemod independently. Can always extract to a standalone package later with a deprecation notice on the bin.

**Alternative considered:** `@conduction/manifest-migrate` standalone npm package — rejected for added complexity; revisit if third-party app developers request it.

### D3: Transformation strategy — flat rule pipeline, not strategy classes

**Decision:** Implement each migration matrix row as a pure function `transformX(manifest) → manifest` composed in a pipeline. No strategy class hierarchy.

**Rationale:** The transformation rules are straightforward tree mutations with no shared state between them. A flat pipeline is easier to test (each function is independently invocable with a plain object), easier to read, and avoids premature abstraction. The manifest tree is small (< 1 KB for typical apps); performance is not a concern.

**Alternative considered:** Per-page-type strategy classes — rejected; the transformations cut across page types (e.g., `$schema` insertion is manifest-level) and do not map cleanly to a strategy pattern.

### D4: Test corpus location — `tests/fixtures/v1-manifests/`

**Decision:** Commit four real fleet manifests (procest, pipelinq, softwarecatalog, decidesk HEAD snapshots from their `beta`/`development` branches at capture time) into `tests/fixtures/v1-manifests/`.

**Rationale:** The manifests are not secrets; they are already public in their respective repos. Having them as static fixtures means CI does not need cross-repo network access, and regressions surface deterministically. The fixture files are renamed with a `-v1` suffix to make the corpus intent clear.

### D5: Report format — Markdown file

**Decision:** `--report <path>` writes a Markdown file (`.md`) with sections per page, a table of TODO items, and suggested registry entries for trivial custom pages.

**Rationale:** The report is read by a human making final migration decisions. Markdown renders in GitHub, VS Code, and any text editor. A JSON report would require a separate viewer. Markdown is sufficient.

### D6: `--dry-run` output — to stdout

**Decision:** `--dry-run` prints the transformed manifest JSON to stdout and the report to stderr. No files are written.

**Rationale:** Follows POSIX convention (output to stdout, diagnostic messages to stderr). Allows piping the dry-run output: `manifest-migrate --dry-run --input src/manifest.json | jq .`

### D7: Idempotency via `$schema` field detection

**Decision:** When the input manifest's `$schema` field points to the v2 schema URL, the CLI validates the manifest against v2 and exits 0 without transformation. It does not re-run transformations.

**Rationale:** Idempotency is essential for CI integration (a lint step that runs the codemod and checks for changes must not produce spurious diffs). Detecting by `$schema` URL is already the pattern established in `useAppManifest.js` by spec 1.

## Risks / Trade-offs

| Risk | Mitigation |
|------|-----------|
| Codemod incompleteness — real v1.3.0 manifests may use field combinations not covered by the matrix | Four-corpus CI test catches gaps before release; `_note` markers and report flag unknowns conservatively |
| False-positive custom flagging — a trivial custom page is flagged as non-trivial | Heuristic: flag as trivial only when `type: "custom"` and `component` resolves to an exact entry in `customComponents`; everything else is flagged for human review |
| `commander` version conflicts — consuming app already has a different `commander` version | `commander` is a devDependency; it is not part of the published bundle. No conflict risk at runtime. |
| Large manifests — a future app with 50+ pages and 200+ widgets | Pipeline approach processes each rule in O(n) per page; no performance concern for realistic sizes |
| Separate package extraction cost — if needed later | The bin entry is the only coupling point; extraction requires only moving files and updating the `package.json` path, no API changes |

## Migration Plan

1. Implement CLI in `src/cli/manifest-migrate.js` (this spec)
2. Publish with next `@conduction/nextcloud-vue` beta release
3. Each consumer app runs `npx manifest-migrate --input src/manifest.json --report MIGRATION_REPORT.md` in its own opsx change
4. No rollback strategy needed — CLI is additive and the v1 manifest is preserved unless `--output` overwrites it (or the default same-as-input path is used)

## Open Questions

None. All decisions above are resolved. Per-app execution is deferred to individual app opsx changes.
