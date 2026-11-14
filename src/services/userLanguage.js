/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * The language OpenRegister reads and writes for this user
 * (larpinq-adopt-or-abstractions, Phase 3).
 *
 * OpenRegister negotiates on the base language, so the region is stripped:
 * Nextcloud's `en_GB` or `de-DE` becomes `en` / `de`. An unset or
 * unreadable language gives null, which makes the object store leave the
 * `?_lang=` parameter and the `X-Translation-Target-Language` header off.
 */
import { getLanguage } from '@nextcloud/l10n'

/**
 * The base of a language code.
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
