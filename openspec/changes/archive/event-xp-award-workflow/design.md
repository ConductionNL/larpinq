# Design — event-xp-award-workflow

## Context

XP currently exists as an ability whose value is computed by the stat engine:
base + effects (positive grants via event/skill effects, negative costs via
skill XP-cost effects), in the deterministic skills → items → conditions →
events order (rpg-system CALC-001..007). Event effects are ambient: linked
characters all receive the same modifier (EVT-007/008). There is no
per-participant grant, no grantor, no reason, no record.

`skill-requirement-enforcement` (companion change) turns computed XP into a
spend budget at the write boundary. This change builds the income side as
explicit data.

## Decision

### 1. Awards are objects, not effects

An `xpAward` is a stored record `{event, character, amount, reason,
awardedBy, awardedAt}` in the larpingapp register. Modelling per-participant
grants as per-character synthetic effects was rejected: effects are reusable
game-mechanic definitions (rpg-system EFF-*), not transactional records, and
abusing them would explode the effect list with one-off "Summer LARP — Alice
+3" entries while still lacking grantor/reason fields. An app schema gives
us OR storage, audit trails, relations (award ↔ event ↔ character), and
RBAC for free (ADR-022) — and per the content-types-as-leaves rule this is
genuine app-domain game data, not a Nextcloud content type.

`amount` is a positive number; penalties stay out of scope (deductions are
what negative effects/conditions are for, and a "negative award" would
silently fight the XP budget floor in `skill-requirement-enforcement`).
`reason` is optional but recommended (the modal nudges; empty is allowed —
"attendance" is self-explanatory). `awardedBy`/`awardedAt` are
server-stamped, never client-supplied.

### 2. Engine extension: a fifth stage, not a parallel adder

`calculateCharacter()` applies the character's awards to the XP ability
after the existing four entity stages (skills → items → conditions → events
→ **xp awards**), each award producing an audit entry
`{type: "xpAward", award: {...}, old, new}`. Appending the stage (rather
than interleaving) keeps every existing CALC scenario's arithmetic and audit
ordering valid — the delta to rpg-system is purely additive (CALC-008/009).

- Awards are preloaded into an indexed map keyed by character, exactly like
  skills/items/etc. (CHAR-080..082 pattern), so batch recalculation stays
  O(entities), not O(queries).
- The XP ability is resolved via the app register config with a name-match
  fallback — the same resolution rule `skill-requirement-enforcement`
  defines; the two changes MUST share one resolver, whichever lands first.
- An award whose `character` no longer resolves is ignored for calculation;
  an award whose `event` is gone still counts (the grant happened — the
  event reference is provenance, not a validity condition). Both follow
  CALC-006 graceful-skip semantics: never a crash.
- Because the budget check in `skill-requirement-enforcement` consumes
  `calculateCharacter()` output, awards raise the spendable budget with zero
  integration code.

### 3. RBAC: OR schema-level, no app auth code

Write access (create/update/delete) on the `xpAward` schema is restricted to
the GM group through OpenRegister's schema-level RBAC configuration in the
register config — the same `gamemasters` group `larpingapp-notifications`
assumes. No app-local listener re-implements authorization (ADR-022;
contrast with `skill-requirement-enforcement`, whose listener exists for
game-rule validation, not auth). Reads are open to authenticated app users:
players already see XP arithmetic in their character's audit trail, and
hiding grant reasons from the player they concern serves no purpose
(GM-private notes belong in `slNotesPrivate`).

### 4. Batch UI lives on the event, corrections are edits

The post-event ritual is event-centric, so the workflow is an "Award XP"
tab/action on the event detail page (visible to GMs only — visibility
sugar; enforcement is the schema RBAC):

- Roster = characters whose `events[]` contains this event (the existing
  character↔event link, CHAR-043/EVT-006 world; how the player got there —
  signup forms or manual link — is `event-signup-to-forms-leaf`'s concern).
- Default amount applies to all checked rows; per-row override + optional
  reason; save creates one `xpAward` per checked character.
- Existing awards for the event are listed inline (per character, with
  amount/reason/awardedBy) and can be edited or deleted — corrections are
  normal object updates, fully visible in the OR audit trail. The batch
  form pre-unchecks characters that already have an award for this event,
  so re-opening the tab does not double-award by default, while deliberate
  extra grants (plot bonus on top of attendance) stay one click away.

### 5. Recalculation on award changes

Award create/update/delete triggers recalculation of the affected character
(the same contract as association changes, CHAR-045). One award touches
exactly one character, so batch awarding N characters costs N targeted
recalculations — bounded by the same preloaded maps the engine already uses.

## Alternatives considered

- **Per-participant synthetic effects** — rejected (see Decision 1):
  pollutes the effect catalogue, no grantor/reason, wrong abstraction.
- **A ledger field on the character (`xpGrants[]`)** — rejected: bloats the
  character object, complicates concurrent batch writes (N award rows vs N
  rebases of one character object), and loses the event-centric query
  ("all awards for Summer LARP").
- **Negative awards for penalties** — rejected: collides with the XP budget
  floor semantics; deductions remain effects/conditions.
- **A dedicated XpAwardController with app routes** — rejected: plain OR
  object CRUD suffices (gate-17 redundant-controller); the only server logic
  is engine consumption + recalc trigger.
- **Award-on-event-effect autosplit** (turn ambient event XP effects into
  per-participant awards automatically) — rejected: changes the meaning of
  existing data; ambient effects and explicit awards serve different needs
  and coexist.

## Risks

- **Double-awarding** — mitigated by the pre-unchecked roster rows and the
  inline existing-awards list; deliberately not hard-blocked (multiple
  grants per character per event are legitimate).
- **Engine-order regression** — the stage is appended, so existing CALC
  scenarios stay byte-identical; PHPUnit asserts the old four-stage
  arithmetic unchanged plus the new stage.
- **OR RBAC config dependency** — if schema-level group write restriction
  is misconfigured, any user could write awards; Newman tests assert the
  403 for a non-GM write explicitly.
- **Award volume** — hundreds of awards per season is trivial for the
  preloaded-map pattern; the per-character index keeps calculation O(own
  awards).
