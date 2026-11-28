# help-desk Specification

## Purpose

Players ask the organisers questions inside larpinq or the portal and hear
back; game masters answer from one list. From larpinq matrix row
`com-help-desk`.

## ADDED Requirements

### Requirement: Players ask questions (REQ-CHD-001)

A player SHALL be able to ask a question with a subject and a message,
optionally about an event or a character, and see their own questions and
their state. Other players MUST NOT see them.

#### Scenario: Anna asks about her bow

- GIVEN Anna is a player
- WHEN she asks "Can I bring a real bow?" about "Winter Court 2026" on the Help page
- THEN the question is listed as open on her Help page
- AND game masters are notified of a new question

### Requirement: Game masters answer from one list (REQ-CHD-002)

Game masters SHALL see open and answered questions on a Help desk page, oldest
activity first, assign them, answer them in the thread, and close them. An
answer MUST set the question to answered and notify the asker.

#### Scenario: Joris answers

- GIVEN Anna's question is open
- WHEN game master Joris answers "Only LARP-safe bows, checked at the gate"
- THEN the question is answered
- AND Anna receives a notification

### Requirement: The asker can reply until the question is closed (REQ-CHD-003)

The asker SHALL be able to reply to an answered question, which MUST reopen it;
a closed question MUST refuse new messages.

#### Scenario: A follow-up

- GIVEN Anna's question is answered
- WHEN Anna replies "Does a crossbow count?"
- THEN the question is open again on the Help desk

### Requirement: Portal players use the help desk too (REQ-CHD-004)

A player who uses the portal SHALL be able to ask a question and read their
questions and answers there.

#### Scenario: Lotte asks through the portal

- GIVEN Lotte uses the portal with a linked player profile
- WHEN she asks a question through the portal
- THEN it appears on the game masters' Help desk
- AND her portal list shows it with its state
