/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The language switch on the phone pages (platform-baseline-on-nextcloud,
 * REQ-PLB-007): it starts on the account language, saves a choice as the
 * user's Nextcloud language through the provisioning API, and reloads once
 * Nextcloud stored it. Only fetch, the account language and the reload are
 * faked; the real userLanguage service runs.
 *
 * @spec openspec/changes/platform-baseline-on-nextcloud
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import LanguageSwitch from '../../src/components/LanguageSwitch.vue'
import {
	LANGUAGES,
	offeredLanguage,
	saveLanguage,
	switchLanguage,
} from '../../src/services/userLanguage.js'

const { account } = vi.hoisted(() => ({ account: { language: 'nl' } }))

vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => account.language,
	translate: (app, key) => key,
}))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8')

/**
 * Fake fetch that answers every call with one status and records the calls.
 *
 * @param {boolean} ok Whether the answer is a success.
 * @return {Array<{url: string, init: object}>} The recorded calls.
 */
function fakeFetch(ok) {
	const calls = []
	globalThis.fetch = async (url, init) => {
		calls.push({ url, init })
		return { ok }
	}
	return calls
}

let reload

beforeEach(() => {
	account.language = 'nl'
	reload = vi.fn()
	globalThis.window = { location: { reload } }
})

afterEach(() => {
	delete globalThis.fetch
	delete globalThis.window
})

describe('offeredLanguage', () => {
	it('maps the account language and its regional forms onto an offered language', () => {
		expect(offeredLanguage('nl')).toBe('nl')
		expect(offeredLanguage('nl_BE')).toBe('nl')
		expect(offeredLanguage('en-GB')).toBe('en')
		expect(offeredLanguage('EN')).toBe('en')
	})

	it('falls back to English for a language the switch does not offer', () => {
		expect(offeredLanguage('de')).toBe('en')
		expect(offeredLanguage('')).toBe('en')
		expect(offeredLanguage(undefined)).toBe('en')
	})

	it('offers exactly Nederlands and English', () => {
		expect(LANGUAGES.map((language) => language.id)).toEqual(['nl', 'en'])
		expect(LANGUAGES.map((language) => language.label)).toEqual([
			'Nederlands',
			'English',
		])
	})
})

describe('saveLanguage', () => {
	it("stores the language as the user's Nextcloud language", async () => {
		const calls = fakeFetch(true)

		expect(await saveLanguage('en', { uid: 'sem' })).toBe(true)

		expect(calls).toHaveLength(1)
		expect(calls[0].url).toBe('/ocs/v2.php/cloud/users/sem')
		expect(calls[0].init.method).toBe('PUT')
		expect(calls[0].init.headers.requesttoken).toBe('test-token')
		expect(calls[0].init.headers['OCS-APIREQUEST']).toBe('true')
		expect(JSON.parse(calls[0].init.body)).toEqual({
			key: 'language',
			value: 'en',
		})
	})

	it('encodes the user id in the path', async () => {
		const calls = fakeFetch(true)

		await saveLanguage('nl', { uid: 'anna de vries' })

		expect(calls[0].url).toBe('/ocs/v2.php/cloud/users/anna%20de%20vries')
	})

	it('reports a refused save', async () => {
		fakeFetch(false)

		expect(await saveLanguage('en', { uid: 'sem' })).toBe(false)
	})

	it('reports a network failure without throwing', async () => {
		globalThis.fetch = async () => {
			throw new Error('offline')
		}

		expect(await saveLanguage('en', { uid: 'sem' })).toBe(false)
	})

	it('saves nothing without a signed-in user', async () => {
		const calls = fakeFetch(true)

		expect(await saveLanguage('en', null)).toBe(false)
		expect(calls).toHaveLength(0)
	})
})

describe('switchLanguage', () => {
	it('saves a new language and reloads the page', async () => {
		const save = vi.fn(async () => true)

		expect(await switchLanguage('en', 'nl', { save, reload })).toBe('saved')

		expect(save).toHaveBeenCalledWith('en')
		expect(reload).toHaveBeenCalledTimes(1)
	})

	it('does nothing when the language is already in use', async () => {
		const save = vi.fn(async () => true)

		expect(await switchLanguage('nl', 'nl', { save, reload })).toBe('unchanged')

		expect(save).not.toHaveBeenCalled()
		expect(reload).not.toHaveBeenCalled()
	})

	it('does not reload when Nextcloud refused the save', async () => {
		const save = vi.fn(async () => false)

		expect(await switchLanguage('en', 'nl', { save, reload })).toBe('failed')

		expect(reload).not.toHaveBeenCalled()
	})

	it('refuses a language the switch does not offer', async () => {
		const save = vi.fn(async () => true)

		expect(await switchLanguage('de', 'nl', { save, reload })).toBe('failed')

		expect(save).not.toHaveBeenCalled()
	})
})

describe('LanguageSwitch', () => {
	it('starts on the account language', () => {
		const self = { ...LanguageSwitch.data() }

		expect(self.selected).toBe('nl')
		expect(LanguageSwitch.computed.selectedOption.call(self)).toEqual({
			id: 'nl',
			label: 'Nederlands',
		})
	})

	it('starts on English for an account in a language it does not offer', () => {
		account.language = 'fr'

		expect(LanguageSwitch.data().selected).toBe('en')
	})

	it('saves the choice and reloads', async () => {
		const calls = fakeFetch(true)
		const self = { ...LanguageSwitch.data() }

		await LanguageSwitch.methods.onSelect.call(self, {
			id: 'en',
			label: 'English',
		})

		expect(JSON.parse(calls[0].init.body)).toEqual({
			key: 'language',
			value: 'en',
		})
		expect(reload).toHaveBeenCalledTimes(1)
		expect(self.failed).toBe(false)
		expect(self.saving).toBe(false)
	})

	it('goes back to the language in use and says so when the save fails', async () => {
		fakeFetch(false)
		const self = { ...LanguageSwitch.data() }

		await LanguageSwitch.methods.onSelect.call(self, {
			id: 'en',
			label: 'English',
		})

		expect(reload).not.toHaveBeenCalled()
		expect(self.failed).toBe(true)
		expect(self.selected).toBe('nl')
	})

	it('ignores a cleared selection', async () => {
		const calls = fakeFetch(true)
		const self = { ...LanguageSwitch.data() }

		await LanguageSwitch.methods.onSelect.call(self, null)

		expect(calls).toHaveLength(0)
		expect(self.selected).toBe('nl')
	})
})

describe('placement on the phone pages', () => {
	it("is the actions of the player's own character list", () => {
		const fragment = JSON.parse(
			read('src/manifest.d/characters-my-character-sheet.json'),
		)
		const page = fragment.pages.find((entry) => entry.id === 'MyCharacters')

		expect(page.actionsComponent).toBe('LanguageSwitch')
	})

	it('is registered as an actions component', () => {
		expect(read('src/registry.js')).toMatch(
			/LanguageSwitch: \{\s*kind: 'actions',\s*component: LanguageSwitch,/,
		)
	})
})
