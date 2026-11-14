---
kind: code
screens: [LrTelefoon]
---

# Proposal: my-character-sheet

## Why

The LrTelefoon board draws what a player sees on their phone: "Mijn karakterblad" first in the menu, their own
character with its stats, skills and what they carry. larpinq had no player-facing entry: the character list is the
game master's list of every character in the world, and a player had to find their own in it.

## What changes

- A menu entry and page "My character sheet" (`/my-characters`): the signed-in player's own characters
  (`ownerUid` is the current user), as cards, each opening the character page. The character schema already lets a
  player read and update their own characters, and a game master who also plays sees only their own here.

Not in this change: the one-character phone layout of the board (stats with their modifiers, skills, carried items,
check-in at the event and the language switch). The character page shows the same data today; a phone layout and
check-in (events-qr-checkin) are follow-ups.
