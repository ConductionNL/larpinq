/**
 * ADR-111 — the demo-data setup step, exercised against a running instance.
 *
 * WHY THIS EXISTS. The programme that added demo data to this app shipped a
 * defect that every unit test passed: `occ openregister:descriptors:list
 * --import=<slug>` printed `register "larpinq" imported.` and seeded ZERO of
 * the descriptor's 30 objects. The unit tests could not see it — they mock the
 * import service, so they validate the CALL and never its effect.
 *
 * So the assertion that matters here is not "the endpoint answers 200". It is
 * that the response NAMES WHAT LANDED. A success message that cannot be told
 * apart from an import that wrote nothing is exactly what let that defect
 * through.
 *
 * WHY THE API AND NOT A CLICK-THROUGH. `CnAppRoot` opens the optional wizard
 * only while an optional step is outstanding, and the CI seed deliberately
 * settles those so the wizard stops covering the app in every test. The
 * observable surface for this capability is therefore the contract the wizard
 * calls — `GET /api/setup/status` and `POST /api/setup/action/{id}` — issued
 * from inside the authenticated admin page so every call carries the real
 * session and `OC.requestToken` through Nextcloud's `AuthorizedAdminSetting`
 * middleware. A unit test with a mocked IAppConfig cannot show that middleware
 * admitting the request; this can.
 *
 * WHAT THIS DELIBERATELY DOES NOT ASSERT. That `setup.steps[0]` is the
 * demo-data step (ADR-111 rule 4) is a property of the manifest, which the app
 * bundles rather than serves, so it is not observable from here. Gate 100
 * (`setup-demo-data-first`) checks it statically on every change. Claiming to
 * prove it here would be asserting something this vantage point cannot see.
 */
import { test, expect, Page } from '@playwright/test'

const BASE = '/apps/larpinq'

/** One authenticated JSON call issued from inside the logged-in admin page. */
async function api(
	page: Page,
	method: string,
	path: string,
): Promise<{ status: number; json: any }> {
	return await page.evaluate(
		async ({ method, path }) => {
			const res = await fetch(path, {
				method,
				headers: {
					'Content-Type': 'application/json',
					// eslint-disable-next-line no-undef
					requesttoken: (window as any).OC?.requestToken || '',
					'OCS-APIREQUEST': 'true',
				},
			})
			let json: any = null
			try {
				json = await res.json()
			} catch {
				json = null
			}
			return { status: res.status, json }
		},
		{ method, path },
	)
}

test.describe.configure({ mode: 'serial' })

test.describe('ADR-111 demo data', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' })
		// The wizard's own contract lives behind the admin middleware; the page
		// must be a real authenticated session before any call below.
		await page.waitForFunction(() => (window as any).OC?.requestToken, null, {
			timeout: 15000,
		})
	})

	test('setup status reports the demo-data step, so the wizard can offer it', async ({
		page,
	}) => {
		const res = await api(page, 'GET', `${BASE}/api/setup/status`)

		expect(res.status, 'setup/status must answer an authenticated admin').toBe(
			200,
		)

		// A step the endpoint never MENTIONS resolves to `done: false` forever —
		// no operator action can clear it, and CnAppRoot then covers the app with
		// the wizard in every fresh browser context. Absence is the defect here,
		// not "not done".
		expect(
			Object.keys(res.json?.steps ?? {}),
			'setup/status must report a demo-data step',
		).toContain('demo-data')
	})

	test('installing the demo data reports HOW MUCH landed, not just success', async ({
		page,
	}) => {
		// 🔴 A REAL IMPORT, NOT A STUB. shillinq seeds 1497 objects and the call
		// took 49.6s on one run and exceeded the 60s default on the next — the
		// operation is legitimately slow, and the assertion is worth its cost:
		// it is the only check that the install WROTE something.
		test.slow()

		const res = await api(
			page,
			'POST',
			`${BASE}/api/setup/action/install-demo-data`,
		)

		expect(res.status, 'the action must pass the admin middleware').toBe(200)
		expect(
			res.json?.success,
			`install failed: ${JSON.stringify(res.json)}`,
		).toBe(true)

		// 🔴 THE COUNTS ARE THE ASSERTION. "Demo data installed" with no numbers
		// is indistinguishable from an import that wrote nothing — the exact
		// defect this programme shipped in openregister and had to fix. A message
		// carrying a positive object count is the only evidence that the demo
		// data reached the instance.
		const message = String(res.json?.message ?? '')
		const numbers = (message.match(/\d+/g) ?? []).map(Number)

		expect(
			numbers.some((n) => n > 0),
			`the install message must name a non-zero object count; got: "${message}"`,
		).toBe(true)
	})

	test('re-installing is safe, because the step promises it is', async ({
		page,
	}) => {
		// The step body tells the operator it is "safe to run more than once".
		// That sentence is a contract; this asserts the server keeps it rather
		// than erroring or reporting failure on a second pass.
		const again = await api(
			page,
			'POST',
			`${BASE}/api/setup/action/install-demo-data`,
		)

		expect(again.status).toBe(200)
		expect(
			again.json?.success,
			`a second install must not fail: ${JSON.stringify(again.json)}`,
		).toBe(true)
	})
})
