// SPDX-License-Identifier: AGPL-3.0-or-later
// SPDX-FileCopyrightText: Conduction B.V. <info@conduction.nl>
//
// Custom-component registry for LarpingApp's manifest-driven app shell.
//
// EMPTY ON PURPOSE. Every LarpingApp page is a declarative manifest page type
// — `dashboard`, 9× `index`/`detail` (character / player / ability / skill /
// item / condition / effect / event / setting) and `settings`. An entry here
// means a page (or sidebar tab / settings section) that does NOT fit a
// built-in type; adding one requires an explicit justification. Deleting
// entries is the right direction (ADR-024).
//
// Resolution order at runtime (first hit wins):
//   1. Built-in page types          (CnIndexPage, CnDetailPage, …)
//   2. Built-in widget types        (version-info, data, metadata, …)
//   3. customComponents (this file) ← consumer-injected components
//
// See @conduction/nextcloud-vue → docs/migrating-to-manifest.md.

export default {}
