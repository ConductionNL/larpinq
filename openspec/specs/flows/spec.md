---
status: implemented
retrofit: true
---

# Flows

## Purpose

Game masters read and edit the automations behind their campaign (a reminder before a deadline, a confirmation on submission) from inside larpinq. The flow engine and its one store are OpenRegister's (ADR-065), and the list, canvas and sidebar controls are the shared nextcloud-vue components. Larpinq declares the authoring surface, scopes it to its own flows, and mounts the sidebar that names, describes and configures a flow. This spec was written after the fact (spec round of 7 October 2026) and describes what the code does today.

**Source files:**
- `src/manifest.json` menu entry `FlowsMenu` (section `settings`, the Advanced foldout of the navigation)
- `src/manifest.json` page `Flows` (route `/flows`, type `index`, `config.entitySource: "flows"`, `config.app: "larpinq"`)
- `src/manifest.json` page `FlowDetail` (route `/flows/:id`, type `flow`, `config.app: "larpinq"`, `sidebarComponent: "FlowDetailSidebar"`)
- `src/views/flows/FlowDetailSidebar.vue`: mounts nextcloud-vue's `CnFlowSidebar` in the Nextcloud app sidebar
- `src/registry.js`: registers `FlowDetailSidebar` (kind `page`)
- `src/manifest.json` walkthrough step `see-flows`: points new users at the Flows navigation entry

Capability row: `adm-workflows`.

@e2e exclude covered in the browser by tests/e2e/app-chrome.spec.ts ("the settings foldout carries Personal settings, Admin settings and Flows"), which proves the entry renders; opening and saving a flow on the canvas has no browser test yet, and per-scenario @e2e anchors belong in a code change, not in this docs-only spec round.

## Requirements

### Requirement: The app MUST list its own flows

The app MUST provide a `Flows` page at route `/flows`, reached from the `Flows` entry in the navigation's settings section. The page MUST list the flows from OpenRegister's flow store that belong to larpinq (`config.app: "larpinq"`), and MUST NOT list flows that other apps own.

#### Scenario: A game master opens the flows list

- GIVEN OpenRegister holds two flows owned by larpinq and one owned by another app
- WHEN a game master opens the Advanced foldout of the navigation and chooses Flows
- THEN the page at `/flows` MUST list the two larpinq flows
- AND MUST NOT list the other app's flow

### Requirement: A flow MUST open on a canvas with its controls in the app sidebar

The app MUST provide a `FlowDetail` page at route `/flows/:id` that renders the shared flow canvas for that flow, scoped to larpinq. The controls that name the flow, describe it, choose its trigger and edit a step MUST render in the Nextcloud app sidebar through `FlowDetailSidebar`, so the canvas keeps the full width. The route with the literal id `new` MUST open an empty flow on the same canvas.

#### Scenario: Open an existing flow

- GIVEN a larpinq flow "Reminder before the cancel-by date"
- WHEN a game master opens it from the Flows list
- THEN the canvas MUST show its steps
- AND the app sidebar MUST show its name, description and trigger, ready to edit

#### Scenario: Start a new flow

- WHEN a game master opens `/flows/new`
- THEN the canvas MUST open empty
- AND the app sidebar MUST offer the controls to name the flow and pick its trigger

### Requirement: The walkthrough MUST point new users at the flows

The app's walkthrough MUST include the optional step `see-flows`, titled "Where the automation lives", that points at the Flows navigation entry, explains what flows do and that nothing has to be built yet, and moves on when the user opens the Flows page.

#### Scenario: The walkthrough reaches the flows step

- GIVEN a new user follows the walkthrough
- WHEN the tour reaches the step `see-flows`
- THEN it MUST show "Where the automation lives" next to the Flows navigation entry
- AND opening the Flows page, or choosing next, MUST move the tour on without building a flow
