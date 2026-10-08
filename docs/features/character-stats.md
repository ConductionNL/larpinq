# Character stats

Open a character and choose **Stats** in the sidebar. You see every ability with its base value and its final value. Larpinq works these numbers out each time you open the tab, from the character's skills, items, conditions, events and XP awards.

## Why a number is what it is

Select an ability to see the modifiers that moved it, in the order Larpinq applied them. Each line names the change and where it came from:

- `+3 from Swordsmanship (skill)`
- `+1 from Iron shield (item)`
- `-2 from Cursed (condition)`

A negative modifier is shown in the error colour, so a penalty stands out. An ability that nothing changed shows its base value and "No modifiers".

## XP earned, spent and left

The line at the top of the tab shows the character's experience points:

| | What it counts |
|---|---|
| XP earned | The XP ability's base value plus every XP award and every other increase |
| XP spent | Every decrease, such as the XP cost of a skill |
| XP left | What remains. This is the same number Larpinq checks when you add a skill, so a purchase that the tab shows as affordable will not be refused for lack of XP |

Larpinq finds the XP ability by name: an ability called "XP", or one whose name contains "experience".

## Who sees the tab

Whoever can read the character can read its stats. Game masters see the stats of every character. A player sees the stats of their own character. Anyone else gets "Could not load the stats."

## For developers

The tab reads `GET /apps/larpinq/api/characters/{id}/stats`. The response lists `abilities` (id, name, base, final and the ordered `modifiers`, each with `source`, `sourceId`, `sourceName`, `effectName`, `change`, `old` and `new`) and `xp` (`ability`, `earned`, `spent`, `left`, or `null` when no XP ability exists). An id the caller cannot read answers 404.
