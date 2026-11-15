# Casting and crew

Many larps are cast. The game masters write the characters, the players say which
ones they would like to play, and larpinq proposes who plays whom. Volunteers fill
crew and NPC roles at the same event.

Game masters work on the **Casting** tab of the event. Players work on the
**Casting** tab of their registration, which they reach from **My registrations**.

## Open characters for casting

1. Write the character as usual and approve it, so players can read it.
2. Set **Open for casting on** to the event. Only game masters can set it.
3. On the event, set **Casting deadline**: until this moment players can rank.
   Without a deadline, ranking stays open.

## Rank the characters you would like to play

1. Open **My registrations** and the registration for the event.
2. Go to **Casting**. Every character open for casting on the event is listed.
3. Give up to five of them a rank, 1 for the one you want most. Each rank goes to
   one character only; leave the others on **Not ranked**.
4. Choose **Save ranking**.

Game masters and you can read your ranking. Other players cannot. From the
casting deadline on, the ranking can no longer be changed and the tab says so.
Larpinq also refuses a ranking on someone else's registration, of a character
that is not open on the event, or of a sixth character. Game masters can still
change rankings after the deadline.

## Propose the casting

The event's **Casting** tab shows, for each open character, how many players
ranked it at each rank, and below it the proposal. The proposal changes nothing
until it is confirmed.

- Each player gets at most one character, and each character at most one player.
- As many ranking players as possible get a character they ranked.
- Among those castings, the total of the ranks given is the lowest possible.
- When two castings tie, the one with more first choices wins. If that also ties,
  the earlier sign-ups get the better ranks.
- A player who gets nothing they ranked is listed under **Players without a
  ranked character**. Larpinq never gives a player a character they did not rank.

Only accepted registrations take part, and only rankings of characters open for
casting on the event count. The proposal takes at most 500 players and 500
characters.

## Confirm the casting

1. Change the proposal where you want: pick another character for a player, or
   **Not cast**. Giving a character to one player takes it from the player who
   had it.
2. Choose **Confirm casting**.

For each pair, the character gets the player as owner and leaves casting, and
the registration gets the character. These are ordinary writes, so the usual
checks run. A pair that cannot be written is listed by player under **Not
confirmed**; the other pairs are still written.

## Crew and NPC roles

1. Add a **Crew role** for the event: a name, a kind (crew or NPC), the number
   of places and a description.
2. On the event's **Casting** tab, each role shows how many of its places are
   filled. Pick a **Volunteer** and choose **Assign**. Only accepted
   registrations with a crew or NPC ticket are offered.

A volunteer sees their roles on the **Casting** tab of their registration.

## For developers

- `GET /api/events/{id}/casting/proposal`: the pairs, the unmatched registrations
  and the rank counts. Game masters only; writes nothing.
- `POST /api/events/{id}/casting/confirm` with
  `{"pairs": [{"registration": "...", "character": "..."}]}`: the pairs written and
  the pairs refused with a reason. Game masters only.
- Rankings (`larping_casting_preference`), crew roles (`larping_crew_role`) and
  crew assignments (`larping_crew_assignment`) are OpenRegister objects, written
  through the objects API. A pre-write listener holds the ranking rules above.

## Example data

The example data holds "Winter Court 2026" with a casting deadline of
1 November 2026 and four open characters: The Chancellor, The Spy, The Heir and
The Bard. Anna ranks The Heir 1 and The Spy 2, Pieter The Heir 1 and The Bard 2,
and Sanne The Spy 1, so the proposal is Anna The Heir, Pieter The Bard and Sanne
The Spy. The Bandits (NPC, 6 places) and the Kitchen (crew, 3 places) are its
roles, and Joris is in the Kitchen.
