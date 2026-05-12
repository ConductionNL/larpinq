import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright config for Larping.
 *
 * Scaffolded by /journeydoc-init (ADR-030). Larping had no Playwright
 * setup before this — the regression `chromium` project is a minimal
 * starting point; the `docs-capture` project drives the journeydoc
 * screenshot suite (`tests/e2e/docs-screenshots.spec.ts`). Tune the
 * base URL, auth/storage state, and reporters when wiring real
 * regression tests.
 */
export default defineConfig({
	testDir: './tests/e2e',
	timeout: 30_000,
	expect: { timeout: 10_000 },
	fullyParallel: false,
	retries: 1,
	workers: 1,
	reporter: [
		['html', { open: 'never', outputFolder: 'tests/e2e/playwright-report' }],
		['junit', { outputFile: 'tests/e2e/test-results/results.xml' }],
	],
	outputDir: 'tests/e2e/test-results',

	use: {
		baseURL: process.env.NEXTCLOUD_URL || 'http://localhost:8080',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		// Default regression project. Excludes the docs capture spec so
		// PR pipelines don't reshoot screenshots on every push.
		{
			name: 'chromium',
			testIgnore: ['**/docs-screenshots.spec.ts'],
			use: { ...devices['Desktop Chrome'] },
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
			},
			timeout: 90_000,
		},
	],
})
