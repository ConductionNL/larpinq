---
kind: code
---

# PDF character sheet download has no frontend entry point — the shipped spec is fiction

## Why

`openspec/specs/pdf-export/spec.md` §"PDF Download Flow (Frontend)" (lines 169-217) declares six
MUST requirements, **all marked `Implemented`**:

- PDF-040: "Character detail page MUST have 'Als pdf downloaden' action button"
- PDF-041: "Clicking the button MUST open a modal to select a template"
- PDF-042: "Template selector MUST fetch from DocuDesk API: `GET /apps/docudesk/api/templates?namespace=larpingapp`"
- PDF-043: "Clicking 'Download PDF' MUST open the PDF URL in a new browser tab"
- PDF-044: "Download button MUST be disabled until a template is selected and template list is loaded"
- PDF-045 / PDF-046: modal instructional text + Cancel/Help/Download action buttons

None of this exists in `src/`. Verified at HEAD:

- `grep -rli pdf src --include=*.vue --include=*.js` matches exactly **one** file:
  `src/views/ObjectDetail.vue`, and its only occurrence is a code comment —
  `ObjectDetail.vue:19`: "the corresponding host div is omitted and the detail page continues to
  work normally (**DocuDesk PDF pattern**)" — a passing mention, not a button.
- There is no `RenderPdfFromCharacter.vue` (the component the spec's own "Key source files" list
  and the sibling `openspec/changes/player-character-sheet-access/proposal.md` both name).
- `grep -rn "Als pdf downloaden"` (the exact button label PDF-040 requires) returns zero hits
  anywhere in the repo.
- `src/manifest.json` has no `pdf`/`download`/`template` action entry on the character detail
  page (`grep -in "pdf\|download" src/manifest.json` is empty).
- The spec's own header (`pdf-export/spec.md:9`) still carries the **stale** e2e-exclusion
  reason "larpingapp Vue SPA fails to mount at localhost:8080" — the fix for that (#202) shipped
  months ago per every sibling spec (`character-management/spec.md:9`,
  `game-mechanics/spec.md:9`, `dashboard/spec.md`, etc. all say "SPA mount fixed in #202"), and
  `tests/e2e/spec-coverage/spa-ui.spec.ts` proves the SPA mounts and drives character/event
  flows today. The frontend PDF requirements were simply never revisited after the mount fix —
  they describe a UI surface that was either removed, never built, or copy-pasted from a sibling
  app's spec template and never implemented here.

The backend half is real: `lib/Controller/CharactersController.php::downloadPdf` and the route
`/characters/{id}/download/{template}` (`appinfo/routes.php:7`) exist, are unit-tested
(`tests/unit/Controller/CharactersControllerTest.php`), and are the exact target of the sibling
`openspec/changes/player-character-sheet-access` change (owner-based access to the same
endpoint). That change fixes **who** may call the endpoint; it does not add a way for a player or
GM to discover and click a download action from the UI — after it ships, the corrected
authorization still has zero reachable caller in the product.

This is the "phantom green" pattern the fleet review is hunting for at its worst: a spec marked
`status: implemented` with every requirement row saying `Implemented`, for a user-facing feature
that a player cannot actually use.

## What Changes

- Add a "Download as PDF" action to the character detail page (`src/views/ObjectDetail.vue` or a
  new `src/modals/CharacterPdfDownloadModal.vue`, NcModal-based per the modal-isolation house
  rule) that: probes DocuDesk availability, lists templates via DocuDesk's template API scoped to
  `namespace=larpingapp`, and opens `/characters/{id}/download/{template}` in a new tab on
  confirm.
- Gate the action's visibility on DocuDesk being installed/enabled (mirrors the backend's 424
  contract) and on `canAccessCharacter` from `player-character-sheet-access` once that change
  lands (own character, GM, or admin) — this change does not duplicate that authorization logic,
  it only adds the caller.
- Correct `openspec/specs/pdf-export/spec.md:9`'s stale `@e2e exclude` reason and add a
  `tests/e2e/spec-coverage/` (or extend `spa-ui.spec.ts`) scenario that actually drives the
  button → modal → download flow, closing the phantom-green gap instead of re-stating it.
- Re-verify PDF-040 through PDF-046 against the real implementation once built; downgrade any
  that still don't hold to `status: Planned` rather than `Implemented`.

Not BREAKING: additive UI; no existing route or component is removed.
