# Tasks — LarpingApp schema-declared notifications

- [ ] PREREQUISITE: add a structured `ownerUid` field (Nextcloud uid) to the `character` schema in `lib/Settings/larpingapp_register.json` (or confirm OR object-acl owner is populated) — `ocName` is free text and cannot resolve a recipient
- [ ] Add `x-openregister-notifications` to the `character` schema with `character-submitted` (trigger `created`, `groups: [gamemasters]`) and `character-approved` (transition action `approved`, `object-acl` manage + `groups: [gamemasters]`)
- [ ] Add `x-openregister-notifications` to the `event` schema with `event-reminder` (trigger `scheduled` on `startDate`, `groups: [players]`)
- [ ] Do NOT use `{"kind":"field","field":"ocName"}` — it will not resolve to a uid (see Caveats); use `groups` / `object-acl` until `ownerUid` exists
- [ ] After `ownerUid` lands, switch `character-approved` recipient to `{"kind":"field","field":"ownerUid"}` so the player is notified
- [ ] Confirm the `gamemasters` and `players` group names match the target deployment, or adjust the recipient groups
- [ ] Provide nl + en subjects on every rule (ADR-007 / ADR-025)
- [ ] Validate that `lib/Settings/larpingapp_register.json` is still well-formed JSON after the edits
- [ ] Confirm the GM-approval flow drives `character.approved` through a named OpenRegister transition action `approved` (prerequisite; see Caveats)

## Acceptance criteria

- `lib/Settings/larpingapp_register.json` parses as valid JSON.
- `character` declares `character-submitted` (created) and `character-approved` (transition) rules; neither uses a `field:ocName` recipient.
- `event` declares an `event-reminder` rule with a `scheduled` trigger on `startDate`.
- Every rule has both `nl` and `en` subject strings.
- The `ownerUid` prerequisite for per-player targeting is recorded as a task and noted in Caveats.
