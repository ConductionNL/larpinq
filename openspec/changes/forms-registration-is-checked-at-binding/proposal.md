---
kind: code
depends_on: []
---

# Proposal: forms-registration-is-checked-at-binding

larpinq's part of decision 179 (Ruben, 10 October 2026): "when building a form we should know the destination object and the form should at least be valid against that." Cross-app change: `hydra/openspec/changes/form-submits-into-its-destination-object`, architecture in hydra ADR-117. Needs `openregister/form-destination-validator`. Ruben answered question Q6 (decision 181): Nextcloud Forms stays a source through a binding checked against the destination when it is made.

## Why

`FormSubmissionListener` creates a registration when a Nextcloud Forms form is submitted. The form's questions are never checked against the registration schema, so a form that misses a required property makes the listener fail after the player has sent it.

## What changes

1. **A binding names its destination and is checked when made.** Binding a Nextcloud Forms form to an event runs OpenRegister's form destination validator over the question-to-property mapping. A binding with findings is not saved.
2. **The listener creates through the submit service**, so the registration is validated against its schema the same way every other form submit is.
3. **The answers in Nextcloud Forms are that app's record.** larpinq stores no copy of its own.

## Rollback

The check refuses from the first release (decision 181). An existing binding with findings must be fixed before it can be saved again; it keeps working until then. Rolling back means reverting this change.
