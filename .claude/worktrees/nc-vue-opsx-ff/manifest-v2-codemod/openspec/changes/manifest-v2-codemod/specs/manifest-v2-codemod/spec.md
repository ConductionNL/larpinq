# Specification: manifest-v2-codemod

## ADDED Requirements

### Requirement: CLI binary shape and flags

The library SHALL expose a `manifest-migrate` executable (via the `bin` field in `package.json`) that accepts the following flags:

- `--input <path>` (required) — path to the v1 manifest JSON file to transform
- `--output <path>` (optional, default: same as `--input`) — path to write the transformed v2 manifest
- `--validate-only` (optional) — skip transformation; only validate the input against its declared `$schema` and exit
- `--report <path>` (optional) — write the migration report (Markdown) to this path
- `--dry-run` (optional) — print the transformed manifest to stdout and the report to stderr; do not write any files

#### Scenario: Help flag prints usage

- **WHEN** the user runs `manifest-migrate --help`
- **THEN** the CLI prints a usage summary listing all flags and exits 0

#### Scenario: Missing --input flag

- **WHEN** the user runs `manifest-migrate` without `--input`
- **THEN** the CLI prints an error message and exits 1

#### Scenario: Input file not found

- **WHEN** the user runs `manifest-migrate --input path/to/nonexistent.json`
- **THEN** the CLI prints an error message identifying the missing file and exits 1

---

### Requirement: Exit codes

The CLI SHALL use the following exit codes:

- `0` — success: transformation completed and output is a valid v2 manifest; OR validate-only ran and input is valid; OR input is already v2 and no changes are needed
- `1` — failure: unreadable input, invalid JSON, schema validation failure, or unhandled error

#### Scenario: Successful transformation exits 0

- **WHEN** the CLI transforms a valid v1 manifest and the output validates against the v2 schema
- **THEN** the CLI exits with code 0

#### Scenario: Validation failure exits 1

- **WHEN** the CLI runs `--validate-only` and the input does not satisfy its declared `$schema`
- **THEN** the CLI exits with code 1 and prints the validation errors to stderr

#### Scenario: Output fails v2 schema validation

- **WHEN** the transformation produces output that does not satisfy the v2 schema
- **THEN** the CLI exits with code 1, prints the schema errors to stderr, and does not write the output file

---

### Requirement: Idempotency on v2 input

When the input manifest's `$schema` field references the v2 schema URL, the CLI SHALL validate the manifest against the v2 schema and exit 0 without modifying any files.

#### Scenario: Already-v2 manifest is a no-op

- **WHEN** the input manifest contains `"$schema": "https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json"`
- **THEN** the CLI validates it against the v2 schema, writes nothing, and exits 0

#### Scenario: Idempotent second run

- **WHEN** the CLI is run twice on the same manifest
- **THEN** the second run produces no file changes and exits 0

---

### Requirement: validate-only mode

In `--validate-only` mode the CLI SHALL parse the input manifest, detect its schema version from the `$schema` field, validate against that schema, print a summary of validation errors (if any) to stderr, and exit without writing any output.

#### Scenario: Valid v1 manifest passes validate-only

- **WHEN** the user runs `manifest-migrate --input manifest.json --validate-only` on a valid v1 manifest
- **THEN** the CLI prints "Valid" to stdout and exits 0

#### Scenario: Invalid manifest fails validate-only

- **WHEN** the user runs `manifest-migrate --input manifest.json --validate-only` on a manifest missing the required `version` field
- **THEN** the CLI prints the schema validation errors to stderr and exits 1

---

### Requirement: dry-run mode

In `--dry-run` mode the CLI SHALL print the transformed manifest JSON to stdout and the migration report to stderr without writing any files.

#### Scenario: Dry-run outputs to stdout

- **WHEN** the user runs `manifest-migrate --input manifest.json --dry-run`
- **THEN** the transformed JSON is written to stdout, a report summary is written to stderr, and no files are created or modified

#### Scenario: Dry-run is pipeable

- **WHEN** the user pipes dry-run output to `jq .`
- **THEN** `jq` receives valid JSON on stdin

---

### Requirement: Report output shape

When `--report <path>` is provided (or in `--dry-run` mode where report goes to stderr) the CLI SHALL write a Markdown document containing:

1. A summary section: total pages processed, transformation rules applied, and count of items requiring manual attention
2. One section per page that has items requiring attention, listing each `_note` TODO marker and the suggested action
3. A "Registry suggestions" section listing trivial `type: "custom"` pages converted to `kind: "page"` suggestions in JSON snippet form
4. A "Carried-forward fields" section listing fields passed through verbatim (`dataSource`, `@resolve:`, `dynamicSource`, named-view sidebar)

#### Scenario: Report lists TODO items

- **WHEN** the input contains a non-trivial `type: "custom"` page
- **THEN** the report includes a TODO entry for that page with the page `id` and a description of why it requires manual migration

#### Scenario: Report lists registry suggestions

- **WHEN** the input contains a trivial `type: "custom"` page whose `component` maps to an entry in `customComponents`
- **THEN** the report includes a suggested `registry` entry JSON snippet for that page

---

### Requirement: Migration transformations

The CLI SHALL apply the following transformation rules in order to every input manifest whose detected schema version is v1.x:

#### Scenario: Merge widgets and layout per dashboard page

