# Design: portal-contribution

## Context

hydra ADR-046 defines portaliq as the ONE shared external portal for people
without Nextcloud accounts. Contract v2.1: apps contribute via a single plain
class at convention FQCN, duck-typed by portaliq (`method_exists()`, never
`instanceof`). Larpinq has no bespoke portal to retire; this change ADDS the
declarative contribution for the `player` audience.

All register facts below were verified against HEAD
(`lib/Settings/larpinq_register.json` + `lib/Settings/register.d/*.json`,
branch point `origin/development` @ c7db0c5) and the shipped portaliq reader
(`apps-extra/portaliq` @ development 8aefefe).

## How the shipped portaliq reader scopes (verified)

`ContributionController::collection()` reads a collection with
`scopeField = collection['scopeField'] ?? 'subjectRef'` and
`subjectRef = subject['subjectRef']`, and `PortalObjectReader` filters
`row[scopeField] === subjectRef` (plus a per-row `verifyScope()` re-check). Two
consequences drive this design:

1. **Scoping is single-hop by the subject ref.** A collection is shown only if
   one of its own row fields equals the subject's ref. There is no join, no
   parent scope, no claim-bag lookup in the shipped controller (`scopeClaim` is
   a forward-compatible contract field, currently unread).
2. **An absent `scopeField` defaults to `subjectRef`.** A public list must
   therefore declare `scopeField: ''` EXPLICITLY — otherwise the reader filters
   on a non-existent `subjectRef` column and returns nothing.

## Claim-names contract (Larpinq's claim namespace — STABLE)

The `player` audience's subject ref is the Larpinq **`player` object UUID**.
Every character the player owns stores that UUID in `character.ownerRef` (added
by `portal-identity`). The provider declares `scopeClaim: 'ownerRef'` on the
character collection — Larpinq's stable claim contract with portaliq
operators: provision a portal account whose `subjectRef` is the player's
`player` UUID.

| Claim | Value (UUID domain ref) | Used by |
|---|---|---|
| `ownerRef` | Larpinq `player` object UUID | player (own characters + create) |

## Scoping map (schema → scopeField → claim), all register `larpingapp`

| Surface | Kind | Schema | scopeField | scopeClaim | Notes |
|---|---|---|---|---|---|
| `myCharacters` | read | `character` | `ownerRef` | `ownerRef` | field-projected |
| `events` | read | `event` | `''` (public) | — | public event list |
| `skillCatalog` | read | `skill` | `''` (public) | — | game reference data |
| `itemCatalog` | read | `item` | `''` (public) | — | drops `characters` |
| `conditionCatalog` | read | `condition` | `''` (public) | — | drops `characters` |
| `createCharacter` | create | `character` | `ownerRef` | — | writer stamps owner |

### character read projection (default-deny whitelist)

