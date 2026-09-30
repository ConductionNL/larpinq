# Copy a world

A new season often runs on the rules of the last one. A game master copies a world's rules into a new world in one step, instead of entering every skill again.

## What is copied

The copy holds a copy of every ability, effect, skill, item and condition of the world, its lore pages and its extra character fields. Inside the copy, every reference points at the copied object: a skill that requires Swordsmanship requires the copy of Swordsmanship, and an effect on Strength works on the copy of Strength. A reference to something shared by all worlds stays as it is.

Characters, players, events, XP awards and attendance are not copied. Items and conditions in the new world start without holders.

## Copy a world

1. Open **Worlds** and choose the world.
2. Open **Actions** and choose **Copy world**. The dialog shows how many objects of each kind the copy creates.
3. Enter the name of the new world, for example "Aldmoor season 2", and choose **Copy world**.
4. Choose **Open the new world** to go to it.

## Who can copy

Only game masters (the `gamemasters` group) and administrators see the action and may call `POST /api/worlds/{id}/copy`. A player gets 403.

A world with more than 2000 rules objects is refused, so a copy always finishes in one request. When a copy fails halfway, larpinq removes what it had created and names anything it could not remove.

Next: write the lore of the new season in [Lore pages](./lore-pages.md).
