import { defineConfig, devices } from '@playwright/test'
import { resolveBaseURL } from './tests/e2e/_base-url'

/**
 * Playwright config for Larping.
 *
 * Scaffolded by /journeydoc-init (ADR-030) and refreshed with the
 * shared globalSetup + storageState scaffold from hydra#272. The
 * regression `chromium` project is a minimal starting point; the
 * `docs-capture` project drives the journeydoc screenshot suite
 * (`tests/e2e/docs-screenshots.spec.ts`). Tune the reporters when
 * wiring real regression tests.
 */
export default defineConfig({
	testDir: './tests/e2e',
	testIgnore: ['**/global-setup.ts', '**/fixtures/**'],
	timeout: 60_000,
	expect: { timeout: 15_000 },
	fullyParallel: false,
	retries: 1,
	workers: 1,
	// The shared quality.yml Playwright job is `timeout-minutes: 45`, and a job
	// cancelled by that cap produces NO verdict: Playwright never prints its
	// tally, the `if: failure()` trace upload never fires, and the
	// `if: always()` report upload does not run on a cancelled job either — the
	// run you most need to read is the one that leaves nothing behind, and it
	// still renders as "fail" in `gh pr checks` while carrying no information.
	// Runs cancelled at ~45m16s have been observed in this fleet. Measured
	// overhead before `Run Playwright tests` starts is 2.0-2.4 min and the
	// uploads after it take seconds, so 38m keeps ~7 min of margin while
	// guaranteeing both a tally and the artifacts that explain it.
	globalTimeout: 38 * 60_000,
	reporter: [
		['html', { open: 'never', outputFolder: 'tests/e2e/playwright-report' }],
		['junit', { outputFile: 'tests/e2e/test-results/results.xml' }],
	],
	outputDir: 'tests/e2e/test-results',

	// Runs once before the test run, drives the NC login, persists cookies
	// to `tests/e2e/.auth/admin.json`. See `tests/e2e/global-setup.ts`.
	globalSetup: require.resolve('./tests/e2e/global-setup'),

	use: {
		// Resolved centrally, and deliberately WITHOUT a `localhost:8080`
		// fallback — that is the shared dev container. See tests/e2e/_base-url.ts.
		baseURL: resolveBaseURL(),
		// `on-first-retry` writes a trace only for the SECOND attempt, so a
		// failure that does NOT reproduce on retry — precisely the one worth a
		// trace — leaves no record of the attempt that actually failed. It also
		// ties the trace artifact to `retries`, which several repos in this
		// fleet set to 0, giving them zero traces ever. `retain-on-failure`
		// traces every attempt and keeps the ones that failed: strictly more
		// informative, and independent of the retry count.
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},

	projects: [
		// Default regression project. Excludes the docs capture spec so
		// PR pipelines don't reshoot screenshots on every push.
		{
			name: 'chromium',
			testIgnore: ['**/docs-screenshots.spec.ts', '**/visual/**'],
			use: {
				...devices['Desktop Chrome'],
				// Pick up the authenticated storage state globalSetup wrote.
				storageState: 'tests/e2e/.auth/admin.json',
			},
		},
		// Documentation capture project (ADR-030 / journeydoc). Opt-in:
		//   npx playwright test --project docs-capture
		// Output lands in `docs/static/screenshots/tutorials/{user,admin}/`.
		{
			name: 'docs-capture',
			testMatch: /docs-screenshots\.spec\.ts$/,
			use: {
				...devices['Desktop Chrome'],
				viewport: { width: 1280, height: 800 },
				// Same authed session — capture spec navigates into the app,
				// which is admin-only on most ConductionNL deployments.
				storageState: 'tests/e2e/.auth/admin.json',
			},
			timeout: 90_000,
		},
		// Visual-regression project (GAP-5). Opt-in / non-gating:
		//   npx playwright test --project visual
		//   npx playwright test --project visual --update-snapshots  (rebaseline)
		// Fixed viewport + authenticated session => deterministic shots.
		// Baselines live in tests/e2e/visual/*-snapshots/ and ARE committed.
		// PLATFORM CAVEAT: PNG baselines are host-font/GPU specific, so a CI
		// Linux runner will not byte-match a dev-container baseline; the visual
		// project must regenerate its baselines in-CI before it can gate.
		{
			name: 'visual',
			testMatch: /visual\/.*\.visual\.spec\.ts$/,
			use: {
				...devices['Desktop Chrome'],
				viewport: { width: 1280, height: 800 },
				storageState: 'tests/e2e/.auth/admin.json',
			},
			timeout: 90_000,
		},
	],
})
