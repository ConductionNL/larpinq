---
status: implemented
---

# Tasks

## 1. Integration wiring
- [x] 1.1 Confirm the OR forms leaf is exposed via the integration registry (ADR-019) and supports binding a submission to an OR object + submission read-back — consumer-only check against `window.OCA.OpenRegister.integrations.isRegistered('forms')`
- [x] 1.2 Add the forms leaf host to `src/views/ObjectDetail.vue` for the `event` object type — `ObjectDetail.vue` renders `[data-integration-host="forms"]` for event objectType; manifest `EventDetail` slot `forms-leaf: ObjectDetail`

## 2. Submission ownership
- [x] 2.1 Persist sign-up submissions through the forms leaf / OR forms abstraction (no app-local submission store) — `event` schema linkedTypes fragment (`lib/Settings/register.d/event-signup-to-forms-leaf.json`) declares the binding; submissions are owned by the OR forms abstraction

## 3. Event-domain rules (thin, in-app)
- [x] 3.1 Derive confirmed-vs-waitlisted from submission order against event capacity — thin classification rule lives in the event detail view (no separate signup store); reads submissions from the forms leaf via the OR object-interactions API in submission order
- [x] 3.2 Feed confirmed sign-ups into the Event `players[]` — confirmed submissions are mapped to player references on the OR event object via the same canonical write path used elsewhere

## 4. Graceful degradation
- [x] 4.1 Hide the sign-up surface and keep manual `players[]` editing when the forms leaf / registry is unavailable — `availableLeaves` returns no entries when registry/leaf absent; manual `players[]` field editing continues through the standard OR detail UI

## 5. Tests
- [x] 5.1 Frontend test: sign-up form renders and submits — `tests/e2e/spec-coverage/spa-ui.spec.ts` `event-signup-to-forms-leaf` describe block
- [x] 5.2 Test: waiting-list classification at capacity — same describe block, "waiting list forms when capacity is reached" scenario
- [x] 5.3 Frontend test: manual `players[]` fallback when leaf absent — same describe block, "sign-up hidden when integration registry absent" scenario
