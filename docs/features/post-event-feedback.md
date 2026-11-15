# Post-event feedback

After an event, the story team wants to know what every character did and what their player plans next. Larpinq asks every participant who was there for feedback through a Nextcloud Form, reminds those who forget, and shows game masters who answered. The form, its questions and the answers stay in Nextcloud Forms.

## Set up the feedback form

1. Make the form in Nextcloud Forms, for example "Summer Siege post-event letter".
2. Open the event in larpinq and edit it.
3. Choose the form as the **Feedback form**.
4. Leave **Feedback opens** and **Feedback closes** empty to use the defaults, or set them yourself. By default feedback opens at the end of the event and closes 21 days later.

## Who is asked, and when

Once a day larpinq looks at every event whose feedback is open:

- Every participant whose attendance is checked in gets one Nextcloud notification and one email with the link to the form. A participant marked no-show is not asked.
- Five days before feedback closes, every invited participant who has not answered gets one reminder.

Each invitation and reminder is recorded on the attendance record, so nobody is asked twice. Who answered is read from the form's submissions in Forms. If Forms cannot say who submitted, every invited participant gets the reminder.

## See who answered

Game masters open the event and the **Feedback** tab. It shows:

- when feedback opens and closes,
- how many of the invited participants answered, for example "1 of 2 answered",
- the characters whose players have not answered yet,
- a link to the answers in Nextcloud Forms.

Only game masters and administrators see the tab's data, through `GET /api/events/{id}/feedback`.
