---
status: draft
---

# Post-event XP award workflow

## Why

The app description promises "Handle event subscription and **experience
score**". Today the only way an event grants XP is an ambient event effect:
every character linked to the event gets the identical modifier during stat
calculation (EVT-007/008, CHAR-055). That is the wrong tool for the core
post-event ritual of every LARP organisation: after the weekend, the GM sits
down and awards XP **per participant** — full XP for full attendance, half
for a day-guest, a bonus for the player who carried the plot. There is no
recorded act of awarding, no per-participant amount, no reason, and no way to
answer "who gave this character 5 XP and why". The 2026-06-11 feature
re-evaluation (`FEATURE-REEVALUATION-2026-06-11/larpingapp.md`) lists this as
the missing "post-event experience-score awarding workflow".

The companion change `skill-requirement-enforcement` makes computed XP a hard
budget for skill purchases — which makes the *income* side of that ledger
matter: XP earned must be explicit, auditable data, not an undifferentiated
event effect.

## What Changes

- **New `xpAward` object type.** `{event, character, amount (positive
  number), reason, awardedBy, awardedAt}` — one record per grant.
  `awardedBy`/`awardedAt` are stamped server-side. Awards are app-domain
  game-mechanic records (not a Nextcloud content type), so an app schema in
  the larpingapp register is the right home; storage, audit trail, and RBAC
  come from OpenRegister.
- **Stat engine consumes awards.** `CharacterService.calculateCharacter()`
  gains a fifth application stage: after skills → items → conditions → events
  (CALC-002), the character's XP awards are summed onto the XP ability, each
  producing an audit entry `{type: "xpAward", ...}`. The XP ability is
  resolved via the app register config with a name-match fallback (same rule
  as `skill-requirement-enforcement`) — no hardcoded UUIDs. Dangling award
  references are skipped gracefully (CALC-006 semantics).
- **GM-only writes.** Creating/updating/deleting `xpAward` objects is
  restricted to the GM group server-side via OpenRegister schema-level RBAC
  (authorization stays OR-delegated per ADR-022 — no app-local auth code).
  Players can read the awards on their own characters (the XP audit trail
  shows them anyway).
- **Batch award UI on the event detail page.** A GM-only "Award XP" tab/
  action shows the event roster (characters whose `events[]` contain the
  event), a default amount, per-row amount override and optional reason, and
  creates one `xpAward` per selected character on save. Existing awards for
  the event are listed inline and can be edited or deleted (corrections),
  with every change visible in the OR object audit trail.
- **Recalculation.** Creating, updating, or deleting an award triggers stat
  recalculation of the affected character (the CHAR-045 analogue), so the XP
  ability and the skill-purchase budget update immediately.

## Impact

- Affected specs: `event-xp-awards` (new capability); `rpg-system`
  (MODIFIED — "Stat Calculation Order and Audit" gains the XP-award stage:
  CALC-002 order extended, CALC-008/009 added).
- Affected code (apply phase, NOT here):
  - `lib/Settings/larpingapp_register.json` — new `xpAward` schema (+ GM
    group RBAC config for it); register schema list
  - `lib/Service/CharacterService.php` — award preloading (CHAR-080..082
    pattern) + the fifth application stage + audit entry type
  - Listener/hook for award create/update/delete → recalculate the affected
    character
  - `src/manifest.json` / event detail — "Award XP" tab (GM-only), batch
    modal in `src/modals/`
  - `src/store/store.js` — `xpAward` in SCHEMA_SLUGS
  - `l10n/` nl + en strings; `appinfo/info.xml` version bump (cache-bust)
- Depends on: OpenRegister schema-level RBAC (group write restriction) and
  object audit trails — both shipped.
- Relates to: `skill-requirement-enforcement` (awards feed the engine-
  computed XP that the budget check consumes — by construction, since both
  use `calculateCharacter()`); `event-signup-to-forms-leaf` (signups decide
  who attends; awards happen after — the roster comes from the existing
  character↔event link either way, no duplication); `larpingapp-notifications`
  (the `gamemasters` group concept; an "XP awarded" notification rule is a
  natural follow-up there, not part of this change); `setting-management`
  (awards inherit the event's setting context implicitly via the event ref).
- Out of scope: negative awards/penalties (deductions stay modelled as
  effects or conditions); player-visible XP-history page beyond the existing
  stat audit trail; changing event ambient effects (EVT-007/008 keep working
  for world-level modifiers like "everyone got cursed").
