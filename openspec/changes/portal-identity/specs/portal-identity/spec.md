---
status: in-progress
---

# Portal Identity — Delta

**Spec refs**: hydra ADR-046 (portaliq external portal) + contract v2.1 (subject scoped by a UUID row field), ADR-005 (server-derived subject), ADR-037 (modular register fragments)

## ADDED Requirements

### Requirement: Character Owner Domain Reference

The `character` schema MUST expose a `ownerRef` property of `type: string`,
`format: uuid`, carrying a gate-28 `title`, that references the owning player's
**domain** UUID (the larpingapp `player` object UUID) — the portal-subject
scoping key. `ownerRef` MUST be added ALONGSIDE the existing `ownerUid`
(Nextcloud user id) without removing, renaming, or altering it, MUST NOT be a
member of `character.required` (unset rows are fail-closed / portal-invisible),
and MUST be introduced by an ADR-037 `register.d` fragment rather than by
editing the monolithic register on a build branch.

#### Scenario: ownerRef is added as an optional uuid domain ref alongside ownerUid

- **GIVEN** the `character` schema at HEAD scopes ownership by `ownerUid` (a Nextcloud uid)
- **WHEN** the `portal-identity` fragment is deep-merged into `larpingapp_register.json`
- **THEN** the merged `character` schema MUST contain BOTH `ownerUid` (unchanged) AND a new `ownerRef` with `type: string`, `format: uuid`, and a non-empty `title`
- **AND** `ownerRef` MUST NOT appear in `character.required`, and `character.required` MUST remain `["name", "ocName"]`
- `@e2e exclude` schema-shape change verified by JSON deep-merge simulation + the dependent provider's register-drift PHPUnit pin; there is no UI surface in larpingapp CI for the portaliq scoping key

#### Scenario: the fragment introduces no live objects

- **WHEN** `lib/Settings/register.d/portal-identity.json` is imported by OpenRegister
- **THEN** it MUST contribute only the `character` schema definition (the `ownerRef` property and a schema `version` bump) and MUST NOT create any object
- `@e2e exclude` register-import content is asserted by JSON inspection; fragment objects would go live, so none are declared — a static-data guarantee with no runtime UI in larpingapp
