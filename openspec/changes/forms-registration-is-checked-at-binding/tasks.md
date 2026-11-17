# Tasks: forms-registration-is-checked-at-binding

- [x] 1.1 Question-to-property mapping on the binding; validator call on save
  - `event.signupFormMapping` (register.d/forms-registration-is-checked-at-binding.json); `SignupFormBinding` hands the mapping and the listener's own values to OpenRegister's `FormDestinationValidatorInterface` against the registration schema; `SignupFormBindingListener` refuses an event write that changes the binding with findings, and one whose binding cannot be judged. Tests: `tests/unit/Service/SignupFormBindingTest.php`, `tests/unit/Listener/SignupFormBindingListenerTest.php`.
- [ ] 1.2 `FormSubmissionListener` creates through `FormSubmitService` with idempotency key
  - Files: lib/Listener/FormSubmissionListener.php
  - Test: unit test for one registration on a repeated event; control for a refused binding
  - Not built: OpenRegister's `FormSubmitService::submit()` writes a fresh submit under the submitter's RBAC, and registration `create` is game masters only (registration-intake-and-capacity), so routing the listener through it would refuse every player's sign-up. Needs an in-process app-authority submit in OpenRegister first.
- [ ] 2.1 `composer check:strict`, `npm run lint`, `openspec validate forms-registration-is-checked-at-binding --strict`
