# Tasks: Beta Cross-Surface Alignment (Larpinq)

## 1. Code metadata (`appinfo/info.xml`)

- [x] 1.1 Add `lang="en"`/`lang="nl"` `<name>` and `<summary>` (real Dutch, not machine-copied English)
- [x] 1.2 Rewrite `<description>` (en + nl) to the canonical feature list
- [x] 1.3 Fix `<licence>` from `agpl` to `EUPL-1.2` (matches `LICENSE` file)
- [x] 1.4 Fix `<website>`/`<bugs>`/`<repository>` from stale `LarpingNextApp` to real `codeberg.org/Conduction/larpingapp` (verified via `git remote -v`)
- [x] 1.5 Add `<app>openregister</app>` to `<dependencies>`
- [x] 1.6 Confirm `img/app.svg` — white fill, 24×24, no change needed

## 2. Code features (`src/manifest.json` + `manifest.d/`)

- [x] 2.1 Read nav/menu labels and page titles; confirm they already match the canonical feature list — no edits required

## 3. Product page (`conduction-website/src/pages/apps/larpingapp.mdx`)

- [x] 3.1 Rewrite hero tagline/intro to drop Scene/NPC language, describe real feature set
- [x] 3.2 Rewrite `FeatureList` (6 items) to canonical vocabulary
- [x] 3.3 Rewrite `RotatingCards` (Characters / Rules / Players)
- [x] 3.4 Rewrite `WidgetShelf` to the four real dashboard widgets
- [x] 3.5 Rewrite `Showcase` (Calendar+maps+sign-up / Files+Photos / Contacts+OpenRegister), drop Discord/Talk/Hilma
- [x] 3.6 Fix `PairCard` copy under "Pairs well with"

## 4. Product page — Dutch (`conduction-website/i18n/nl/docusaurus-plugin-content-pages/apps/larpingapp.mdx`)

- [x] 4.1 Bring structure up to parity with the English page (intro paragraph, FeatureList, PairRow, CtaBanner)
- [x] 4.2 Rewrite all copy to real Dutch matching the reconciled canonical feature list
- [x] 4.3 Fix `secondaryCta` doc link from `docs.conduction.nl/larpingapp` to `larpingapp.conduction.nl`

## 5. Docs (`larpingapp/docs/`)

- [x] 5.1 Verify `docs/FEATURES.md`, `docs/features/*.md`, `docs/Schema/*.json`, `docs/README.md` already match code — no edits needed (used as canonical source)
- [x] 5.2 Replace placeholder junk in `docs/readme.md` with a real "About Larping" paragraph

## 6. Verification

- [x] 6.1 Grep `lib/`, `src/` for `faction|npc|scene|discord|talk|hilma` to confirm none of the removed claims exist in code
- [x] 6.2 Confirm actual git remote matches the corrected info.xml repository URLs
