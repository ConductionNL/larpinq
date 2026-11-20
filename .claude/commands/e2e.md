---
description: Run the larpinq Playwright end-to-end tests against a running Nextcloud
---

Run the Playwright suite (`playwright.config.ts`, specs in `tests/e2e/`).

1. It needs a running Nextcloud with larpinq and OpenRegister. Set `PLAYWRIGHT_BASE_URL` to an isolated instance, for example `PLAYWRIGHT_BASE_URL=http://localhost:8094`. There is deliberately no `localhost:8080` default: that is the shared dev container. If no instance is available, stop and say so.
2. Install the browser once: `npm run test:e2e:install`
3. Run the suite: `npm run test:e2e`, or one spec with `npx playwright test <path>`. List tests without running them: `npx playwright test --list`.

Reports go to `tests/e2e/playwright-report/` and `tests/e2e/test-results/`.

$ARGUMENTS
