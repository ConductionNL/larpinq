---
kind: code
depends_on: [players-self-signup]
---

# Proposal: communication-help-desk

## Summary

Players have questions: can I bring my own sword, is there a vegan option, my
character's skill is missing. Today those go to someone's private mail and get
lost. This change adds a help desk: a player asks a question, from larpinq or
from the portal, optionally about an event or a character; game masters see
open questions in one list, answer them, and close them; the player is told
when an answer arrives and can reply until the question is closed.

## Motivation

One communication row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build`: two competitors rate it yes.

**`com-help-desk`**, "Let players send questions or problems to the organisers
through a help desk inside the tool." Larpinq rates it no. Matrix evidence:
"grep -rniE 'help ?desk|support ticket|ticket' lib src ...: no
player-to-organiser question flow; the only player-facing surface is the
portaliq contribution provider (lib/Portal/PortalContributionProvider.php),
which submits characters".

- LarpManager (yes): "larpmanager/models/miscellanea.py:48-100 HelpQuestion with attachment and closed flag, larpmanager/urls/user.py:75 and larpmanager/urls/event.py:429 help, organisers answer and close via orga_questions_answer and orga_questions_close" (source read at main 36f23d3).
- MyLARP (yes): "'Every campaign has its own Help Desk players can submit problems or questions to'. https://mylarp.com"

What the fleet already offers, checked before designing: portaliq has no
help desk a contributing app can reuse, but its open change
`inbox-notifications-and-preferences` lets a contributing app declare a field
change worth a portal notification; OpenRegister's Talk leaf gives chat rooms
per object, which carry no status or assignment. This change uses the first and
not the second.

## Affected Projects

- [ ] Project: `larpinq`: question and message schemas, pages for players and game masters, portal action and collection, and notifications.

## Scope

### In Scope

- `helpQuestion`: asker (a player), subject, first message, optional event or character, status (open, answered, closed), assignee (a game master), timestamps.
- `helpMessage`: the thread under a question, from the asker or a game master.
- Players ask and reply on a "Help" page in larpinq, and through the portal (an `askQuestion` action and a `myQuestions` collection).
- Game masters see open questions, assign, answer, and close them on a Help desk page; a count of open questions on the Dashboard for game masters.
- Notifications: a new question to game masters; an answer to the asker (Nextcloud notification for account users, the portal's notification rule for portal players).

### Out of Scope

- Attachments on questions (a later change can add the files leaf).
- A public FAQ built from answered questions.

## Approach

Two register schemas with row rules and notification rules; manifest pages;
the portal contribution gets one action and one collection with a declared
notification. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/communication-help-desk.json` (new): `helpQuestion`, `helpMessage`, rules, lifecycle and notifications.
- `lib/Portal/PortalContributionProvider.php`: `askQuestion`, `myQuestions`, and the declared notification on `status`.
- `lib/Listener/HelpMessageListener.php` (new): a reply by the asker reopens an answered question; a game master reply sets it answered.
- `src/manifest.d/communication-help-desk.json` (new): Help and Help desk pages; `src/manifest.json`: a Dashboard count for game masters (in place).

## Cross-Project Dependencies

portaliq's contribution contract (actions and collections) and its open change
`inbox-notifications-and-preferences` (a contribution declares a field change
that notifies the portal subject). Until that lands, portal players see the
answer when they open "My questions".

## Risks

### Risk 1: Questions nobody answers
**Severity:** Medium. **Mitigation:** the Dashboard count and the Help desk list sorted by age; questions open longer than 3 days are marked.

### Risk 2: A question reveals a secret
**Severity:** Low. **Mitigation:** a question is readable by its asker and by game masters only; nothing is shown to other players.

## Rollback Strategy

Remove the fragments and the provider entries. Questions stay as data.

## Open Questions

None.
