---
kind: config
---

# Proposal: portal-identity

**Tracking issue**: Conduction/larpingapp#51 (Wave 3 of the portaliq fleet rollout — do not close from this change; the code contribution `portal-contribution` and the backfill follow-up live on the same issue)

## Summary

Add a stable UUID **domain reference** for a character's owning player —
`character.ownerRef` — so the shared **portaliq** external portal (hydra
ADR-046 + contract v2.1) can scope a player's own characters by a domain UUID
rather than by a Nextcloud user id. The property is added ALONGSIDE the
existing `character.ownerUid` (which stays untouched — internal flows and the
`character-approved` per-player notification depend on it). This is the first
link of a two-change ADR-032 chain:

1. **`portal-identity` (this change, `kind: config`)** — add the `ownerRef`
   domain-ref property to the `character` schema via an ADR-037 register
   fragment.
2. **`portal-contribution` (`kind: code`, `depends_on: portal-identity`)** —
   ship the dependency-free `PortalContributionProvider` that scopes the
   `player` audience EXCLUSIVELY by `ownerRef`.

## Why — the A4 identity rule

Portaliq serves people **without** Nextcloud accounts. Its subject is resolved
server-side to a `subjectRef` UUID (ADR-005), and collections are scoped by a
row field that equals that ref. A portal scoping property MUST therefore be a
**UUID domain ref**, never a Nextcloud user id (an external subject has no NC
account by premise).

At HEAD, larpingapp scopes character ownership by `character.ownerUid` — a
Nextcloud `uid` string (verified: it is the `recipients[].field` of the
`character-approved` notification rule in `larpingapp_register.json`, and its
own description reads "Nextcloud uid of the player who owns this character").
That identifier cannot scope a portal subject.

## What changes

- **Add** `character.ownerRef` (`type: string`, `format: uuid`, title `Owner`)
  via a new fragment `lib/Settings/register.d/portal-identity.json` (ADR-037 —
  disjoint fragments never collide; the monolith `larpingapp_register.json` is
  not edited on a build branch, per `register.d/README.md`).
- **Keep** `character.ownerUid` exactly as-is (additive, non-destructive).
- **Bump** the `character` schema `version` `1.3.0` → `1.4.0` inside the
  fragment (scalar overwrite). The OpenRegister import version additionally
  auto-busts via the fragment-signature hash (`<ver>+frag.<hash>`).

## Scoping choice (documented — the stable domain identity)

`ownerRef` points at the larpingapp **`player` object UUID** — larpingapp's own
first-class domain entity for a real-world player. The `player` schema is
separately linked to a Nextcloud addressbook contact via
`register.d/player-to-contacts-leaf.json` (`linkedTypes: ["contacts"]`) for
real-world identity, but the portal scoping key anchors on the in-register
`player` UUID because it is stable and does not depend on the external
addressbook link being present or re-pointed. See `design.md`.

## Out of scope (later, on Conduction/larpingapp#51)

- The `PortalContributionProvider` itself — the dependent `portal-contribution`
  change.
- **Backfilling `ownerRef`** onto existing characters (mapping each
  `ownerUid` → its `player` UUID). Until backfilled, a row with `ownerRef`
  unset is invisible to the portal (fail-closed) — the deliberate, safe
  default. This is a documented follow-up, NOT part of this change.
- Denormalising an owner ref onto `xpAward` (needed only if XP awards ever
  become a player-portal collection — see `portal-contribution/design.md`).

## Depends on

- OpenRegister ≥ v0.2.10 (the register's declared `x-openregister.openregister`
  floor); the ADR-037 fragment deep-merge in `ConfigFileLoaderService`.
