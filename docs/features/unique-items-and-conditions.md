# Unique items and conditions

Some things in your world exist only once. The crown of a kingdom, a named sword, a curse that rests on one person. Mark an item or a condition as unique and Larpinq keeps it with one character.

## What happens when you save

You give a unique item to a character while another character still holds it. Larpinq refuses the save and tells you who has it:

> Crown of Aldmoor is already held by Queen Isolde. Remove it there first.

A unique condition works the same way:

> Curse of the Ashen King is already on Mirela. Remove it there first.

The message appears on the character page and on the item or condition page. It uses the language set in your Nextcloud profile.

The rule holds from both sides:

- Adding the item to a second character is refused.
- Adding a second character to the item is refused.
- Switching `unique` on while two characters hold the item is refused. The message names both holders.

Items and conditions that are not unique can go to as many characters as you like.

## Find conflicts from before the rule

Data you entered before this rule existed can still hold a unique item on two characters. Your administrator lists those with one command:

```bash
occ larpinq:unique-holders:check
```

The command prints every unique item and condition with more than one holder, and names the holders. It exits with code 1 while a conflict exists and 0 when there is none. Fix a conflict by removing the item from all but one character.
