---
kind: config
---

# LarpingApp — schema-declared notifications

## Why

LarpingApp manages LARP worlds, characters, and events for game masters
(GMs) and players. The events worth notifying about are: a **new
character submitted** (GMs need to review/approve it), a **character
approved** (the player wants to know), and an **upcoming event** (players
need a reminder).

The OpenRegister notification engine (shipped in the `openregister`
change `notification-schema-rules-and-userconfig-prefs`, archived
2026-05-26) consumes a top-level `x-openregister-notifications` key on a
schema and dispatches `nc-notification` on the configured trigger.

This is a configuration change to
`lib/Settings/larpingapp_register.json`. **However**, LarpingApp's data
model has a blocker for per-player notifications — see Caveats and the
prerequisite task. The rules below are scoped to what resolves *today*
(GM-group + event-scheduled) and explicitly avoid per-player `field`
recipients until a structured owner uid exists on `character`.

## What Changes

Add `x-openregister-notifications` to two schemas in
`lib/Settings/larpingapp_register.json`.

### `character` — new submission + approval (to GMs)

`character.approved` is an enum `no | approved` (default `no`). A new
character should alert the GMs to review it; an approval is a GM
lifecycle action. **Because `character` has no structured player owner
uid** (`ocName` is free text — see Caveats), the player cannot be
targeted today, so both rules route to the `gamemasters` group rather
than to the submitting player.

```jsonc
"x-openregister-notifications": {
  "character-submitted": {
    "trigger": { "type": "created" },
    "enabled": true,
    "channels": ["nc-notification"],
    "recipients": [ { "kind": "groups", "groups": ["gamemasters"] } ],
    "subject": {
      "nl": "Nieuw personage ingediend: {{name}}",
      "en": "New character submitted: {{name}}"
    }
  },
  "character-approved": {
    "trigger": { "type": "transition", "action": "approved" },
    "enabled": true,
    "channels": ["nc-notification"],
    "recipients": [
      { "kind": "object-acl", "permission": "manage" },
      { "kind": "groups", "groups": ["gamemasters"] }
    ],
    "subject": {
      "nl": "Personage {{name}} is goedgekeurd",
      "en": "Character {{name}} has been approved"
    }
  }
}
```

> Once a structured `ownerUid` is added to `character` (prerequisite
> task), the `character-approved` recipient should become
> `{"kind":"field","field":"ownerUid"}` so the **player** is notified of
> their own approval, not the GM group.

### `event` — upcoming-event reminder

`event.startDate` is an ISO `date-time`. A `scheduled` reminder fires
ahead of the event. Routed to the `players` group (no per-player target
without a participant uid — see Caveats).

```jsonc
"x-openregister-notifications": {
  "event-reminder": {
    "trigger": { "type": "scheduled", "intervalSec": 86400, "filter": { "field": "startDate" } },
    "enabled": true,
    "channels": ["nc-notification"],
    "recipients": [ { "kind": "groups", "groups": ["players"] } ],
    "subject": {
      "nl": "Herinnering: {{name}} begint binnenkort ({{startDate}})",
      "en": "Reminder: {{name}} starts soon ({{startDate}})"
    }
  }
}
```

## Capabilities

- GMs are notified (group `gamemasters`) when a new character is
  submitted.
- On character approval, manage-ACL holders + GMs are notified (will
  become the player once `ownerUid` exists).
- The `players` group gets a scheduled reminder ahead of an event's
  `startDate`.
- All rules ship `enabled: true`; users override per `(schema, rule)`
  via OpenRegister's override-only user-config prefs.
- Subjects ship in Dutch and English (ADR-007 / ADR-025).

## Impact

- Affected file: `lib/Settings/larpingapp_register.json` (`character`
  and `event` schemas gain a `x-openregister-notifications` key).
- A follow-up data-model change to add a structured `ownerUid` field to
  `character` (prerequisite for per-player notifications).
- No PHP, Vue, route, or migration changes in this change itself.
- Runtime dependency on the OpenRegister notification engine
  (`notification-schema-rules-and-userconfig-prefs`, already archived).

## Caveats

- **PREREQUISITE: `character` has no structured owner uid.** The only
  player-name field is `ocName`, which is free text — a `field`
  recipient on it will not resolve to a Nextcloud uid. Per-player rules
  (notify the player of their own character's approval) are therefore
  **not expressible today**. The fix is to add a structured `ownerUid`
  field to `character` (or rely on OpenRegister object-acl owner). Until
  then, every character rule uses `{"kind":"groups",...}` or
  `{"kind":"object-acl",...}` recipients — **not** `field:ocName`. This
  is captured as a prerequisite task.
- **Transition action must be wired.** `character-approved` uses a
  `transition` action named `approved`; the GM-approval flow must drive
  `character.approved` from `no` to `approved` through an OpenRegister
  transition action of that name, not a raw field write, or the rule is
  declared-but-dormant.
- **Event reminders are group-scoped.** Without a per-participant uid on
  `event`, the reminder routes to the whole `players` group rather than
  to a character's confirmed participants. Per-participant targeting
  awaits a participant-uid model on `event`.
- The `gamemasters` and `players` group names are assumed to exist in
  the deployment; adjust to match the operator's directory.
- The `updated` trigger has no field-changed condition yet (the engine
  change `notification-updated-field-change-condition` adds it); this
  change uses `created` / `transition` / `scheduled` to avoid that
  dependency.
