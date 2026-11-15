# character-management delta

## ADDED Requirements

### Requirement: A game master rejects a submitted character with a reason (REQ-CTA-001)

Besides approving it, a game master SHALL be able to reject a submitted character with a reason. A rejected character MUST show its state and the reason on its page and in the Characters list, and its owner MUST be notified. The owner MAY change the character and submit it again.

#### Scenario: A character needs another pass

- GIVEN Anna submitted "Mirela the Wanderer" for approval
- WHEN game master Joris rejects it with the reason "Pick one bloodline, not two"
- THEN "Mirela the Wanderer" shows rejected and the reason on her page and in the Characters list
- AND Anna is notified of the rejection and the reason

#### Scenario: A rejected character is submitted again

- GIVEN "Mirela the Wanderer" was rejected
- WHEN Anna changes the bloodline and submits her again
- THEN "Mirela the Wanderer" waits for approval again
