# Design: portal-identity

## Context

hydra ADR-046 defines portaliq as the ONE shared external portal for people
**without** Nextcloud accounts. Contract v2.1 scopes a subject's collections by
matching a row field to the server-derived subject reference (`subjectRef`, a
UUID). This change prepares Larpinq's `character` schema for that model by
adding a UUID **domain** owner-ref, because the existing owner field is a
Nextcloud user id and cannot scope an account-less external subject.

All facts below were verified against HEAD
(`lib/Settings/larpinq_register.json` + `lib/Settings/register.d/*.json`,
branch point `origin/development` @ c7db0c5).

## The A4 identity rule applied

| | Field | Kind | Portal-scopable? |
|---|---|---|---|
| At HEAD | `character.ownerUid` | Nextcloud `uid` string | ❌ external subjects have no NC uid |
| Added | `character.ownerRef` | `player` object UUID (domain ref) | ✅ equals the portal `subjectRef` |

`ownerUid` is load-bearing for internal flows and MUST stay:

- Its own description: "Nextcloud uid of the player who owns this character.
  Used for per-player notifications (e.g. character-approved)."
- `larpinq_register.json` wires the `character-approved` notification rule to
  `recipients: [{ kind: field, field: ownerUid }]` — deliver-to-the-owning-NC-user.

Removing or repurposing `ownerUid` would break that notification and every
NC-user-facing ownership flow. Hence the approach is **additive and
non-destructive**: `ownerRef` is a NEW property alongside `ownerUid`; nothing is
migrated or renamed in this change.

## Additive-remap rationale + the claim name

- **New property, not a rename.** `ownerRef` and `ownerUid` are two identifier
  spaces (domain UUID vs NC uid) serving two consumers (portaliq vs internal
  notifications). Conflating them would either break notifications or scope the
  portal to nothing.
- **Claim name.** The portal subject's scoping claim is `ownerRef` — the same
  bare name the dependent `PortalContributionProvider` declares as
  `scopeField`/`scopeClaim`. This is Larpinq's STABLE claim contract with
  portaliq operators (a portaliq portal account for a player carries a
  `subjectRef` equal to that player's `player` object UUID, and every character
  the player owns stores that same UUID in `ownerRef`).
- **Fail-closed default.** `ownerRef` is NOT in `character.required`. A row with
  `ownerRef` unset matches no subject ref, so it is invisible to the portal —
  the safe default while the backfill is pending.
- **Backfill is a follow-up (not this change).** Mapping each existing
  `character.ownerUid` → the owning `player` UUID is a one-off data migration
  tracked on Conduction/larpingapp#51. Deferring it keeps this change a pure,
  reviewable schema addition and keeps the portal fail-closed until data is
  ready — no character is exposed by accident.

## Stable domain identity: why the `player` UUID

`ownerRef` points at the Larpinq **`player`** object UUID rather than the
player's linked Nextcloud contact UUID:

- `player` is Larpinq's own first-class domain entity ("A real-world player
  participating in the LARP"); a character conceptually belongs to a player.
- `register.d/player-to-contacts-leaf.json` links `player` to an NC addressbook
  contact (`linkedTypes: ["contacts"]`) for real-world identity, but that link
  is an external reference that can be absent or re-pointed. The in-register
  `player` UUID is the stable anchor.
- Anchoring on the `player` UUID keeps the scoping key inside Larpinq's own
  register, independent of the addressbook, and mirrors the fleet pattern
  (pipelinq scopes `client`/`contact` reads by their own object UUIDs).

Consequence for portaliq operators: provision a portal account whose
`subjectRef` is the player's `player` object UUID.

## Declarative vs imperative

**Decision: purely declarative — a schema property, no code.** The ownership
link is expressed as register data (`character.ownerRef`), added by an ADR-037
fragment that OpenRegister deep-merges at import. No PHP, no migration, no
service is added by this change. The dependent code change consumes this
property; it does not compute it.

Rejected: adding an imperative resolver that derives the player UUID from
`ownerUid` at read time — it would couple the schema to a lookup service,
duplicate authz, and still need the property to exist for portaliq to filter on.

## Seed Data (register fragment — no demo objects)

The fragment `lib/Settings/register.d/portal-identity.json` carries **only the
schema definition** (the `ownerRef` property + a schema `version` bump). It adds
**no objects**. This is deliberate: objects placed in a `register.d` fragment
are imported LIVE into OpenRegister (the "registerd fragment objects go live"
gotcha), so a demo/seed character would become real portal-visible data. Test
fixtures for the dependent provider are synthetic nil-pattern UUIDs constructed
in PHPUnit, never seeded into a register.

## Version bumps

| Artifact | Old | New | Where | Why |
|---|---|---|---|---|
| `character` schema `version` | `1.3.0` | `1.4.0` | fragment (scalar overwrite) | additive property |
| OR import version | `<ver>` | `<ver>+frag.<hash>` | auto (fragment signature) | re-run gated import |
| `appinfo/info.xml` app version | `0.1.30` | `0.1.30` | unchanged | app version is release-owned (`register.d/README.md`) |

## Risks

- The scoping key is only meaningful once portaliq accounts carry the player
  UUID as `subjectRef` and characters carry `ownerRef` (backfill). Until then
  the portal shows nothing — fail-closed, not a leak.
- A future register edit that makes `ownerRef` `required` would break internal
  (non-portal) character creation that only sets `ownerUid`. It must stay
  optional.
