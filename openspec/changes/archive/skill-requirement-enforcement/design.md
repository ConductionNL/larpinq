# Design — skill-requirement-enforcement

## Context

Skills already carry a complete prerequisite model (`requiredSkills[]`,
`requiredStats[]`, `requiredConditions[]`, `requiredEffects[]`,
`requiredScore`) and XP costs are modelled as negative effects on the XP
ability — rpg-system SKILL-009..013, MECH-033..037. SKILL-014 documents that
none of it is enforced. Characters are written through OpenRegister's generic
objects API/GraphQL, not through app-local routes (`appinfo/routes.php` has no
character CRUD), so enforcement cannot live in an app controller.

## Decision

### 1. Veto at the OR pre-write hook, not in a controller

LarpingApp registers a listener for OpenRegister's `ObjectCreatingEvent` /
`ObjectUpdatingEvent` (both implement `StoppableEventInterface` and carry a
structured `errors` payload for hook-based rejection), scoped to the
character schema (resolved via the app's register/schema config, slug-tolerant
per the registry-config recipe). The listener delegates to a new
`SkillRequirementService`.

- Covers **every** write path: SPA, REST `/apps/openregister/api/objects/...`,
  GraphQL — server-authoritative by construction.
- Stays inside the ADR-022 boundary: OR owns storage/transport; the LARP rule
  ("you can't take Advanced Swordplay without Basic Swordplay") is app-domain
  logic and stays in LarpingApp.
- Validation only runs when `skills[]`, `items[]`, or `conditions[]` actually
  change (diff old vs new object) — unrelated edits (background text, currency)
  are never blocked by a pre-existing unmet state.

### 2. Reuse the stat engine for the XP budget — extend, don't duplicate

The XP budget question is "would the candidate character's XP ability go
negative?". That is exactly what `CharacterService.calculateCharacter()`
already answers: XP earned (base + positive event/skill effects) minus XP
spent (negative XP-cost effects) **is** the computed XP value, in the
deterministic skills → items → conditions → events order (CALC-002).

`SkillRequirementService` therefore calls `calculateCharacter()` on the
**candidate** character array (the incoming write) and reads the XP ability
from the result. No second formula, no drift risk with rpg-system CALC-001..007.
The XP ability is identified by the existing well-known XP ability reference in
the app's register config (with a name-match fallback), not hardcoded UUIDs.

`requiredStats[]` / `requiredScore` checks read the same candidate stats map,
so a stat granted *by another skill in the same write* counts — assigning
"Basic Swordplay" and "Advanced Swordplay" together in one save is valid if
the combined result satisfies the thresholds.

### 3. Override is data, audit is free

The override is a `requirementOverrides[]` array on the character object:
`{ skill: <uuid>, reason: <string>, overriddenBy: <uid>, overriddenAt: <iso> }`.
The validator skips enforcement for assignments with a matching override entry
(and reports them as "overridden", never as "passed"). Because the override
lives in the character JSON, the existing OR object audit trail (CHAR-110 /
RPG-001) records the who/when/what/why with zero new audit machinery.
`reason` is mandatory — an empty reason is itself a validation error.

Writing `requirementOverrides[]` is restricted server-side to GMs (the
`gamemasters` group already assumed by `larpingapp-notifications`); a
non-GM write that adds or alters override entries is rejected.

### 4. Removal flags, never cascades

Removing skill A while assigned skill B has `requiredSkills: [A]`:

- The write **succeeds** (LARP reality: respec, story events, GM fiat).
- B is **not** auto-removed (no silent cascade-delete — destroying a player's
  skill as a side effect is far worse than a dangling prerequisite).
- The validation result flags B as *dependent-now-unmet*, the response carries
  the report, and the character detail page shows a persistent warning until
  the GM resolves it (re-add A, remove B, or override).
- `GET /api/characters/{id}/requirement-report` recomputes the report on
  demand (also catches drift from *global* skill edits — a GM tightening a
  skill's prerequisites after assignment).

## Alternatives considered

- **Frontend-only validation in the Add-Skill modal** — rejected: characters
  are API-writable; a curl call bypasses it entirely. The modal pre-check is
  kept, but as UX sugar over the server verdict.
- **Hard-block prerequisite removal** — rejected: legitimate GM workflows
  (respec, curse removal, retcon) require it; flag-and-report matches how GMs
  actually run games.
- **Implicit override for any GM write** — rejected: "GMs bypass everything
  silently" makes the feature useless for the GM's own bookkeeping and leaves
  no audit trace of *intentional* exceptions. Override must be explicit and
  reasoned.
- **A parallel "validate" formula for XP** (sum costs client-side) — rejected:
  duplicates the stat engine and will drift from CALC-002 ordering and
  cumulative/non-cumulative semantics.
- **Enforce `allowed_negative` globally in the engine instead** — out of
  scope: that is the pre-existing ABIL-011 bug with calculation-order
  implications for all abilities; this change enforces the XP floor only at
  the assignment boundary, which is where the product promise lives.

## Risks

- **Performance**: validation runs `calculateCharacter()` once per character
  write that touches associations — same cost as the recalculation the write
  already triggers (CHAR-010). The preloaded entity maps (CHAR-080..082) make
  the candidate run cheap.
- **Listener ordering / double work**: the pre-write veto must run before the
  stat-recalculation hook; both consume the same preloaded CharacterService,
  so no extra loading.
- **Legacy data**: existing characters may already violate prerequisites.
  Enforcement is **write-time and diff-scoped** — pre-existing violations
  never block unrelated edits; they surface only in the requirement report
  (warning, not error) until the relevant association is next touched.
- **OR hook contract**: if the deployed OpenRegister predates the
  Creating/Updating hook events, the listener never fires and behaviour
  degrades to today's (data-only). The report endpoint still works; the app
  must not fatal on the missing event classes.
