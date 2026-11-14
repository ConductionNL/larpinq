# Casting and crew

Many larps are cast. The game masters write the characters, the players say which
ones they would like to play, and larpinq proposes who plays whom. Volunteers fill
crew and NPC roles at the same event.

## Opening characters for casting

A game master opens a written character for casting by setting its
`castingEvent` to the event. Only game masters can set it. The event's
`castingDeadline` says until when players can rank.

## Ranking

A player with a registration for the event ranks up to five open characters, 1
for the one they want most. A ranking is a `castingPreference`. Game masters and
the player who made it can read it. Other players cannot.

## The proposal

`GET /api/events/{id}/casting/proposal` returns the best overall casting and
changes nothing. Only game masters can ask for it.

- Each player gets at most one character, and each character at most one player.
- As many ranking players as possible get a character they ranked.
- Among those castings, the total of the ranks given is the lowest possible.
- When two castings tie, the one with more first choices wins. If that also ties,
  the earlier sign-ups get the better ranks.
- A player who gets nothing they ranked is listed as unmatched. Larpinq never
  gives a player a character they did not rank.

Only accepted registrations take part, and only rankings of characters open for
casting on the event count. The proposal takes at most 500 players and 500
characters. 500 by 500 is solved in well under a second.

The answer also counts, per open character, how many players ranked it at each
rank.

## Confirming

`POST /api/events/{id}/casting/confirm` with `{"pairs": [{"registration": ..., "character": ...}]}`
writes the pairs the game master kept or changed. For each pair:

- the character gets the registration's player as owner (`ocName`) and leaves casting;
- the registration gets the character.

The writes are ordinary object writes as the game master, so the usual checks
run. A pair is refused by name when the registration is not accepted on the
event, the character is not open for casting on it, either appears twice, or a
write fails. The other pairs are still written.

## Crew and NPC roles

A game master defines `crewRole`s per event: a name, a kind (`crew` or `npc`), a
number of places and a description. A `crewAssignment` puts a registration on a
role. Game masters assign; the volunteer reads their own assignment.

## Not built yet

- The Casting tab on the event page and the ranking on My registrations.
- Enforcing the casting deadline on a ranking.
- The Winter Court 2026 example data.
