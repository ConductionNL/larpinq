# event-registration Specification (delta)

## Purpose

An accepted, priced registration is paid through shillinq, and larpinq shows
and acts on its payment state. From larpinq matrix rows `reg-online-payment`,
`reg-payment-status`, `reg-invoices`, `reg-payment-reminders` and
`reg-bank-statement-match`.

## ADDED Requirements

### Requirement: An accepted registration gets a payment request (REQ-RPS-001)

When a registration becomes accepted on an event that requires payment and its
price lines are above zero, larpinq SHALL append one payment request for it
through shillinq's payment requests leaf, with the registration's amount, the
player as debtor, the pay-by date as due date and a transfer reference, and
MUST show the player the payment link and the reference. Larpinq MUST NOT book
the payment itself. Until shillinq lets an app raise a request, larpinq raises
it when a game master accepts; a registration accepted any other way, or
refused by shillinq, SHALL wait as "payment to request" with its pay-by date,
and a game master MUST be able to request its payment from the registration.

#### Scenario: Anna gets her payment link

- GIVEN Anna's registration for "Winter Court 2026" is accepted with lines of EUR 85 and EUR 35
- WHEN a game master accepts the registration
- THEN shillinq holds one pending payment request of EUR 120 for her registration
- AND My registrations shows Anna the payment link and reference WC26-0001

### Requirement: The payment state follows shillinq (REQ-RPS-002)

When shillinq reports a registration's payment request as captured, larpinq
SHALL set the registration's payment state to paid, whether the payment came
through the link or through a bank transfer that shillinq matched by its
reference.

#### Scenario: A bank transfer is matched

- GIVEN Sanne paid by bank transfer quoting WC26-0002
- WHEN shillinq matches the statement line and captures the request
- THEN Sanne's registration shows paid on the event's registrations list

### Requirement: A game master uploads a bank statement to match transfers (REQ-RPS-007)

The Registrations list SHALL offer game masters an action to upload a bank statement (CAMT.053, MT940 or CSV). The upload MUST go to shillinq, which matches each incoming transfer to a payment request by its reference. A matched registration MUST show paid with the payment method bank transfer, and the lines that matched nothing MUST be listed for the game master.

#### Scenario: The Monday statement

- GIVEN Sanne's registration for "Winter Court 2026" is accepted with reference WC26-0002 and not paid
- WHEN a game master uploads a statement with a transfer of EUR 120 quoting WC26-0002 and one transfer without a reference
- THEN Sanne's registration shows paid by bank transfer
- AND the transfer without a reference is listed as not matched

### Requirement: Players are reminded before the pay-by date (REQ-RPS-003)

Three days before the pay-by date, larpinq SHALL send a player whose
registration is still open a Nextcloud notification and an email with the
payment link and reference.

#### Scenario: Anna forgot to pay

- GIVEN the pay-by date of "Winter Court 2026" is 2026-11-20 and Anna has not paid
- WHEN the daily job runs on 2026-11-17
- THEN Anna receives a reminder with her link and WC26-0001

### Requirement: An unpaid registration expires and frees its place (REQ-RPS-004)

One day after the pay-by date, an accepted registration whose payment request
is still pending SHALL be cancelled as unpaid, and its place MUST go to the
waiting list.

#### Scenario: The place goes to Pieter

- GIVEN Anna's request is still pending on 2026-11-21 and Pieter is first on the waiting list
- WHEN the daily job runs
- THEN Anna's registration is cancelled as unpaid
- AND Pieter's registration is accepted

### Requirement: Organisers see who paid (REQ-RPS-005)

The event page and the registrations list SHALL show each registration's
payment state and reference, filter on it, and count paid and open
registrations, without showing money totals.

#### Scenario: Who still owes

- GIVEN 18 accepted registrations of which 15 are paid
- WHEN a game master filters the registrations of "Winter Court 2026" on open
- THEN the 3 unpaid registrations are listed with their references

### Requirement: Invoices and receipts come from shillinq (REQ-RPS-006)

A player SHALL be able to ask for an invoice on their registration, which
larpinq passes on the payment request; larpinq MUST NOT produce invoices or
receipts itself. Without shillinq, a game master MUST be able to set the
payment state by hand.

#### Scenario: A club pays for its member

- GIVEN Joris needs an invoice for his club
- WHEN Joris ticks "Request an invoice" on his registration before it is accepted
- THEN the payment request for his registration carries the invoice request
