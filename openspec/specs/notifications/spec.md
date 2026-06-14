# notifications Specification

## Purpose
TBD - created by archiving change larpingapp-notifications. Update Purpose after archive.
## Requirements
### Requirement: Character submission and approval notifications

The LarpingApp `character` schema SHALL declare
`x-openregister-notifications` rules that notify the `gamemasters` group
on character submission (trigger `created`) and notify manage-ACL
holders plus the `gamemasters` group on approval (transition action
`approved`), with bilingual (nl/en) subjects. These rules SHALL NOT use
a `field:ocName` recipient because `ocName` is free text and does not
resolve to a Nextcloud uid.

#### Scenario: New character submitted

- **WHEN** a `character` object is created
- **THEN** the OpenRegister notification engine dispatches an `nc-notification` to the `gamemasters` group with a nl/en subject referencing `{{name}}`

#### Scenario: Character approved

- **WHEN** a `character` object transitions through the `approved` action
- **THEN** the engine dispatches an `nc-notification` to the object's manage-ACL holders and the `gamemasters` group with a nl/en subject referencing `{{name}}`

### Requirement: Structured owner uid prerequisite for per-player notifications

LarpingApp SHALL add a structured `ownerUid` field to the `character`
schema (or rely on the OpenRegister object-acl owner) before per-player
`field` recipients can be used; until then character rules use `groups`
or `object-acl` recipients only.

#### Scenario: ownerUid enables player-targeted approval

- **WHEN** a structured `ownerUid` field exists on `character`
- **THEN** the `character-approved` rule recipient is switched to `{"kind":"field","field":"ownerUid"}` so the submitting player is notified

### Requirement: Event reminder notification

The LarpingApp `event` schema SHALL declare an
`x-openregister-notifications` rule that sends the `players` group a
scheduled reminder ahead of an event's `startDate`, with bilingual
(nl/en) subjects.

#### Scenario: Upcoming event reminder

- **WHEN** the scheduled reminder window before an `event` object's `startDate` is reached
- **THEN** the engine dispatches an `nc-notification` to the `players` group with a nl/en subject referencing `{{name}}` and `{{startDate}}`

