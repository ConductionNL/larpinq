/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Reading the JSON body of a fetch response: a plain body, or a refusal with
 * its message.
 */

/**
 * The parsed JSON body, or an empty object when it does not parse.
 *
 * @param {Response} response The fetch response.
 * @return {Promise<unknown>} The parsed body.
 *
 * @spec openspec/specs/character-builds/spec.md
 * @spec openspec/specs/event-registration/spec.md
 * @spec openspec/specs/event-xp-awards/spec.md
 * @spec openspec/specs/setting-management/spec.md
 */
async function jsonOr(response) {
	try {
		return await response.json()
	} catch {
		return {}
	}
}

/**
 * The JSON body of a response, or an empty object when there is none.
 *
 * @param {Response} response The fetch response.
 * @return {Promise<object>} The body.
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 * @spec openspec/specs/setting-management/spec.md
 */
export async function bodyOf(response) {
	const body = await jsonOr(response)
	return body && typeof body === 'object' ? body : {}
}

/**
 * The refusal in a failed response: its `errors` block (or the whole body
 * when there is none) and the message it carries.
 *
 * @param {Response} response The fetch response.
 * @return {Promise<{errors: unknown, message: string}>} The refusal.
 *
 * @spec openspec/specs/character-builds/spec.md
 * @spec openspec/specs/event-registration/spec.md
 */
export async function refusalOf(response) {
	const refusal = await jsonOr(response)
	const errors = errorsOf(refusal)
	return {
		errors,
		message: String(errors?.message ?? refusal?.message ?? ''),
	}
}

/**
 * The `errors` object of a refusal body, or the body itself.
 *
 * @param {unknown} refusal The parsed refusal body.
 * @return {unknown} The errors block.
 *
 * @spec openspec/specs/character-builds/spec.md
 * @spec openspec/specs/event-registration/spec.md
 */
function errorsOf(refusal) {
	return refusal?.errors && typeof refusal.errors === 'object'
		? refusal.errors
		: refusal
}
