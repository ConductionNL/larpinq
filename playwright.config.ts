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
		trace: 'on-first-retry',
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
