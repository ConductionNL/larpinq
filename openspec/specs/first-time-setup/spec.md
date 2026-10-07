# first-time-setup Specification

## Purpose
The first-time setup wizard gets a new Larpinq installation to a working state. The operator picks an example dataset on a card and loads it from that card, the setup status reports every step the manifest declares, and repairing the register is an admin settings action rather than a wizard step. This capability defines those steps and what the server accepts and refuses while running them.

## Requirements

### Requirement: Each example data card loads itself

The `demo-data` setup step MUST be a cards choice step with `loadAction: load-demo-data`. The setup wizard MUST NOT carry a separate run-action step that loads the picked dataset.

#### Scenario: The operator loads a dataset from its card

- GIVEN the setup wizard shows the example data cards
- WHEN the operator presses Load on a card
- THEN the wizard posts `{ "dataset": <card value> }` to `/api/setup/action/load-demo-data`
- AND the server loads that dataset
- AND the server records the dataset as the pick only after the load succeeds
- @e2e exclude the card and its spinner are CnSetupWizard UI, tested in nextcloud-vue; the posted body is covered by tests/unit/Controller/SetupControllerTest.php

#### Scenario: An unknown dataset is refused

- GIVEN a dataset id that no card offers
- WHEN it is posted to `/api/setup/action/load-demo-data`
- THEN the server answers 400 with `success: false`
- AND nothing is loaded or stored
- @e2e exclude API contract without a browser path of its own; covered by tests/unit/Controller/SetupControllerTest.php

#### Scenario: A call without a body keeps working

- GIVEN a dataset was stored through `/api/setup/config`
- WHEN `/api/setup/action/load-demo-data` is called without a body
- THEN the stored dataset is loaded
- @e2e exclude API contract without a browser path of its own; covered by tests/unit/Controller/SetupControllerTest.php

#### Scenario: A failed load leaves the step open

- GIVEN the load of the posted dataset fails
- WHEN the server answers
- THEN the answer carries `success: false`
- AND no pick or decision is stored
- @e2e exclude needs a load that fails on a live instance; covered by tests/unit/Controller/SetupControllerTest.php

### Requirement: Setup status reports every manifest step

`GET /api/setup/status` MUST report a `done` state for every step id in `manifest.setup.steps`.

#### Scenario: The status ids match the manifest

- GIVEN the Larpinq manifest
- WHEN an administrator reads `/api/setup/status`
- THEN `steps` holds an entry for every manifest step id
- AND the retired load step is not reported
- @e2e exclude API contract without a browser path of its own; covered by tests/unit/Controller/SetupControllerTest.php

### Requirement: Provisioning runs from the admin settings page

Provisioning, repair and register-import actions MUST NOT be wizard steps. The admin settings page MUST offer the register import. In Larpinq that is the existing Re-import button, which imports the register and schemas past the version gate. The `provision` setup action MUST keep working for runbooks and scripts.

#### Scenario: The administrator repairs the register from the admin page

- GIVEN an administrator on the Larpinq admin settings page
- WHEN they press Re-import
- THEN the page posts to `/api/settings/reimport`
- AND the register and schemas are imported again
- AND the result message is shown on the page
- @e2e exclude admin settings button calling an existing setup action; the action is covered by tests/unit/Controller/SetupControllerTest.php

#### Scenario: The wizard no longer asks

- GIVEN the Larpinq manifest
- WHEN the setup steps are read
- THEN none of `provision` is a step
- @e2e exclude a manifest property; asserted by tests/unit/Controller/SetupControllerTest.php
