# Import and export

You can take any list out of Larpinq as a spreadsheet, bring characters and players in from one, and move a whole campaign in one file. OpenRegister does the reading and writing; Larpinq switches it on for its pages.

## Export a list

Open a list, such as Characters, Players, Skills or Events, and choose **Export**, then **CSV** or **Excel**. You get the list as it is filtered on screen.

The file holds only what you may read. A player who exports the Cast list gets the characters and the fields a player can see, never the game master notes.

## Import characters or players

Open **Characters** or **Players** and choose **Import** in the actions menu. Pick a CSV or Excel file with one row per character or player and a column per field. OpenRegister reports how many rows it created, updated, left unchanged and could not read.

Only administrators see the import. OpenRegister lets only people who may manage the Larpinq register import into it, and by default that is administrators. The other lists have no import.

## Move a campaign

Open **Worlds** and choose **Export campaign**. You get one Excel workbook with a sheet per kind of record: characters, players, skills, items, conditions, effects, events, worlds, XP awards, abilities and attendance.

To bring a campaign back, choose **Import campaign** on the same page and pick such a workbook. Each sheet goes to its own kind of record, and a row with an id that already exists updates that record. A message tells you how many records were created, updated, left unchanged and failed.

- Export the campaign before you import one, so you can go back.
- Files, such as portraits and maps, are not in the workbook. They live in Nextcloud Files and your Nextcloud backup covers them.
- Only administrators see **Import campaign**. Everyone sees **Export campaign**, and the workbook holds only the records that person may read.
