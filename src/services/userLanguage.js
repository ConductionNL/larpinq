/**
 * The interface language of the signed-in user (platform-baseline-on-nextcloud,
 * REQ-PLB-007): the languages the phone pages offer, the one the account uses,
 * and saving a choice as the user's Nextcloud language so the rest of
 * Nextcloud follows. It also gives the base language OpenRegister reads and
 * writes for this user (larpinq-adopt-or-abstractions, Phase 3).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { getLanguage } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * The languages the switch offers, each named in its own language.
 *
 * @spec openspec/changes/platform-baseline-on-nextcloud
 */
export const LANGUAGES = Object.freeze([
	Object.freeze({ id: 'nl', label: 'Nederlands' }),
	Object.freeze({ id: 'en', label: 'English' }),
])

/** The language shown when the account uses one the switch does not offer. */
export const FALLBACK_LANGUAGE = 'en'

/**
 * The offered language that matches an account language: `nl_BE` and `nl`
 * both give `nl`; a language the switch does not offer gives English.
 *
 * @param {string} accountLanguage The account language, as Nextcloud reports it.
 * @return {string} The id of an offered language.
 *
 * @spec openspec/changes/platform-baseline-on-nextcloud
 */
export function offeredLanguage(accountLanguage) {
	const base = String(accountLanguage || '')
		.toLowerCase()
		.split(/[-_]/)[0]
	const match = LANGUAGES.find((language) => language.id === base)
	return match ? match.id : FALLBACK_LANGUAGE
}

/**
 * Save a language as the signed-in user's Nextcloud language through the
 * provisioning API (`PUT cloud/users/{userId}`, key `language`).
 *
 * @param {string} language The language id.
 * @param {object|null} user The signed-in user.
 * @return {Promise<boolean>} Whether Nextcloud stored it.
 *
 * @spec openspec/changes/platform-baseline-on-nextcloud
 */
export async function saveLanguage(language, user = getCurrentUser()) {
	if (!user || !user.uid) {
		return false
	}
	try {
		const response = await fetch(
			generateOcsUrl('cloud/users/{userId}', { userId: user.uid }),
			{
				method: 'PUT',
				headers: {
					requesttoken: getRequestToken(),
					'OCS-APIREQUEST': 'true',
					'Content-Type': 'application/json',
					Accept: 'application/json',
				},
				body: JSON.stringify({ key: 'language', value: language }),
			},
		)
		return response.ok
	} catch {
		return false
	}
}

/**
 * Switch to a language: nothing happens when it is already the current one;
 * otherwise it is saved and, once stored, the page reloads in it.
 *
 * @param {string} language The chosen language id.
 * @param {string} current The language in use.
 * @param {{save?: function(string): Promise<boolean>, reload?: function(): void}} deps The save and reload steps.
 * @return {Promise<'unchanged'|'saved'|'failed'>} What happened.
 *
 * @spec openspec/changes/platform-baseline-on-nextcloud
 */
export async function switchLanguage(
	language,
	current,
	{ save = saveLanguage, reload = () => window.location.reload() } = {},
) {
	if (!LANGUAGES.some((offered) => offered.id === language)) {
		return 'failed'
	}
	if (language === current) {
		return 'unchanged'
	}
	if (!(await save(language))) {
		return 'failed'
	}
	reload()
	return 'saved'
}

/**
 * The base of a language code. OpenRegister negotiates on the base
 * language, so the region is stripped: Nextcloud's `en_GB` or `de-DE` becomes
 * `en` / `de`. An unset or unreadable language gives null, which makes the
 * object store leave the `?_lang=` parameter and the
 * `X-Translation-Target-Language` header off.
 *
 * @param {string|null|undefined} language A Nextcloud language code.
 * @return {string|null} `en` for `en_GB`, null for an empty value.
 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md#requirement-larpinq-or-fetches-must-pass-lang-user-locale
 */
export function baseLanguage(language) {
	if (typeof language !== 'string') {
		return null
	}
	const base = language.trim().split(/[-_]/)[0].toLowerCase()
	return /^[a-z]{2,3}$/.test(base) ? base : null
}

/**
 * The current user's base language.
 *
 * @return {string|null} The language, or null when Nextcloud does not say.
 * @spec openspec/changes/larpinq-adopt-or-abstractions/specs/larpinq-adopt-or-abstractions/spec.md#requirement-larpinq-or-fetches-must-pass-lang-user-locale
 */
export function userLanguage() {
	try {
		return baseLanguage(getLanguage())
	} catch {
		return null
	}
}
