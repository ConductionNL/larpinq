/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Manifest ↔ registry reachability.
 *
 * `src/registry.js` registering a component makes it RESOLVABLE, not
 * REACHABLE. A `kind: "section"` entry only renders if some manifest page
 * names it — from `config.sidebar.tabs[].component`, a `page.slots.*` value,
 * or `config.sections[].component`. A component that is registered and never
 * named is dead UI: it ships, it passes lint, its backend is unit-tested, and
 * no user can get to it.
 *
 * That is exactly what happened to the event check-in roster (#286). The
 * openspec task "T6: Add the roster / check-in surface to src/manifest.json"
 * was ticked while the manifest edit was never made, and nothing caught it
 * because:
 *   - `EventRoster.vue` has no mount test that would have failed,
 *   - a SEPARATE body widget with the confusingly similar id `event-roster`
 *     (a read-only `object-list`) existed, so a grep for "roster" in the
 *     manifest hit, and
 *   - the only user-visible symptom was an orphan `Check-in` key in
 *     `l10n/en.json` with no call site.
 *
 * These tests assert on the TAB OBJECT itself — its `component` binding and
 * its label — never merely on the presence of a `tabs` array. An assertion
 * that the sidebar "has tabs" passes against the broken tree, because the
 * broken tree had an audit tab.
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

const manifest = JSON.parse(
	fs.readFileSync(path.join(ROOT, 'src/manifest.json'), 'utf8'),
)
const registrySource = fs.readFileSync(path.join(ROOT, 'src/registry.js'), 'utf8')
const enTranslations = (() => {
	const raw = JSON.parse(fs.readFileSync(path.join(ROOT, 'l10n/en.json'), 'utf8'))
	return raw.translations || raw
})()

/**
 * Page lookup by manifest id.
 *
 * @param {string} id The manifest page id.
 * @return {object} The page object.
 */
function page(id) {
	const found = (manifest.pages || []).find((p) => p.id === id)
	if (!found) throw new Error(`No manifest page with id "${id}"`)
	return found
}

/**
 * Registry keys, read out of the module SOURCE rather than by importing it.
 *
 * `src/registry.js` imports `.vue` single-file components. This vitest project
 * runs in the `node` environment with no Vue plugin (see vitest.config.js), so
 * importing the module would throw on the first `.vue` import and the test
 * would fail for a reason that has nothing to do with what it measures.
 *
 * @return {string[]} The exported registry keys.
 */
function registryKeys() {
	const body = registrySource.slice(registrySource.indexOf('export default'))
	return [...body.matchAll(/^\t([A-Za-z0-9_$]+):\s*\{/gm)].map((m) => m[1])
}

describe('manifest ↔ registry reachability', () => {
	it('registry.js still registers EventRoster', () => {
		// Positive control for the test below: if this key is ever renamed,
		// the manifest assertion must fail LOUDLY rather than silently pass
		// against a tab pointing at a component that no longer exists.
		expect(registryKeys()).toContain('EventRoster')
	})

	it('EventDetail exposes the check-in roster as a sidebar tab', () => {
		const tabs = page('EventDetail').config.sidebar.tabs
		const checkin = tabs.find((t) => t.component === 'EventRoster')

		// Assert on the TAB, not on `tabs.length` or on the array existing.
		expect(
			checkin,
			'no EventDetail sidebar tab renders the EventRoster component',
		).toBeDefined()
		expect(checkin.id).toBe('checkin')
		expect(checkin.label).toBe('Check-in')
		expect(checkin.icon).toBe('AccountCheck')

		// `component` and `widgets` are mutually exclusive — CnObjectSidebar
		// warns and drops `widgets` when both are set.
		expect(checkin.widgets).toBeUndefined()
	})

	it('the check-in tab component resolves against the registry', () => {
		const tabs = page('EventDetail').config.sidebar.tabs
		const componentTabs = tabs.filter((t) => t.component)

		expect(componentTabs.length).toBeGreaterThan(0)
		for (const tab of componentTabs) {
			expect(
				registryKeys(),
				`sidebar tab "${tab.id}" names component "${tab.component}", which src/registry.js does not export — CnObjectSidebar.resolveTabComponent() would console.warn and render nothing`,
			).toContain(tab.component)
		}
	})

	it('the check-in surface strings are in the translation catalogue', () => {
		// The orphan `Check-in` key in l10n/en.json was the ONLY visible
		// symptom of #286 — 8 strings translated into 36 locales with nothing
		// rendering them. These are EventRoster.vue's own `t()` strings, which
		// DO get translated at runtime, so they must stay in the catalogue for
		// the now-reachable tab to render in a user's language.
		//
		// Deliberately NOT asserted: that `tab.label` itself is in the
		// catalogue. CnObjectSidebar renders sidebar tabs with
		// `:name="tab.label"` — raw, with no translate() call — unlike
		// CnAppNav, which routes menu labels through `resolveLabel()` ->
		// `effectiveTranslate()`. Asserting it would fail on the 10
		// pre-existing "History" tab labels and would imply a translation
		// that the library does not actually perform. Tracked separately.
		for (const key of [
			'Check in',
			'No-show',
			'Checked in',
			'Registered',
			'Attendance tracking is unavailable — showing the participant list read-only.',
			'No confirmed participants for this event yet.',
		]) {
			expect(
				enTranslations,
				`EventRoster source string "${key}" is missing from l10n/en.json`,
			).toHaveProperty(key)
		}
	})

	it('the body object-list and the sidebar check-in tab are different surfaces', () => {
		// Guards the specific confusion that hid #286: a widget id that reads
		// like the roster but is a read-only list with no check-in controls.
		const cfg = page('EventDetail').config
		const bodyRoster = cfg.widgets.find((w) => w.id === 'event-roster')

		expect(bodyRoster.type).toBe('object-list')
		expect(bodyRoster.component).toBeUndefined()

		const checkin = cfg.sidebar.tabs.find((t) => t.id === 'checkin')
		expect(checkin.component).toBe('EventRoster')
	})
})
