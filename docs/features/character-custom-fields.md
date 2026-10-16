# Extra character fields

Every group's character sheet has fields no other group has: a bloodline, a guild rank, a patron god, a number of scars. Game masters add these fields per world, and players fill in the ones meant for them.

## Add a field

Open **World**, then **Character fields**, and choose **Add**. A field has:

- **Label**: the name on the sheet, for example "Bloodline".
- **Key**: the name the value is stored under, in lower case with hyphens, for example `bloodline`. Keep it once characters hold values.
- **Field type**: text, number, choice or yes or no. A choice field lists its **Choices**.
- **Visible to**: game masters only, or game masters and the player who owns the character.
- **World**: the world whose characters get the field. Leave it empty to give every world the field.
- **Order** and **Help text**: the place on the sheet and a hint under the field.

## Fill in the fields

Open a character and choose the **Extra fields** tab. It shows one input per field of the character's world. Choose **Save extra fields**. A value that does not fit its field, such as text in a number field, is refused, and the tab names the field.

## Who sees what

| | Game master | The player who owns the character | Other players |
|---|---|---|---|
| A field for game masters and the player | Reads and fills in | Reads and fills in | Nothing |
| A field for game masters only | Reads and fills in | Nothing, not even that the field exists | Nothing |

OpenRegister applies these rules to every way of reading, so the private values never reach a player through the list, the API or an export.

When a field is removed, the values stay on the characters. Game masters see them on the tab under "Values of fields that no longer exist".

Next: add the first field for your world under **Character fields**.
