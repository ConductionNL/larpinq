# Check in by QR code

At the gate of a larp, stewards check in dozens of players in a few minutes. Scrolling a roster for each name is slow. With a check-in code, the steward scans the player's QR code, sees their name and character, and the roster updates on its own.

## Show your code as a player

Every accepted registration has a check-in code. Larpinq makes it when a game master accepts the registration, and makes a new one when the registration is handed to another player. The old holder's code then stops working.

1. Open **My registrations** and choose your registration.
2. Go to the **Check-in code** tab.
3. Show the QR code at the gate, or choose **Print** to take it on paper. The print shows only the code, your name, your character and the event.

Only you and the game masters can read your code. A pending or waitlisted registration has no code yet.

## Check players in at the gate

1. Open the event and go to the **Check-in** tab.
2. Choose **Scan codes**.
3. Point the camera at a player's QR code. Without a camera, or in a browser that cannot read QR codes, type the code in **Check-in code** and press Enter. A handheld scanner types the code and presses Enter for you.

The panel shows the result for three seconds:

- **Checked in: Anna de Vries, Mirela the Wanderer**: the character is checked in on the roster.
- **Already checked in at 18:02 by joris**: the code was used before. Nothing changes.
- **Unknown code for this event**: the code belongs to no registration of this event, or to another event.
- **This registration is not accepted**: the registration was cancelled, declined or is still waiting.
- **This registration has no character yet**: the player has not chosen a character. Check them in from the roster once they have.

Only game masters can check players in. The endpoint accepts at most 120 codes per minute per steward.

## Codes for registrations accepted before this feature

When larpinq is upgraded, every accepted registration without a code gets one. Players find it on the **Check-in code** tab straight away.

Next: read [the check-in roster](./event-checkin-roster.md) to see how check-ins feed attendance and XP, then hand your stewards a phone or a scanner for the gate.
