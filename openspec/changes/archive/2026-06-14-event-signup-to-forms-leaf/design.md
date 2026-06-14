# Design — event-signup-to-forms-leaf

## Context

README promises Event Subscriptions (registrations + waiting lists) and "players
register for events". No implementation exists. This is greenfield — the ideal
moment to apply ADR-022 before any bespoke form code is written.

## Decision

Implement event sign-up / waiting-list intake by consuming the OpenRegister
**forms leaf** (ADR-019, ADR-022). The forms leaf owns the form definition and
the submissions; LarpingApp owns only the thin event-domain rules layered on
top.

### Why a leaf, not in-app

- Forms is an OR integration-registry leaf. A bespoke sign-up form +
  submission store would duplicate the forms abstraction (ADR-022
  anti-pattern), and would also re-solve validation/spam/accessibility that the
  leaf already handles.
- Building it as a leaf from the start avoids a future "consume-OR-abstraction"
  migration issue entirely.
- Precedent: PDF export delegates wholesale to DocuDesk.

### Boundary

| Concern | Owner |
|---|---|
| Sign-up form definition + submissions | OR forms leaf |
| Capacity / confirmed-vs-waitlisted ordering | LarpingApp event-domain logic |
| Event participation (`players[]`) | LarpingApp (fed from submissions) |

The waiting-list order derives from submission order; confirmed players land in
`players[]`, the remainder are the waitlist. The decision logic is small and
LARP-specific, so it stays in-app — but the intake surface is the forms leaf.

## Alternatives considered

- **Hand-rolled Vue sign-up form + a `larpingapp_signups` table** — rejected:
  parallel forms machinery + parallel submission store, both ADR-022
  anti-patterns ("Parallel link tables", forms reinvention).
- **Nextcloud Forms app directly** — rejected: intake belongs behind the OR
  forms leaf so submissions link to the OR event object uniformly (same posture
  as calendar/contacts).

## Risks

- The forms leaf must support binding a submission to an OR object (the event)
  and exposing submissions back to the host for the capacity/waitlist logic. If
  the leaf lacks submission read-back, that's an upstream OR prerequisite —
  tracked as a dependency, not built here.
