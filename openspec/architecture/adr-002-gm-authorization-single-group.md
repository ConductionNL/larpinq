# ADR-002: GM Authorization Is One Canonical Group, Declared Once

## Status

Proposed

## Date

2026-07-07

## Context

Game-master authority is LarpingApp's only domain-specific privilege
tier, and its group id is currently declared independently in every
place that needs it:

- `lib/Controller/EventsController.php:47` — `private const GM_GROUP = 'gamemasters';` (run-sheet download guard)
- `lib/Listener/CharacterRequirementListener.php:61` — `private const GM_GROUP = 'gamemasters';` (requirement-override authoring guard)
- OpenRegister schema-level RBAC on `xpAward` (create/update/delete restricted to `gamemasters`, per `openspec/changes/archive/event-xp-award-workflow/tasks.md` 1.2)
- OpenRegister notification rules targeting the `gamemasters` group (`openspec/specs/notifications/spec.md`)

Separately, `CharactersController::downloadPdf` treats "NC admin" as the
GM tier (`lib/Controller/CharactersController.php:146`), so the codebase
today has two competing definitions of "is a GM": admin-only in one
controller, `gamemasters`-or-admin in another.

Four independent declarations of one string is drift waiting to happen: a
rename (or a deployment that provisions a differently-named group) would
silently split GM authority — some guards would pass, others would 403,
and OR-side RBAC would disagree with app-side guards.

## Decision

1. The GM tier is defined by exactly one Nextcloud group, canonical id
   `gamemasters`, declared once in PHP as a public constant on
   `OCA\LarpingApp\AppInfo\Application` (e.g. `Application::GM_GROUP`).
   All server-side guards MUST reference that constant; per-class
   `private const GM_GROUP` duplicates are forbidden.
2. NC administrators are always implicitly GM-privileged (admin OR
   GM-group membership passes every GM guard). No guard may be
   admin-only where the semantic requirement is "GM".
3. Declarative surfaces that cannot reference the PHP constant (OR schema
   `authorization` blocks, `x-openregister-notifications` rules in
   `lib/Settings/larpingapp_register.json` / `register.d/`) MUST use the
   literal `gamemasters` and MUST be listed in this ADR's inventory when
   added, so a rename has a checklist.
4. Renaming the GM group is a BREAKING change: it requires a coordinated
   update of the PHP constant, every declarative occurrence, and a
   migration/repair step for existing installs — never a partial edit.

## Consequences

- Positive: one grep target (`Application::GM_GROUP`) answers "who is a
  GM"; guards, OR RBAC, and notifications cannot drift apart unnoticed;
  new GM-gated endpoints get the check pattern for free.
- Positive: the admin-vs-GM ambiguity is resolved by rule 2 — pending
  changes (e.g. player-character-sheet-access) build on admin-or-GM
  rather than inventing a third variant.
- Negative: the group id remains a convention, not runtime-configurable;
  making it configurable would require threading app-config into OR
  schema RBAC and is explicitly out of scope until a deployment needs it.
- Follow-up (code, small): replace the two `private const GM_GROUP`
  declarations with the shared `Application::GM_GROUP` constant when
  either file is next touched.