- **WHEN** a page of `type: "dashboard"` has both a `widgets[]` array and a `layout[]` array
- **THEN** each widget entry from `widgets[]` is merged with its matching `layout[]` entry (matched by `id`) into a unified `widgets[]` entry with grid coordinates, and the top-level `layout[]` key is removed

#### Scenario: Lift sidebar tab widgets

- **WHEN** a page contains a `sidebarTabs[]` array where each tab has a `widgets[]` sub-array
- **THEN** each widget in `sidebarTabs[i].widgets[]` is lifted to the page's top-level `widgets[]` with `slot: "sidebar"` and `tabGroup: <tab-id>`, and the `sidebarTabs` key is removed

#### Scenario: Flatten settings section widgets

- **WHEN** a settings page contains `sections[]` where each section has a `widgets[]` sub-array
- **THEN** each widget is moved to the page's top-level `widgets[]` with `slot: "section:<section-id>"`, and the `sections` key is removed

#### Scenario: Flatten settings tabs

- **WHEN** a settings page contains `tabs[]` each with a `widgets[]` sub-array
- **THEN** each widget is moved to the page's top-level `widgets[]` with `slot: "tab:<tab-id>"`, and the `tabs` key is removed

#### Scenario: Migrate cardComponent to card-grid widget

- **WHEN** a page has a `cardComponent` field
- **THEN** a new widget entry `{ "id": "<page-id>-card-grid", "type": "card-grid", "component": "<cardComponent-value>" }` is prepended to the page's `widgets[]`, and the `cardComponent` key is removed

#### Scenario: Add explicit action type

- **WHEN** an action entry in a page's `actions[]` array lacks a `type` field
- **THEN** `"type": "handler"` is added to that action entry

#### Scenario: Carry forward dataSource verbatim

- **WHEN** a page or widget has a `dataSource` field
- **THEN** the `dataSource` field is preserved unchanged in the output

#### Scenario: Carry forward @resolve: fields verbatim

- **WHEN** a manifest field name starts with `@resolve:`
- **THEN** that field and its value are preserved unchanged in the output

#### Scenario: Carry forward dynamicSource verbatim

- **WHEN** a page or widget has a `dynamicSource` field
- **THEN** the `dynamicSource` field is preserved unchanged in the output

#### Scenario: Carry forward named-view sidebar verbatim

- **WHEN** a page has a named-view sidebar configuration
- **THEN** the sidebar configuration is preserved unchanged in the output

#### Scenario: Convert trivial custom page to registry suggestion

- **WHEN** a page has `type: "custom"` and its `component` value appears as a key in the manifest's top-level `customComponents` map
- **THEN** the codemod emits a registry entry suggestion in the report (not in the output manifest itself) and adds a `_note: "TODO: add to registry"` marker on the page entry

#### Scenario: Flag non-trivial custom page with TODO

- **WHEN** a page has `type: "custom"` and its `component` value does NOT appear in `customComponents`, or the page has additional v1 fields not covered by the trivial-custom heuristic
- **THEN** the codemod adds `"_note": "TODO: manual migration required — custom page not auto-converted"` to the page entry and adds a TODO item to the report

#### Scenario: Update $schema to v2 URL

- **WHEN** any v1 manifest is transformed
- **THEN** the output manifest's `$schema` field is set to `"https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json"`

#### Scenario: Map customComponents to registry

- **WHEN** the input manifest has a top-level `customComponents` map
- **THEN** each entry is moved to a top-level `registry` map with `"kind": "component"` added to each entry, and the `customComponents` key is removed

---

### Requirement: Test corpus validation

The library's test suite SHALL include integration tests that run the CLI transformation on each of the four captured fleet manifests (procest, pipelinq, softwarecatalog, decidesk) and assert that the output validates against the v2 JSON Schema without errors.

#### Scenario: procest v1 manifest transforms to valid v2

- **WHEN** the transformation pipeline runs on `tests/fixtures/v1-manifests/procest-v1.json`
- **THEN** the output passes `validateManifest()` against the v2 schema with zero errors

#### Scenario: pipelinq v1 manifest transforms to valid v2

- **WHEN** the transformation pipeline runs on `tests/fixtures/v1-manifests/pipelinq-v1.json`
- **THEN** the output passes `validateManifest()` against the v2 schema with zero errors

#### Scenario: softwarecatalog v1 manifest transforms to valid v2

- **WHEN** the transformation pipeline runs on `tests/fixtures/v1-manifests/softwarecatalog-v1.json`
- **THEN** the output passes `validateManifest()` against the v2 schema with zero errors

#### Scenario: decidesk v1 manifest transforms to valid v2

- **WHEN** the transformation pipeline runs on `tests/fixtures/v1-manifests/decidesk-v1.json`
- **THEN** the output passes `validateManifest()` against the v2 schema with zero errors

---

### Requirement: Migration documentation

The library SHALL ship a `docs/migrating-to-v2.md` document covering:

- How to run the CLI (`npx @conduction/nextcloud-vue manifest-migrate`)
- Each CLI flag with an example
- The complete migration matrix in tabular form
- Common manual-migration patterns for non-trivial custom pages
- How to interpret the migration report
- Known pitfalls (e.g., `dataSource` carry-forward, `@resolve:` field handling)

#### Scenario: Migration guide is present in docs

- **WHEN** a developer navigates to `docs/migrating-to-v2.md` in the published Docusaurus site
- **THEN** the page renders with CLI usage examples and the migration matrix table
