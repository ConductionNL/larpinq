# Care details and their erasure after an event

A weekend in a forest needs to know who carries an epipen, who cannot eat nuts and whom to call when someone falls. A player can record this on their own profile. Each event they are accepted for gets a copy for its first aid and kitchen, and that copy is erased after the event, with a record of what was erased.

## What the player records

On their player page a player fills in:

- **Allergies**, such as "hazelnuts"
- **Medical notes**
- **Dietary needs**
- **Emergency contact** and **Emergency contact phone**

All five are stored encrypted. Only the player themself and game masters can read or change them. Another player sees none of them: not on the player page, not in a list or a search, and not in a CSV export of players.

## The copy for an event

When a game master accepts a player's registration for an event, Larpinq copies the player's care details into a care record for that event. Only game masters can read care records.

- When the player changes their details before the event starts, the copy is updated.
- Once the event has started, the copy no longer changes, so the team works from the list they had at the start.
- A player who records no care details gets no care record.

## Erasure after the event

Each care record is due for erasure a number of days after the event ends: 30 by default. A game master sets another number in **Keep care details (days)** on the event.

OpenRegister's retention workflow handles the erasure:

1. After the due date, OpenRegister's destruction job puts the event's care records on a destruction list.
2. A game master (or the archivist group your organisation configured) approves the list.
3. OpenRegister destroys the records and issues a destruction certificate. The certificate lists which records were destroyed, when and on whose approval, without the erased content.

The player's own profile keeps their details: only the event copies are erased.

## When a player asks what is held about them

A request to see or erase personal data goes to OpenRegister's data-subject workflow. Larpinq has no screen of its own for such requests. The player profile and any care records that are still held are what Larpinq keeps about a player.

## Good to know

- Encrypted fields cannot be searched. Find a player by name, then open their page.
- Each care record carries its own due date, the event's end plus the event's days, set when the record is copied or updated.
