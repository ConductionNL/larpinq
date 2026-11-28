/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Rejecting a submitted character with a reason (REQ-CTA-001). The reject
 * move is a transition of the character's approval lifecycle: OpenRegister
 * admits game masters only, requires the reason and writes it in the same
 * save, and the transition tells the owner. This module posts it and passes
 * the refusal on.
 *
 * @spec openspec/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
 */

import { generateUrl } from '@nextcloud/router'
import { jsonHeaders } from './requestHeaders.js'
import { bodyOf } from './responseBody.js'

/** The approval state a game master can reject from: submitted, not yet approved. */
export const WAITING = 'no'

/**
 * Reject a character with a reason.
 *
 * @param {string} characterId The character.
 * @param {string} reason Why it is rejected.
 * @return {Promise<{ok: boolean, message: string}>} The outcome; `message` is the server's refusal.
 *
 * @spec openspec/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
 */
export async function rejectCharacter(characterId, reason) {
	const response = await fetch(
		generateUrl(`/apps/openregister/api/objects/${encodeURIComponent(characterId)}/transition`),
		{
			method: 'POST',
			headers: jsonHeaders(),
			body: JSON.stringify({ action: 'reject', data: { rejectionReason: reason.trim() } }),
		},
	)
	if (response.ok) {
		return { ok: true, message: '' }
	}
	const body = await bodyOf(response)
	return { ok: false, message: String(body.error ?? body.message ?? '') }
}
