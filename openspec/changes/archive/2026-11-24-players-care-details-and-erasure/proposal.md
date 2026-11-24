---
kind: code
depends_on: [registration-intake-and-capacity]
---

# Proposal: players-care-details-and-erasure

## Summary

A weekend in a forest needs to know who carries an epipen, who cannot eat
nuts and whom to call when someone falls. That is health data, the most
sensitive personal data there is, and it should be gone once the event is
over. Larpinq's player holds a name and a description. This change adds care
details to the player (allergies, medical notes, dietary needs, emergency
contact), encrypted and readable only by game masters and the player; copies
them onto each accepted registration for the event's first aid and kitchen;
and erases those event copies after the event through OpenRegister's retention
workflow, which keeps a destruction record.

## Motivation

Two players rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for both.

**`ply-medical-dietary`**, "Record a player's allergies, dietary needs and
emergency contact." Larpinq rates it no. Matrix evidence:
"lib/Settings/larpinq_register.json:362-395 (player schema properties are only
name, description, userUid); grep -rniE 'allerg|dietary|emergency contact' lib
src openspec: no hits in larpinq's own schema/code". Two competitors rate it
yes, one partial:

- LarpManager (yes): "larpmanager/models/member.py:183-300 first_aid, accessibility, diet (allergies) and confidential safety fields, orga_safety and orga_diet pages; emergency contact via a custom registration question (larpmanager/utils/services/association.py:232)" (source read at main 36f23d3).
- LARP Portal (yes): "features.php explicitly lists 'Medical and allergy information tracking.' https://larportal.com/features.php"
- pretix (partial): "src/pretix/base/models/items.py:1570-1620 custom Question types (text, choice, phone number) can ask allergies, diet and an emergency contact per attendee; there are no built-in fields for them".

**`ply-event-data-erasure`**, "Erase participants' personal data from a
finished event, with a record of what was erased." Larpinq rates it no. Matrix
evidence: "grep -rniE 'gdpr|anonymi|erase|retention' lib src: no hits; only
per-record delete through OpenRegister exists". Demand: a pretix feature
request, https://github.com/pretix/pretix/issues/2498. One competitor rates it
yes:

- pretix: "src/pretix/control/views/shredder.py:78-150 exports then shreds chosen personal data of an event, logged as src/pretix/base/services/shredder.py:144-167 pretix.event.shredder.started/completed with the shredder list (shown via src/pretix/control/logdisplay.py:763-764)" (source read at tag v2026.7.0).

Collecting health data and erasing it after the event are one policy, so one
change.

## Affected Projects

- [ ] Project: `larpinq`: care detail fields on the player, an event copy per accepted registration, the declarations that put both under OpenRegister's encryption, access rules, retention and data-subject workflow.

## Scope

### In Scope

- On `player`: `allergies`, `medicalNotes`, `dietaryNeeds`, `emergencyContactName`, `emergencyContactPhone`, encrypted at rest (`x-openregister-encrypted`), readable and writable by the player and by game masters only.
- An event copy (`eventCareRecord`) made when a registration is accepted: the same fields, the event, the registration, readable by game masters only.
- A care list on the event page for game masters: who has which allergy or medical note, and whom to call.
- Erasure: each event copy gets a retention date of the event's end plus 30 days (configurable per event) and is destroyed through OpenRegister's retention workflow with a game master's approval and a destruction certificate.
- The player and care schemas declared in scope for OpenRegister's data-subject workflow (hydra ADR-047), so a player's request to see or erase their data is handled there.

### Out of Scope

- A larpinq screen for data-subject requests: hydra ADR-047 says an app declares and builds no such screen.
- Age and parental consent (row `ply-age-consent`, deferred).
- Diet counts for the kitchen: the meal option of `registration-ticket-types-and-options` counts those.

## Approach

Register declarations for the fields, the encryption flag, the access rules and
the retention metadata; a small copy step in the registration service on
acceptance. OpenRegister does encryption, stripping, retention, destruction
certificates and the data-subject workflow. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/players-care-details-and-erasure.json` (new): player fields, `eventCareRecord`, `event.careRetentionDays`, rules and retention.
- `lib/Service/RegistrationService.php`: the copy on acceptance.
- `src/manifest.json`: a care section on PlayerDetail and a care list on EventDetail for game masters (in place).

## Cross-Project Dependencies

OpenRegister specs on development: `field-level-encryption`
(`x-openregister-encrypted: true`, decrypted only for authorised reads,
excluded from search and facets), `row-field-level-security`,
`retention-management` (archiefactiedatum from a property, destruction lists
with approval, destruction certificates) and `gdpr-data-subject-rights`; hydra
ADR-047 for the data-subject case workflow.

## Risks

### Risk 1: Health data leaks through a list or an export
**Severity:** High. **Mitigation:** the fields are encrypted, excluded from search and facets, and property rules strip them for everyone but game masters and the player on every path, exports included. Newman checks a CSV export as another player.

### Risk 2: Erasure is forgotten
**Severity:** Medium. **Mitigation:** the retention date is set on each copy when it is made; OpenRegister's destruction job lists due copies for approval, and the event page shows how many care records are due or past due.

### Risk 3: First aid needs the data after the retention date
**Severity:** Low. **Mitigation:** a game master can extend the window per event before the date, or place a legal hold (for an incident) through OpenRegister.

## Rollback Strategy

Remove the fragment and the copy step. Existing encrypted values stay
encrypted; copies keep their retention dates.

## Open Questions

None.
