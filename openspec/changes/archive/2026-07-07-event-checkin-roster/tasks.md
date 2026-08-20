# Tasks: event-checkin-roster

- [x] T1: Add `lib/Settings/register.d/event-checkin-roster.json` — an ADR-037
  fragment adding the `larping_attendance` schema (`event`, `character`,
  `status` enum, `checkedInAt`, `checkedInBy`, each with a gate-28 `title`) plus
  its `gamemasters`-group write RBAC config. Do NOT edit the monolithic
  `larpingapp_register.json`. Namespaced slug `larping_attendance` per the
  cross-app schema-slug collision rule.
- [x] T2: JSON-validate the fragment and simulate the deep-merge to prove no
  existing schema/property is removed and the new schema shape is correct.
- [x] T3: Register the `larping_attendance` slug in the frontend schema-slug set
  (`src/store`) so the manifest data layer can read/write it.
- [x] T4: Add a GM-only `recordAttendance` action to
  `lib/Controller/EventsController.php`: guard the acting user (`gamemasters`
  group or admin), validate the `(event, character)` participant scope, stamp
  `checkedInAt` (server clock) and `checkedInBy` (session uid), and persist via
  the OR object write (RBAC OR-delegated — no app-local auth path). Register the
  route with the correct auth attributes.
- [x] T5: Add a roster read for the event detail page that lists confirmed
  participants with player name, character type and current attendance status.
- [x] T6: Add the roster / check-in surface to `src/manifest.json` for the event
  detail page using `@conduction/nextcloud-vue` CnDataTable (no bespoke table);
  GM controls to set `checked-in` / `no-show`, read-only for non-GMs; graceful
  empty/degraded state when the attendance schema is absent.
  NOTE: the surface is placed as the EventDetail "Check-in" sidebar tab (a
  registry kind:"section", same proven path as the Location/Sign-up tabs),
  rather than a config-grid body widget — the detail page uses the CnDetailPage
  config-grid whose custom body widgets require catalog registration; the
  sidebar-tab placement keeps the CnDataTable roster on the event detail page
  without that complexity. Built from CnDataTable (ADR-012), GM controls, and
  degrades read-only.
- [~] T7: Integration — make the `event-xp-award-workflow` batch-award roster
  read attendance to pre-select `checked-in` and de-select `no-show`
  participants (default only; GM can override). No re-derivation of attendance.
  DEVIATION: the `event-xp-award-workflow` batch-award roster UI was itself
  DEFERRED in that change (no batch modal exists in `src/`), so there is no
  surface to wire. Attendance is nonetheless readable by any XP-award surface
  (records exposed via the OR objects API + surfaced on the roster status
  column and the run-sheet). The pre-selection default lands when the batch
  modal ships (tracked with the award-workflow follow-up).
- [x] T8: Integration — optionally surface each cast member's attendance status
  in `EventsController::buildRunsheetContext` (`pdf-export`), additive.
- [x] T9: Unit tests (run in the Nextcloud container per config.yaml): non-GM
  refused; non-participant refused; server-stamped provenance overrides body;
  degraded/absent-schema path never throws. Minimum 75% coverage for new code
  (ADR-009).
- [x] T10: i18n — nl + en strings for the roster surface and statuses (ADR-005);
  bump `appinfo/info.xml` version for cache-bust.
- [~] T11: Docs — `docs/features/` entry with Playwright screenshots of the
  roster / check-in surface (ADR-010).
  DEVIATION: `docs/features/event-checkin-roster.md` added; Playwright
  screenshots deferred — the frontend bundle cannot be built/served in this
  environment (no node_modules), see the live-run-deferred note.
- [x] T12: Run `openspec validate event-checkin-roster --strict` until clean.

## Acceptance criteria

- `larping_attendance` is an OR-native schema (namespaced slug) added via an
  ADR-037 fragment; no app-local attendance table exists.
- Check-in is GM-only and server-stamped (`checkedInAt` / `checkedInBy` never
  taken from the client body); non-GM and non-participant writes are refused.
- The event detail page hosts a roster with GM check-in controls (read-only for
  players) built from nc-vue CnDataTable; it degrades read-only when attendance
  storage is absent and never throws.
- Attendance is readable by the XP-award roster and the run-sheet without
  re-derivation.
- `openspec validate event-checkin-roster --strict` passes.
