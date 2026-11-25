---
kind: code
depends_on: []
---

# Proposal: admin-phone-friendly-pages

## Summary

At a LARP site nobody carries a laptop. The steward at the gate checks people
in on a phone, a player looks up their character sheet between scenes, a game
master checks who has not arrived. Larpinq is a Nextcloud web app that was
never checked at phone width. This change names the pages people use on a
phone and makes each work at 360 pixels wide: no sideways scrolling, tap
targets big enough, tables that fold into cards, and the check-in scan usable
with one hand. A Playwright run at phone size keeps it that way.

## Motivation

One admin row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build`: two competitors rate it yes.

**`adm-mobile-app`**, "Use the tool from a mobile app or a phone-friendly
site." Larpinq rates it no. Matrix evidence: "grep -rniE
'mobile|pwa|manifest.webmanifest' lib/ src/: no hits; larpinq is a standard
Nextcloud web app (nc-vue components), with no dedicated mobile app or
installable PWA manifest of its own".

- LarpManager (yes): "larpmanager/templates/structure.html:6 responsive viewport with about 90 media queries in larpmanager/static/larpmanager/assets/css/larpmanager/, phone QR check-in page larpmanager/templates/larpmanager/orga/checkin.html; no native app" (source read at main 36f23d3).
- pretix (yes): "src/pretix/presale/templates/pretixpresale/base.html:21 responsive viewport shop and the in-browser check-in (src/pretix/plugins/webcheckin) work on a phone; the native pretixSCAN/pretixPOS apps are separate first-party repos, noted not counted" (source read at tag v2026.7.0).
- MyLARP and Kanka are partial: responsive web views, no native app.

Both competitors rated yes deliver a phone-friendly site, not a native app;
this change does the same.

## Affected Projects

- [ ] Project: `larpinq`: phone-width fixes on the named pages and a phone-size Playwright suite.

## Scope

### In Scope

- The pages used on a phone: the Check-in tab (`EventRoster.vue`, with the scan panel of `events-qr-checkin`), CharacterDetail, the Stats tab, My registrations, the Cast page, Lore pages, the Dashboard and the Events index.
- At 360 by 740 pixels: no horizontal page scroll, tables that fold into stacked rows, tap targets at least 44 by 44 pixels on the check-in actions, text at least 16 pixels in inputs (so phones do not zoom).
- A Playwright project at phone size that opens each page and fails on horizontal overflow, and screenshots for the docs.

### Out of Scope

- A native app or an installable web app of larpinq's own: Nextcloud's own clients and the browser carry the pages.
- Offline use (row `evt-offline-checkin`, deferred).
- Pages a game master uses at a desk (mechanics editing, reports), which stay usable but are not tuned.

## Approach

Most pages are rendered by `@conduction/nextcloud-vue`; larpinq's own views
(`EventRoster.vue`, `SkillTree.vue`, the new section components) get
container-width styles with Nextcloud CSS variables only. Where a shared
component overflows, the fix belongs in nextcloud-vue and is reported rather
than patched in larpinq. Details in design.md.

## New Dependencies

None.

## Impact

- `src/views/EventRoster.vue` and larpinq's other section components: phone layout styles.
- `playwright.config.ts`: a `phone` project; `tests/e2e/phone/*.spec.ts` (new).

## Cross-Project Dependencies

Pages rendered by `@conduction/nextcloud-vue` (`CnIndexPage`, `CnDetailPage`,
widgets) get their phone behaviour from that library; overflow found there is
reported to nextcloud-vue.

## Risks

### Risk 1: A shared component overflows
**Severity:** Medium. **Mitigation:** the phone suite names the component; the fix goes to nextcloud-vue and the larpinq test is marked with the upstream issue until the release lands.

## Rollback Strategy

Revert the styles; the phone suite can stay as a report.

## Open Questions

None.
