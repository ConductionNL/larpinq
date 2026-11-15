# leaf-integrations delta

## ADDED Requirements

### Requirement: An event's organising milestones are Deck cards (REQ-LFI-001)

When the Deck app is enabled, an event SHALL track its organising milestones as Deck cards linked to the event. The stack a card sits in is its status and the card's due date is its deadline. The event page MUST show these cards in its Deck tab.

#### Scenario: The site booking is due

- GIVEN event "Winter Court 2026" and the Deck app is enabled
- WHEN a game master adds milestone card "Book the site" with due date 2026-09-01 in stack "To do" from the Deck tab
- THEN the Deck tab of "Winter Court 2026" lists "Book the site" as to do, due 2026-09-01

#### Scenario: A milestone is done

- GIVEN milestone card "Book the site" is in stack "To do"
- WHEN a game master moves it to stack "Done"
- THEN the Deck tab shows "Book the site" as done
