# forms-registration Delta: forms-registration-is-checked-at-binding

**Status**: draft
**Scope**: Nextcloud Forms bindings for event registration. Implements hydra `form-submits-into-its-destination-object`.

## ADDED Requirements

### Requirement: A Forms binding MUST be valid against the registration schema when it is made

Binding a Nextcloud Forms form to an event SHALL map its questions to registration properties and run OpenRegister's form destination validator. A binding with findings SHALL NOT be saved, from the first release (decision 181).

#### Scenario: A form without the player's name cannot be bound
- **GIVEN** a registration schema requiring `playerName`
- **AND** a Forms form with no question mapped to it
- **WHEN** an organiser binds it to an event
- **THEN** the binding is refused with `required-unmapped` on `playerName`

### Requirement: A Forms submit MUST create the registration through the submit service

On `FormSubmittedEvent` the listener SHALL create the registration through OpenRegister's submit service, with the Forms submission id as idempotency key.

#### Scenario: A submit creates one registration
- **GIVEN** a valid binding
- **WHEN** a player submits the form, and the event fires twice
- **THEN** exactly one registration exists
