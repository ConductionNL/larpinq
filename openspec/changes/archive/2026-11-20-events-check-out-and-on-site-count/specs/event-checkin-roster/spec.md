## ADDED Requirements

### Requirement: A game master checks a participant out (REQ-ECO-001)

The attendance status SHALL include `checked-out`. A game master SHALL be able to check out a participant who is checked in, and check them in again. The server SHALL stamp `checkedOutAt` and `checkedOutBy`, and SHALL ignore those values in the request, as it does for check-in. Checking out SHALL be refused for a participant who is not checked in.

#### Scenario: A player leaves on Saturday night

- GIVEN Isolde Raaf is checked in for the Wintertocht
- WHEN game master Anna checks her out at 22:10 on Saturday
- THEN Isolde's row reads "uitgecheckt za 22:10"
- AND `checkedOutBy` is Anna's user id

#### Scenario: Checking out someone who never arrived

- GIVEN Grimm has status `registered`
- WHEN a game master tries to check him out
- THEN the request is refused as an invalid status change

### Requirement: The roster shows who is on site now (REQ-ECO-002)

The roster SHALL show a count of who is on site now, where on site means checked in and not checked out again, as LrEvenementInchecken draws it. A participant who checks in again after checking out SHALL count as on site.

#### Scenario: The count follows check-outs

- GIVEN 40 participants checked in and 2 of them checked out
- WHEN a game master opens the check-in tab
- THEN it shows 38 on site now
- AND the 2 checked-out participants are listed as checked out
