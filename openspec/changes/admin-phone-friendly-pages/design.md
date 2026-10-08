# Design: admin-phone-friendly-pages

## Context

Read at development `77c85f0`.

- `src/views/EventRoster.vue` has no media or container queries; its roster
  is a table with per-row buttons (`:43-68`).
- Pages are manifest-driven through `@conduction/nextcloud-vue`; larpinq owns
  only its registered components (`EventRoster`, `SkillTree`, and those added
  by this pass: `CharacterStatSheet`, `EventXpAward`, `EventCasting`,
  `CharacterCustomFields`).
- `tests/e2e/visual/larpinq.visual.spec.ts` takes desktop visual snapshots;
  `playwright.config.ts` defines the projects.
- hydra ADR-010 and ADR-053: Nextcloud CSS variables and semantic tokens only.
  WCAG 2.2 target size (2.5.8) asks at least 24 by 24 pixels; this change uses
  44 on the gate's buttons because gloves and rain are real.

## Goals / Non-Goals

**Goals**: the listed pages work one-handed at 360 pixels; a test keeps them
so.

**Non-Goals**: native or installable app, offline, desk pages.

## Decisions

### D1. Container queries in larpinq's own components

Each registered component gets a container query at 600 pixels: tables become
stacked rows (label and value), action buttons go full width with 44 pixel
height, and the scan panel's code field takes the full width with a 16 pixel
font. Colours and spacing come from Nextcloud variables.

### D2. The phone suite

A Playwright project `phone` (viewport 360 by 740, touch, device scale 2)
opens each listed page with seeded data and asserts
`document.documentElement.scrollWidth <= innerWidth`, and that the check-in
buttons measure at least 44 by 44. It saves screenshots used by the docs.

### D3. Upstream fixes

When the overflow sits in a nextcloud-vue component, the test is marked with
the upstream issue and the fix is proposed there; larpinq does not override
the shared component's styles (hydra ADR-012).

## Declarative-vs-imperative decision

Not applicable: this change adds styles and tests, no business behaviour.

## Seed data

The existing demo data for "Winter Court 2026" and "Mirela the Wanderer".

## Risks / Trade-offs

- [Scope] Only the listed pages are tuned; the rest are checked for overflow by
  the same suite but not redesigned.

## Migration

None.

## Open Questions

None.
