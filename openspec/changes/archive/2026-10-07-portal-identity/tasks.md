# Tasks: portal-identity

Tracking issue: Conduction/larpingapp#51 (Wave 3 — schema config only; the code contribution + the ownerRef backfill are separate items on this issue, do not close it).

- [x] T1: Verify at HEAD that `character` scopes ownership by `ownerUid` (a Nextcloud uid) and that internal flows depend on it.
  - `character.ownerUid` description: "Nextcloud uid of the player who owns this character. Used for per-player notifications"
  - `larpinq_register.json` `character-approved` notification uses `recipients: [{ kind: field, field: ownerUid }]`
  - No `ownerRef` exists anywhere in `lib/` or `src/` at HEAD

- [x] T2: Choose and document the stable domain identity the ref points at.
  - `ownerRef` → the Larpinq `player` object UUID (first-class domain entity)
  - `player` is linked to a NC contact via `register.d/player-to-contacts-leaf.json`, but the in-register `player` UUID is the stable anchor (does not depend on the external addressbook link)

- [x] T3: Add `lib/Settings/register.d/portal-identity.json` — an ADR-037 fragment adding `ownerRef` to the `character` schema ALONGSIDE `ownerUid` (do not edit the monolith).
  - `ownerRef`: `type: string`, `format: uuid`, `title: "Owner"`, description quoting the ownerUid distinction
  - `ownerRef` is NOT added to `required` (fail-closed: unset rows show nothing)

- [x] T4: Bump the `character` schema `version` `1.3.0` → `1.4.0` in the fragment (scalar overwrite); leave `appinfo/info.xml` untouched (app version is release-owned per `register.d/README.md`).

- [x] T5: JSON-validate the fragment and simulate the deep-merge to prove `ownerUid` is retained, `ownerRef` is added, and `required` is unchanged.
  - `python3 -c "import json; json.load(open('lib/Settings/register.d/portal-identity.json'))"`
  - deep-merge simulation: 23 character props (ownerUid + ownerRef both present), `required` still `[name, ocName]`

- [x] T6: Author `openspec/changes/portal-identity/{proposal,design,tasks}.md` and `specs/portal-identity/spec.md` (`status: in-progress`); reference Conduction/larpingapp#51 in the proposal.

- [x] T7: Run `openspec validate portal-identity --strict` until clean.

- [x] T8: Commit on `feat/portal-contribution` (conventional message, no Co-Authored-By); do not push, do not open a PR.

## Acceptance criteria

- `character.ownerRef` is a `uuid`-format domain ref with a gate-28 title, added alongside (never replacing) `ownerUid`.
- The fragment deep-merges non-destructively: every existing `character` property survives and `required` is unchanged.
- No demo/seed objects are introduced by the fragment (schema definition only — fragment objects would go live).
- `openspec validate portal-identity --strict` passes.
