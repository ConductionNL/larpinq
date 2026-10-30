---
kind: code
depends_on: [portal-contribution]
screens: [LrPortaal, LrPortaalRegelboek]
---

# Proposal: portal-my-larp-pages

## Why

The LrPortaal board draws a player's own page in the municipal portal, "Mijn larp" under the Verenigingen group:
their characters and their registrations with what is still to pay. LrPortaalRegelboek draws the rulebook there: the
rules chapters, then the skill list. larpinq contributed collections to the portal but no pages, so the portal had no
larpinq screen to open.

## What changes

- The player contribution gains two contribution-manifest-v3 pages in the group Verenigingen: Mijn larp (the
  player's characters, Create a character, the player's registrations) and Regelboek (the rules chapters, then the
  skills).
- `myRegistrations`: the player's registrations, scoped by `registration.player` with the `ownerRef` claim that
  already scopes the characters, projected to the event, the character, the status, the ticket and the payment
  fields. Decision stamps, accounts and transfer bookkeeping stay behind.
- `rulebookChapters`: lore pages of category rules that players may read, ordered, as REQ-RRS-005 of
  rules-rulebook-and-spell-lists asks. A chapter with a reveal moment is left out: the portal filter cannot compare it
  with the current time, so it fails closed.

Not in this change: the character choice and payment buttons on a registration, the role preferences and the question
to the organisation the board also draws. Those are writes the portal contribution does not declare yet; skill lists
(spells as their own list) wait on rules-rulebook-and-spell-lists task 1.1.
