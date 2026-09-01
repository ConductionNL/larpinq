/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Minimal @nextcloud/logger stub for the offline Vitest suite.
 *
 * The real package reaches `@nextcloud/auth` → `@nextcloud/browser-storage` at
 * MODULE LOAD, which touches `window` and therefore throws in this suite's
 * `node` environment. Aliasing it keeps the store tests DOM-free — the same
 * reason @nextcloud/router and @nextcloud/auth are already stubbed here — and
 * keeps the assertions about `src/store/modules/settings.js` about the store's
 * own fetch/error lifecycle rather than about logging transport.
 *
 * The builder is chainable and the logger methods are silent no-ops, so an
 * error path under test logs without writing noise into the test output.
 */

function noop() {}

const logger = {
	debug: noop,
	info: noop,
	warn: noop,
	error: noop,
	fatal: noop,
}

export function getLoggerBuilder() {
	const builder = {
		setApp: () => builder,
		setUid: () => builder,
		detectUser: () => builder,
		build: () => logger,
	}
	return builder
}
