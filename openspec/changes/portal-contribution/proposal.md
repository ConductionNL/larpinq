---
kind: code
---

# Proposal: portal-contribution

**Tracking issue**: Conduction/larpingapp#51 (Wave 3 of the portaliq fleet rollout — do not close from this change; the `ownerRef` backfill and any XP-award surface live on the same issue)

**Depends on**: `portal-identity` (this change scopes the `player` audience EXCLUSIVELY by `character.ownerRef`, the uuid domain ref that `portal-identity` adds — ADR-032 chain, one branch)

## Summary

Contribute larpingapp's player-facing surfaces to the shared **portaliq**
external portal (hydra ADR-046 + contract v2.1) instead of building a bespoke
portal. LarpingApp ships ONE plain, dependency-free class
`OCA\LarpingApp\Portal\PortalContributionProvider` that declares — for the
single `player` audience — the OpenRegister collections a portal subject may
read and the whitelisted create-action they may perform. Portaliq discovers the
class by convention FQCN, duck-types it (never `instanceof`), and serves the
collections scoped to the subject. Without portaliq installed the class is
inert and larpingapp behaves exactly as before.

## Why

- One shared external portal for people WITHOUT Nextcloud accounts (ADR-046),
  not a portal per app. A LARP player is exactly such a person.
- LarpingApp keeps ownership of its data and domain logic; it only *declares*
  what a player may see and do. No portal auth, shell, session, or inbox logic
  is added to larpingapp.
- Zero coupling: the provider imports nothing from portaliq, has no `implements`
  clause, no info.xml dependency, and no constructor dependencies.

## Scope (this change — code only)

- `lib/Portal/PortalContributionProvider.php` — the declarative manifest
  (read collections + one create action for the `player` audience) with the v2
  `getAudiences()` and v1 `getAudience()` fallback.
- PHPUnit unit tests pinning the manifest shape, the scoping map, the field
  whitelists, the register-drift guard, and the fail-closed behaviour.
- No register/schema edits: the scoping property (`character.ownerRef`) is
  added by the sibling `portal-identity` change.

## The scoping map (see design.md for the full table)

| Surface | Schema | Scope | Fields |
|---|---|---|---|
| `myCharacters` (read) | `character` | `ownerRef` == subject ref | projected (drops GM-only columns) |
| `events` (read) | `event` | public (`scopeField: ''`) | public columns (drops roster + effects) |
| `skillCatalog` / `itemCatalog` / `conditionCatalog` (read) | `skill` / `item` / `condition` | public | reference columns (catalogs drop `characters`) |
| `createCharacter` (create) | `character` | writer stamps `ownerRef` | `name`, `ocName`, `background` |

- **minTrust**: low across the board (hobby app, no special-category or
  government data) — no collection or action raises the assurance floor.

## Out of scope (later phases on Conduction/larpingapp#51)

- **Event signup** — delegated to Nextcloud Forms today
  (`register.d/event-signup-to-forms-leaf.json`); NOT duplicated as a portal
  action.
- **XP awards** as a player collection — the shipped portaliq reader is
  single-hop (`scopeField == subjectRef`) and `xpAward` carries no field equal
  to the player ref (only `character` / `event` / `awardedBy`). Surfacing it
  needs a denormalised owner ref on `xpAward` + backfill, deferred here.
- **No inbox** — larpingapp has no per-player message/notification *collection*;
  the `character-approved` notification targets the owning NC user via
  `ownerUid`, which a portal player does not have. A portal-delivered
  notification path is a follow-up.
- `endpoint` actions (receiver-side assertion verification does not exist yet).

## Depends on

- `portal-identity` (the `character.ownerRef` scoping key).
- portaliq installed (discovery + rendering side); larpingapp stays inert
  without it.
- portaliq read-side **field projection** (contract v2.1) for the projected
  read collections — see design.md Risks.