INCLUDED (the owner's own, non-secret columns): `name`, `ocName`,
`description`, `type`, `faith`, `gold`, `silver`, `copper`, `card`,
`itemsAndMoney`, `slNotesPublic` ("Game master notes visible to players"),
`background`, `skills`, `items`, `conditions`, `events`, `setting`.

EXCLUDED (enumerated, with the schema reason):

- `approved` — GM approval lifecycle field; server-authoritative.
- `slNotesPrivate` — "Game master notes NOT visible to players".
- `notice` — "Automatic system notices"; internal.
- `requirementOverrides` — "Explicit, audited GM overrides"; staff audit data.
- `ownerUid` — the Nextcloud uid; internal identity, not a portal field.
- `ownerRef` — the scoping key itself; no need to echo it back.

`background` ("visible to GMs only") IS included: the collection is scoped to
the player's OWN characters and the player authors the background on create;
"GM-only" means hidden from OTHER players, not from the owning author.

### create whitelist

`createCharacter` accepts `name`, `ocName`, `background` only. The writer stamps
`ownerRef` = subjectRef server-side (`PortalObjectWriter` sets
`data[scopeField] = subjectRef`), so a portal-created character is owned by the
player automatically and has no `ownerUid` (correct — the player has no NC
account). Economy (`gold`/`silver`/`copper`), lifecycle (`approved`), and every
GM field stay server-authoritative.

## Exclusions (judgment calls)

- **`xpAward` — EXCLUDED.** The shipped reader is single-hop; `xpAward` carries
  only `character`, `event`, `awardedBy` refs — none equal the player subject
  ref. A player owns many characters, so scoping XP by "character ∈ my
  characters" is a two-hop the contract does not express. Surfacing it needs a
  denormalised `ownerRef` on `xpAward` (mirroring character) PLUS an award
  write-path stamp PLUS a backfill — its own change. Deferred on
  Conduction/larpingapp#51.
- **Event signup — DELEGATED, not duplicated.** `event-signup-to-forms-leaf.json`
  routes signup through Nextcloud Forms today; adding a portal signup action
  would fork that flow.
- **`event.players` + `event.effects` — PROJECTED OUT.** The participant roster
  is a privacy surface and post-event effects are GM mechanics; the public list
  ships name/description/dates/location/setting only.
- **`item.characters` / `condition.characters` — PROJECTED OUT.** The public
  catalog is reference data; the ownership arrays would leak who-has-what.
- **`player`, `ability`, `effect`, `setting`, `template` — NOT surfaced.** Roster
  / stat-engine / internal reference schemas with no player-portal purpose; the
  manifest is an explicit allowlist.

## Declarative vs imperative

**Decision: fully declarative — pure-data manifest, zero I/O.** The provider
branches only on `$subject['audience']` (server-derived per ADR-005) and returns
constants. Rejected alternatives:

- *Imperative provider* (query OR to tailor collections per subject): portaliq
  already scopes reads server-side; app-side queries would duplicate the authz
  path (ADR-022 violation), add OR coupling to a class whose whole value is
  being dependency-free, and turn a discovery probe into a data access.
- *Resolving the player UUID from `ownerUid` at read time*: needs a lookup
  service (constructor dep) and still needs `ownerRef` to exist for portaliq to
  filter on — which is exactly what `portal-identity` provides declaratively.

## Additive-remap rationale + claim names + backfill follow-up

This change consumes the additive `character.ownerRef` from `portal-identity`
(the character keeps `ownerUid` for internal notifications; the portal scopes
EXCLUSIVELY by `ownerRef`). The claim name is `ownerRef` (STABLE — see the
claim table). Until existing characters are backfilled (`ownerUid` → owning
`player` UUID), `myCharacters` returns nothing for a real player — fail-closed,
never a leak. The backfill is a documented follow-up on
Conduction/larpingapp#51, NOT part of this change.

## Seed Data (unit-test fixtures — nil-pattern UUIDs only)

Tests construct the provider directly (no container) and feed a synthetic
subject built on the nil-UUID pattern so fixtures can never collide with live
data and are self-evidently fake:

```php
$playerSubject = [
    'subjectRef'   => '00000000-0000-0000-0000-000000000001',
    'audience'     => 'player',
    'organisation' => '00000000-0000-0000-0000-000000000002',
    'trust'        => 'low',
];
```

No OR seed objects are created by this change (the provider performs no I/O),
and — critically — no demo objects are placed in any `register.d` fragment
(fragment objects go LIVE). Live-portal seeding (a portal account whose
`subjectRef` is a player UUID, characters carrying `ownerRef`) belongs to
portaliq's own e2e environment, keyed by the claim-names contract above.

## Risks

- **Read-side field projection is enforced portaliq-side.** The provider only
  *declares* the `fields` whitelist; whether the whole row or just the
  whitelist reaches the player depends on portaliq honouring `fields` on read.
  The local portaliq checkout (development @ 8aefefe) does NOT yet project on
  read (`ContributionController::collection()` returns whole verified rows).
  The `character`, `event`, `item` and `condition` collections carry GM-only /
  ownership columns, so this contribution's read-safety **depends on portaliq
  read projection (contract v2.1) being live on the deployed portaliq**. Flagged
  as a DEFERRED question for the fleet: confirm read projection is live before
  this ships, or gate the projected collections behind it (mirrors pipelinq
  Wave-1, which EXCLUDED whole-row-unsafe collections until projection landed).
- The claim name `ownerRef` is load-bearing the moment a portaliq operator
  provisions a player account — renames are a breaking change.
- A future register edit adding a staff-only column to `character` re-opens the
  whole-row exposure risk if projection is not live; the register-drift PHPUnit
  pin fails when a whitelisted column disappears, but cannot see a newly-added
  staff column — the exclusion table above is the review checklist for register
  PRs touching `character`.
