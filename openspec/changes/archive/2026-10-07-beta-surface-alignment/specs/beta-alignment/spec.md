## ADDED Requirements

### Requirement: info.xml Metadata Is Bilingual and Accurate

`appinfo/info.xml` SHALL declare `<name>` and `<summary>` in both `lang="en"` and `lang="nl"` (the Dutch text SHALL be genuinely translated, not an English copy), SHALL declare a `<licence>` matching the repository's actual `LICENSE` file, SHALL point `<website>`/`<bugs>`/`<repository>` at the app's real git remote, and SHALL record every app the code hard-depends on: machine-readably in `src/manifest.json` `dependencies`, and as a comment inside `<dependencies>` in `appinfo/info.xml` (the App Store schema has no `<app>` child there, so an element would fail App Store validation).

#### Scenario: Dutch summary is real Dutch

- **GIVEN** `appinfo/info.xml`
- **WHEN** the `<summary lang="nl">` element is read
- **THEN** its text MUST be idiomatic Dutch describing the app's real features, not a copy of the English summary
- @e2e exclude static metadata file, no browser-navigable UI surface

#### Scenario: Declared OpenRegister dependency

- **GIVEN** the app ships a `Repair\InitializeRegister` install/post-migration step
- **WHEN** `src/manifest.json` and the `<dependencies>` block of `appinfo/info.xml` are read
- **THEN** `src/manifest.json` MUST list `openregister` under `dependencies`, and `appinfo/info.xml` MUST name the openregister dependency in a comment rather than an `<app>` element the App Store schema rejects
- @e2e exclude static metadata file, no browser-navigable UI surface

### Requirement: Product Page Describes Only Shipped Features

The product page (`conduction-website/src/pages/apps/larpinq.mdx`, formerly `larpingapp.mdx`, and its Dutch translation) SHALL describe only features that exist in `src/manifest.json`, `lib/Controller/*`, or `docs/FEATURES.md`, and SHALL NOT reference entities, mechanics, or integrations that do not exist in the codebase.

#### Scenario: No fabricated entities or integrations

- **GIVEN** the product page copy (en + nl)
- **WHEN** the copy is checked against `src/manifest.json` pages/schemas and `lib/` for the terms "Scene", standalone "NPC" entity, "Discord", "Talk", "Hilma"
- **THEN** none of these terms MUST appear as a described feature or integration, because none of them exist in the shipped code
- @e2e exclude marketing copy audit, no browser-navigable UI surface distinct from the visual-coverage gate already covering the page render

#### Scenario: English and Dutch product pages carry equivalent content

- **GIVEN** `src/pages/apps/larpinq.mdx` (English) and `i18n/nl/docusaurus-plugin-content-pages/apps/larpinq.mdx` (Dutch)
- **WHEN** their section structure (hero, feature list, pairs-well-with, CTA) is compared
- **THEN** both language versions MUST present the same canonical feature list and the same outbound doc/GitHub links
- @e2e exclude marketing copy audit, no browser-navigable UI surface distinct from the visual-coverage gate already covering the page render

### Requirement: Docs Reflect the Same Canonical Feature List

`larpinq/docs/` SHALL use the same feature vocabulary as `src/manifest.json` and the product page, and SHALL NOT contain placeholder or filler content in place of real documentation.

#### Scenario: No placeholder content in docs landing page

- **GIVEN** `docs/readme.md`
- **WHEN** its content is read
- **THEN** it MUST contain a real description of the app, not placeholder text
- @e2e exclude static docs file, no browser-navigable UI surface
