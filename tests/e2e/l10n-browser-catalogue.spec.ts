/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The browser-catalogue contract.
 *
 * THE DEFECT THIS PINS
 * --------------------
 * Nextcloud loads a locale catalogue in two formats and neither substitutes
 * for the other:
 *
 *   l10n/<locale>.json — read server-side by PHP `$l->t()`
 *   l10n/<locale>.js   — `OC.L10N.register(<appId>, …)`, the ONLY thing a
 *                        browser ever receives
 *
 * Raw JSON is not served out of an app directory: `l10n/nl.json` is a 404.
 * With no `.js` half, `t('<app>', key)` has nothing registered and hands the
 * key back, so the whole interface renders English no matter which language
 * the user picked — while every server-rendered string is translated. Nothing
 * errors and nothing logs.
 *
 * Measured across the fleet 2026-08-22: nine apps were in exactly this state,
 * holding 8,137 Dutch translations no user had ever seen. Six of them ran an
 * l10n check that passed throughout, because it reads the JSON — the half that
 * was never broken. A check that validates the source cannot see that the
 * artefact the runtime loads does not exist, which is why this test asserts
 * from the BROWSER's side instead.
 *
 * WHY IT IS WRITTEN THIS WAY
 * --------------------------
 * The app id is read from appinfo/info.xml at run time rather than hardcoded,
 * so this file is byte-identical in every app and survives a rename. A
 * catalogue registered under a pre-rename id is silently ignored by `t()` —
 * the same class of failure one level down — and scenario 2 would catch it.
 *
 * No fixture strings: the assertions pick a key out of the app's own
 * registered catalogue at run time. A spec that hardcoded "this Dutch string
 * must appear" would need editing every time the copy changed, and would say
 * nothing about apps whose translations differ.
 */

import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import path from 'node:path'

/**
 * The app id this repo declares. Resolved from the repo root by walking up
 * from this file, so it does not depend on the working directory playwright
 * happens to run in.
 */
const APP_ID = (() => {
	const candidates = [
		path.resolve(__dirname, '../../appinfo/info.xml'),
		path.resolve(process.cwd(), 'appinfo/info.xml'),
	]
	for (const file of candidates) {
		try {
			const match = readFileSync(file, 'utf8').match(/<id>([^<]+)<\/id>/)
			if (match) return match[1].trim()
		} catch {
			// try the next candidate
		}
	}
	throw new Error('could not read <id> from appinfo/info.xml')
})()

/** Locales this app actually ships, so the test never demands one it has not got. */
const LOCALES = ['nl', 'en']

test.describe(`l10n browser catalogue (${APP_ID})`, () => {
	// The literal 404 that made every translation unreachable.
	test('the generated catalogue is served to the browser', async ({ page }) => {
		await page.goto(`/index.php/apps/${APP_ID}/`, {
			waitUntil: 'domcontentloaded',
		})
		await page.waitForFunction(
			() => typeof (window as unknown as { OC?: unknown }).OC !== 'undefined',
			null,
			{ timeout: 20_000 },
		)

		// Ask the running instance where this app is served from rather than
		// assuming. A dev box mounts apps under /custom_apps and CI checks them
		// out under /apps, so hardcoding either makes this pass or fail for a
		// reason that has nothing to do with the catalogue.
		const webroot = await page.evaluate((appId) => {
			const w = window as unknown as {
				OC?: { appswebroots?: Record<string, string> }
			}
			return w.OC?.appswebroots?.[appId] || null
		}, APP_ID)
		expect(
			webroot,
			`OC.appswebroots must know where "${APP_ID}" is served`,
		).toBeTruthy()

		for (const locale of LOCALES) {
			const res = await page.request.get(`${webroot}/l10n/${locale}.js`)
			expect(res.status(), `l10n/${locale}.js must be served, not 404`).toBe(
				200,
			)

			const body = await res.text()
			expect(body, 'it must be an OC.L10N.register call').toContain(
				'OC.L10N.register',
			)
			// Registered under the CURRENT app id. After a rename the old id is
			// still a perfectly valid-looking file that `t()` never consults.
			expect(body, `it must register under "${APP_ID}"`).toContain(
				`"${APP_ID}"`,
			)
		}
	})

	// Served is not the same as reaching the running app: the bundle has to
	// register it, which is what `t()` actually reads.
	test('the running app has the catalogue registered, and t() resolves through it', async ({
		page,
	}) => {
		await page.goto(`/index.php/apps/${APP_ID}/`, {
			waitUntil: 'domcontentloaded',
		})
		await page.waitForFunction(
			() => typeof (window as unknown as { OC?: unknown }).OC !== 'undefined',
			null,
			{ timeout: 20_000 },
		)

		const probe = await page.evaluate((appId) => {
			const w = window as unknown as {
				_oc_l10n_registry_translations?: Record<
					string,
					Record<string, string>
				>
				t?: (app: string, key: string) => string
				OC?: { L10N?: { translate?: (app: string, key: string) => string } }
			}
			const registry = w._oc_l10n_registry_translations || {}
			const bundle = registry[appId]
			if (!bundle) return { registered: false as const, keys: 0 }

			// A key whose translation differs from itself — the only kind that can
			// tell a working lookup apart from the identity fallback.
			const translated =
				Object.keys(bundle).find((k) => bundle[k] !== k) || null
			const translate = w.OC?.L10N?.translate || w.t
			return {
				registered: true as const,
				keys: Object.keys(bundle).length,
				sampleKey: translated,
				expected: translated ? bundle[translated] : null,
				actual:
					translated && translate ? translate(appId, translated) : null,
			}
		}, APP_ID)

		expect(
			probe.registered,
			`"${APP_ID}" must have a catalogue registered in the browser`,
		).toBe(true)
		expect(
			probe.keys,
			'the registered catalogue must not be empty',
		).toBeGreaterThan(0)

		// Skip only when this app genuinely has no translated string yet — an
		// empty-by-nature catalogue is not a failure, but an unresolved lookup is.
		if (probe.sampleKey !== null) {
			expect(
				probe.actual,
				`t('${APP_ID}', '${probe.sampleKey}') must resolve through the catalogue`,
			).toBe(probe.expected)
			expect(
				probe.actual,
				'and must not fall back to returning the key',
			).not.toBe(probe.sampleKey)
		}
	})
})
