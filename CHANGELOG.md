# Changelog

All notable changes to Larpinq are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Two curated MCP tools for Hermiq agents: `awardXpToAttendees` awards the same XP, with a reason, to every character checked in at one event (the server picks the recipients; a write with honest hints, so Hermiq asks a game master first, and OpenRegister's `gamemasters` rule on `xpAward` still decides each write), and `getCharacterSheet` reads a character's calculated sheet without the game master's private notes. `LarpinqScannableServices` opts exactly these two in; `xpAward` gains `awardedVia` (`mcp` on agent awards). Register 1.4.0.

## [0.3.0] - 2026-09-06

### Added

- Give larpinq the SPA catch-all, and move it to clean path URLs ([#651](https://github.com/ConductionNL/larpinq/pull/651))
- Give larpinq a Reports page with three declarative reports ([#677](https://github.com/ConductionNL/larpinq/pull/677))
- Give larpinq a Store ([#681](https://github.com/ConductionNL/larpinq/pull/681))
- Ask which example data to load, as cards, instead of a bare Run button ([#683](https://github.com/ConductionNL/larpinq/pull/683))

### Changed

- Raise Nextcloud floor to 32 and PHP floor to 8.3 ([#296](https://github.com/ConductionNL/larpinq/pull/296))
- Take @conduction/nextcloud-vue 2.25.1 for the KPI card ([#618](https://github.com/ConductionNL/larpinq/pull/618))
- Drop the stale larping_register.json ([#624](https://github.com/ConductionNL/larpinq/pull/624))
- Refresh the shared Conduction locks ([#628](https://github.com/ConductionNL/larpinq/pull/628))
- Take @conduction/nextcloud-vue 2.27.0 ([#631](https://github.com/ConductionNL/larpinq/pull/631))
- Take @conduction/nextcloud-vue 2.27.2 ([#640](https://github.com/ConductionNL/larpinq/pull/640))
- Rename the skill slug to larping_skill ([#645](https://github.com/ConductionNL/larpinq/pull/645))
- Let the linter see tests/ and scripts/ ([#655](https://github.com/ConductionNL/larpinq/pull/655))
- Navigate the router, not the URL fragment ([#660](https://github.com/ConductionNL/larpinq/pull/660))
- Collect the unit tests that were sitting in an uncollected directory ([#669](https://github.com/ConductionNL/larpinq/pull/669))
- Take nextcloud-vue 2.31.1, so a hovered KPI tile stops nesting ([#674](https://github.com/ConductionNL/larpinq/pull/674))

### Fixed

- Main has no branch-protection.yml at all, the required check has no producer ([#258](https://github.com/ConductionNL/larpinq/pull/258))
- Aim main's config at development ([#598](https://github.com/ConductionNL/larpinq/pull/598))
- Import useObjectStore, which was called but never imported ([#620](https://github.com/ConductionNL/larpinq/pull/620))
- Cover the schema strings that broke the check ([#622](https://github.com/ConductionNL/larpinq/pull/622))
- Drop the pre-rename register file the gate rejects ([#625](https://github.com/ConductionNL/larpinq/pull/625))
- Drop hardcoded KPI colours so the cards follow the theme ([#636](https://github.com/ConductionNL/larpinq/pull/636))
- Declare ext-xsl so dependabot can resolve ([#633](https://github.com/ConductionNL/larpinq/pull/633))
- Make the npm overrides follow their direct dependency ([#643](https://github.com/ConductionNL/larpinq/pull/643))
- Drop the second Refresh from the dashboard header ([#646](https://github.com/ConductionNL/larpinq/pull/646))
- Expect the slug the register actually ships, larping_skill ([#649](https://github.com/ConductionNL/larpinq/pull/649))
- Guard the AppHost builder with class_exists ([#653](https://github.com/ConductionNL/larpinq/pull/653))
- Add the load() the canonical route already points at, and finish path routing ([#657](https://github.com/ConductionNL/larpinq/pull/657))
- Assert path URLs, not hash, now the router runs in history mode ([#663](https://github.com/ConductionNL/larpinq/pull/663))
- Stop routing health and metrics larpinq cannot serve, and clear development ([#666](https://github.com/ConductionNL/larpinq/pull/666))
- Declare the health checks the endpoint reports ([#671](https://github.com/ConductionNL/larpinq/pull/671))
- Make the chrome specs pass in a real browser ([#679](https://github.com/ConductionNL/larpinq/pull/679))
- The demo-data test arranges its choice, and the Suggest-feature CTA is a link ([#685](https://github.com/ConductionNL/larpinq/pull/685))

## [0.2.5] - 2026-08-30

### Changed

- Raise Nextcloud floor to 32 and PHP floor to 8.3 ([#296](https://github.com/ConductionNL/larpinq/pull/296))

### Fixed

- Main has no branch-protection.yml at all, the required check has no producer ([#258](https://github.com/ConductionNL/larpinq/pull/258))
- Aim main's config at development ([#598](https://github.com/ConductionNL/larpinq/pull/598))

## [0.2.4] - 2026-08-30

### Changed

- Raise Nextcloud floor to 32 and PHP floor to 8.3 ([#296](https://github.com/ConductionNL/larpinq/pull/296))

### Fixed

- Main has no branch-protection.yml at all, the required check has no producer ([#258](https://github.com/ConductionNL/larpinq/pull/258))
- Aim main's config at development ([#598](https://github.com/ConductionNL/larpinq/pull/598))

## [0.1.28] - 2026-08-30

- Maintenance release.

## [0.1.26] - 2025-04-23

- Maintenance release.

## [0.1.25] - 2025-04-14

- Maintenance release.

## [0.1.24] - 2025-04-13

- Maintenance release.

## [0.1.23] - 2025-03-13

- Maintenance release.

## [0.1.22] - 2025-02-25

- Maintenance release.

## [0.1.21] - 2025-02-04

- Maintenance release.

## [0.1.20] - 2025-01-06

- Maintenance release.

## [0.1.19] - 2025-01-06

- Maintenance release.

## [0.1.18] - 2025-01-01

- Maintenance release.

## [0.1.17] - 2024-12-30

- Maintenance release.

## [0.1.16] - 2024-12-29

- Maintenance release.

## [0.1.15] - 2024-12-28

- Maintenance release.

## [0.1.14] - 2024-12-28

- Maintenance release.

## [0.1.13] - 2024-12-28

- Maintenance release.

## [0.1.12] - 2024-12-16

- Maintenance release.

## [0.1.11] - 2024-10-28

- Maintenance release.

## [0.1.10] - 2024-10-15

- Maintenance release.

## [0.1.9] - 2024-09-24

- Maintenance release.

## [0.1.8] - 2024-09-20

- Maintenance release.

## [0.1.7] - 2024-09-17

- Maintenance release.

## [0.1.6] - 2024-09-10

- Maintenance release.

## [0.1.5] - 2024-09-10

- Maintenance release.

## [0.1.4] - 2024-09-09

- Maintenance release.

## [0.1.3] - 2024-09-06

- Maintenance release.

## [0.1.2] - 2024-09-06

- Maintenance release.

## [0.1.1] - 2024-09-06

- Maintenance release.

[Unreleased]: https://github.com/ConductionNL/larpinq/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/ConductionNL/larpinq/compare/v0.2.5...v0.3.0
[0.2.5]: https://github.com/ConductionNL/larpinq/compare/v0.2.4...v0.2.5
[0.2.4]: https://github.com/ConductionNL/larpinq/compare/v0.1.28...v0.2.4
[0.1.28]: https://github.com/ConductionNL/larpinq/compare/v0.1.26...v0.1.28
[0.1.26]: https://github.com/ConductionNL/larpinq/compare/v0.1.25...v0.1.26
[0.1.25]: https://github.com/ConductionNL/larpinq/compare/v0.1.24...v0.1.25
[0.1.24]: https://github.com/ConductionNL/larpinq/compare/v0.1.23...v0.1.24
[0.1.23]: https://github.com/ConductionNL/larpinq/compare/v0.1.22...v0.1.23
[0.1.22]: https://github.com/ConductionNL/larpinq/compare/v0.1.21...v0.1.22
[0.1.21]: https://github.com/ConductionNL/larpinq/compare/v0.1.20...v0.1.21
[0.1.20]: https://github.com/ConductionNL/larpinq/compare/v0.1.19...v0.1.20
[0.1.19]: https://github.com/ConductionNL/larpinq/compare/v0.1.18...v0.1.19
[0.1.18]: https://github.com/ConductionNL/larpinq/compare/v0.1.17...v0.1.18
[0.1.17]: https://github.com/ConductionNL/larpinq/compare/v0.1.16...v0.1.17
[0.1.16]: https://github.com/ConductionNL/larpinq/compare/v0.1.15...v0.1.16
[0.1.15]: https://github.com/ConductionNL/larpinq/compare/v0.1.14...v0.1.15
[0.1.14]: https://github.com/ConductionNL/larpinq/compare/v0.1.13...v0.1.14
[0.1.13]: https://github.com/ConductionNL/larpinq/compare/v0.1.12...v0.1.13
[0.1.12]: https://github.com/ConductionNL/larpinq/compare/v0.1.11...v0.1.12
[0.1.11]: https://github.com/ConductionNL/larpinq/compare/v0.1.10...v0.1.11
[0.1.10]: https://github.com/ConductionNL/larpinq/compare/v0.1.9...v0.1.10
[0.1.9]: https://github.com/ConductionNL/larpinq/compare/v0.1.8...v0.1.9
[0.1.8]: https://github.com/ConductionNL/larpinq/compare/v0.1.7...v0.1.8
[0.1.7]: https://github.com/ConductionNL/larpinq/compare/v0.1.6...v0.1.7
[0.1.6]: https://github.com/ConductionNL/larpinq/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/ConductionNL/larpinq/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/ConductionNL/larpinq/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/ConductionNL/larpinq/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/ConductionNL/larpinq/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/ConductionNL/larpinq/releases/tag/v0.1.1
