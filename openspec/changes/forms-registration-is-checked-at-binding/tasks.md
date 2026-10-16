# Tasks: forms-registration-is-checked-at-binding

- [ ] 1.1 Question-to-property mapping on the binding; validator call on save
- [ ] 1.2 `FormSubmissionListener` creates through `FormSubmitService` with idempotency key
  - Files: lib/Listener/FormSubmissionListener.php
  - Test: unit test for one registration on a repeated event; control for a refused binding
- [ ] 2.1 `composer check:strict`, `npm run lint`, `openspec validate forms-registration-is-checked-at-binding --strict`
