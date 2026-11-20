/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A game master's requirement override from the character page (REQ-SRE-001):
 * the skills of the character's world it does not have yet, and the one write
 * that adds a skill together with a `requirementOverrides[]` entry carrying the
 * reason. The requirement listener refuses an override from anyone but a game
 * master and one without a reason, and OpenRegister's audit trail records who
 * wrote it, so this module sends nothing else.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */

import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'
import { refusalOf } from './responseBody.js'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/**
 * The ids in a relation list, whether stored as ids or as objects.
 *
 * @param {unknown} value The stored list.
 * @return {Array<string>} The ids.
 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */
export function idsOf(value) {
	return (Array.isArray(value) ? value : [])
		.map((entry) => String(entry && typeof entry === 'object' ? (entry.id ?? '') : (entry ?? '')))
		.filter((id) => id !== '')
}

/**
 * The character and the skills of its world it does not have, by name.
 *
 * @param {string} characterId The character.
 * @return {Promise<{character: object, skills: Array<{id: string, name: string}>}|null>} Null when the character cannot be read.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */
export async function fetchOverrideOptions(characterId) {
	if (!characterId) {
		return null
	}
	const characterResponse = await fetch(
		generateUrl(`${OBJECTS}/character/${encodeURIComponent(characterId)}`),
		{ headers: jsonHeaders() },
	)
	if (!characterResponse.ok) {
		return null
	}
	const character = await characterResponse.json()
	const query = new URLSearchParams({ _limit: '500' })
	if (character?.setting) {
		query.set('setting', String(character.setting))
	}
	const skillsResponse = await fetch(generateUrl(`${OBJECTS}/larping_skill?${query.toString()}`), {
		headers: jsonHeaders(),
	})
	const body = skillsResponse.ok ? await skillsResponse.json() : {}
	const held = new Set(idsOf(character?.skills))
	const skills = (Array.isArray(body?.results) ? body.results : [])
		.map((skill) => ({ id: String(skill?.id ?? skill?.['@self']?.id ?? ''), name: String(skill?.name ?? '') }))
		.filter((skill) => skill.id !== '' && !held.has(skill.id))
		.sort((a, b) => a.name.localeCompare(b.name))
	return { character, skills }
}

/**
 * The write that grants a skill under an override: the character's skills
 * with the new one, and its overrides with an entry for it.
 *
 * @param {object} character The character as read.
 * @param {string} skillId The skill to grant.
 * @param {string} reason Why the requirements are waived.
 * @return {{skills: Array<string>, requirementOverrides: Array<object>}} The PATCH body.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */
export function overrideBody(character, skillId, reason) {
	const skills = idsOf(character?.skills)
	const overrides = Array.isArray(character?.requirementOverrides) ? character.requirementOverrides : []
	return {
		skills: skills.includes(skillId) ? skills : [...skills, skillId],
		requirementOverrides: [...overrides, { skill: skillId, reason: reason.trim() }],
	}
}

/**
 * Grant a skill to a character under a requirement override, in one write.
 *
 * @param {object} character The character as read.
 * @param {string} skillId The skill to grant.
 * @param {string} reason Why the requirements are waived.
 * @return {Promise<{ok: boolean, message: string}>} The outcome; `message` is the server's refusal.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */
export async function grantWithOverride(character, skillId, reason) {
	const id = String(character?.id ?? character?.['@self']?.id ?? '')
	const response = await fetch(generateUrl(`${OBJECTS}/character/${encodeURIComponent(id)}`), {
		method: 'PATCH',
		headers: jsonHeaders(),
		body: JSON.stringify(overrideBody(character, skillId, reason)),
	})
	if (response.ok) {
		return { ok: true, message: '' }
	}
	const { message } = await refusalOf(response)
	return { ok: false, message }
}
