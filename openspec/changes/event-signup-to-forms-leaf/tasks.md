---
status: draft
---

# Tasks

## 1. Integration wiring
- [ ] 1.1 Confirm the OR forms leaf is exposed via the integration registry (ADR-019) and supports binding a submission to an OR object + submission read-back
- [ ] 1.2 Add the forms leaf host to `src/views/ObjectDetail.vue` for the `event` object type

## 2. Submission ownership
- [ ] 2.1 Persist sign-up submissions through the forms leaf / OR forms abstraction (no app-local submission store)

## 3. Event-domain rules (thin, in-app)
- [ ] 3.1 Derive confirmed-vs-waitlisted from submission order against event capacity
- [ ] 3.2 Feed confirmed sign-ups into the Event `players[]`

## 4. Graceful degradation
- [ ] 4.1 Hide the sign-up surface and keep manual `players[]` editing when the forms leaf / registry is unavailable

## 5. Tests
- [ ] 5.1 Frontend test: sign-up form renders and submits
- [ ] 5.2 Test: waiting-list classification at capacity
- [ ] 5.3 Frontend test: manual `players[]` fallback when leaf absent
