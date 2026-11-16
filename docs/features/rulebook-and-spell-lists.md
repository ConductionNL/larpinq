# Rulebook and spell lists

Spells and powers live in their own lists, each paid from its own pool, and players read the rules of their world as a book they can print.

## Skill lists

Open **Mechanics**, then **Skill lists**, and choose **Add**. A list has:

- **Name**, such as Arcane spells.
- **Kind**: skills, spells or powers.
- **Paid from**: the ability the costs of its skills are paid from, such as Mana. Leave it empty to pay from XP.
- **Description** and **Order**, the place of the list in the rulebook.
- **World**: the world the list belongs to.

Open a skill and set **List** to place it in a list. A skill in no list stays a general skill, paid from XP. The cost of a skill is still an effect on an ability: Firebolt costs 3 mana through an effect that lowers Mana by 3.

The **Skills** list shows the list of each skill, and a list's own page shows the skills in it.

## One pool per list

When a game master adds skills to a character, larpinq checks every pool the character pays from: XP, and the ability of every list one of the character's skills is in. A change that would take any pool below zero is refused, and the refusal names the pool. A character with 30 XP and 2 mana who is given Firebolt (3 mana) is refused with a shortfall of 1 on Mana, however much XP is left.

## The rulebook

Open **World**, then **Rulebook**. It shows the rulebook of the world chosen in the world switcher, or of every world when none is chosen; `/rulebook/<world>` opens the rulebook of one world.

The rulebook starts with the rules chapters: the lore pages of the category **rules**, in their order. The lore read rules apply, so a player reads a chapter only when it is visible to players and revealed. Write the chapters as lore pages (see [Lore pages](./lore-pages.md)).

After the chapters comes the reference:

- the abilities of the world,
- every skill list with its kind, its pool and its skills, each with its cost (such as *Firebolt, 3 mana*), the skills it requires and its effect,
- the general skills,
- the items and the conditions.

Choose **Print** to print the rulebook or save it as a PDF; each chapter starts on a new page.

## Secret rules

Set **Hidden from the rulebook** on a skill, a skill list, an item or a condition to keep it out of the rulebook, for example a forbidden rite players must discover in play. Game masters still see it everywhere else.

## In the portal

Players who use the portal find the rulebook under **Regelboek**: the rules chapters they may read, the skill lists (without those hidden from the rulebook) and the skills.

## Demo data

The demo data holds the world Aldmoor with the lists Martial skills, Arcane spells (paid from Mana) and Divine powers, the spells Firebolt (3 mana) and Ward (2 mana) that require Arcane lore, the hidden Blood rite, and the chapters *How combat works* and *Casting a spell*.
