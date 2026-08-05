/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CI regression config for the shared `E2E Tests (Playwright)` job.
 *
 * WHY A SECOND CONFIG EXISTS
 * --------------------------
 * The shared workflow (ConductionNL/.github/.github/workflows/quality.yml)
 * runs the suite as:
 *
 *     CONFIG="${{ inputs.playwright-test-path }}/playwright.config.ts"
 *     if [ ! -f "$CONFIG" ] && [ -f "playwright.config.ts" ]; then
 *       CONFIG="playwright.config.ts"
 *     fi
 *     npx playwright test --config="$CONFIG"
 *
 * Note what is missing: `--project`. Whichever config it picks, EVERY project
 * declared in it runs. The ROOT `playwright.config.ts` declares three:
 *
 *   chromium     — the regression suite. This is the one CI wants.
 *   docs-capture — journeydoc screenshot capture (ADR-030). It re-shoots every
 *                  tutorial screenshot into `docs/static/screenshots/…` and is
 *                  driven deliberately by `npm run test:e2e:docs`.
 *   visual       — pixel-diff baselines. Its own README records the reason it
 *                  cannot gate: the committed PNGs are host-font/GPU specific,
 *                  so a Linux CI runner does not byte-match a dev-container
 *                  baseline. Running it on CI produces guaranteed red that says
 *                  nothing about the app.
 *
 * Letting the root config be picked would therefore make every PR re-shoot the
 * documentation screenshots AND fail on baselines that cannot match. Rather
 * than delete or weaken either project, `playwright-test-path: tests/e2e` in
 * the caller makes the workflow's FIRST lookup hit THIS file, which declares
 * exactly one project. The root config is untouched and stays the entry point
 * for local runs, `npm run test:e2e:docs` and `--project visual`.
 *
 * ⚠️ `testIgnore` HAS TO BE REPEATED AT PROJECT LEVEL.
 * A project-level `testIgnore` REPLACES the top-level one, it does not merge
 * with it. The two only appear to combine because Playwright applies the
 * top-level filter to the file list before the project filter — so a future
 * reader who deletes the top-level list would silently start collecting the
 * visual specs. Both lists are spelled out in full below so neither one is
 * load-bearing on its own.
 *
 * WHAT RUNS HERE (10 spec files)
 *   tests/e2e/spec-coverage/*.spec.ts          (8)
 *   tests/e2e/workflows/*.workflow.spec.ts     (2)
 *
 * WHAT IS EXCLUDED, AND WHY
 *   tests/e2e/visual/**                — see above; cannot byte-match on CI.
 *   tests/e2e/docs-screenshots.spec.ts — documentation capture, not a gate.
 *   global-setup.ts, _base-url.ts, _nav.ts, workflows/fixtures.ts,
 *   visual/_visual-helpers.ts          — helper MODULES. They export functions,
 *                                        not tests. Playwright's default
 *                                        `testMatch` already skips them, but
 *                                        they are named explicitly so a widened
 *                                        `testMatch` cannot start collecting
 *                                        them and erroring with "no tests
 *                                        found in file".
 *
 * ARTIFACT PATHS
 * --------------
 * The shared workflow's upload steps accept BOTH `server/apps/<app>/…` and
 * `server/apps/<app>/tests/e2e/…`, so the scaffolded `tests/e2e/` locations
 * (already covered by tests/e2e/.gitignore) still produce a downloadable
 * report and trace artifact.
 */

import { defineConfig, devices } from '@playwright/test'
import * as path from 'path'

import { resolveBaseURL } from './_base-url'

/**
 * Helper modules and opt-in projects that must never be collected as CI specs.
 * Listed once, applied at BOTH levels — see the header.
 */
const IGNORED = [
	'**/global-setup.ts',
	'**/_base-url.ts',
	'**/_nav.ts',
	'**/fixtures.ts',
	'**/fixtures/**',
	'**/_visual-helpers.ts',
	'**/visual/**',
	'**/docs-screenshots.spec.ts',
]

export default defineConfig({
	testDir: __dirname,
	testIgnore: IGNORED,
	globalSetup: path.resolve(__dirname, 'global-setup.ts'),
	timeout: 60_000,
	expect: { timeout: 15_000 },
	// The workflow suites share ONE Nextcloud and seed fixtures into it by
	// name; running files in parallel would interleave those writes.
	fullyParallel: false,
	workers: 1,
	// No retries, on CI or off it.
	//
	// A retry can only ever turn a red result green, never the other way round,
	// so `retries: 1` silently converts an intermittent product defect into a
	// reported PASS — the same class of dishonest green this whole config exists
	// to remove. It also doubles the most expensive case: a spec that fails by
	// exhausting the 60 s test timeout costs two minutes instead of one.
	//
	// The budget is there for it: the suite runs in ~7.5 min against a 20 min
	// cap, so a genuine flake shows up as a red job that gets investigated
	// rather than as a green one that does not.
	retries: 0,
	reporter: [
		['html', { open: 'never', outputFolder: path.resolve(__dirname, 'playwright-report') }],
		['list'],
	],
	outputDir: path.resolve(__dirname, 'test-results'),

	use: {
		// Single source of truth — see tests/e2e/_base-url.ts.
		baseURL: resolveBaseURL(),
		// Written by global-setup.ts after the admin login.
		storageState: path.resolve(__dirname, '.auth', 'admin.json'),
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'chromium',
			// Repeated deliberately: a project-level testIgnore REPLACES the
			// top-level one rather than extending it.
			testIgnore: IGNORED,
			use: { ...devices['Desktop Chrome'] },
		},
	],
})
