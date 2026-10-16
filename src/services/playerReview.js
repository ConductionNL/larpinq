/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The New players bulk action "Mark reviewed" (players-self-signup). It
 * clears `awaitingReview` on each selected player through the object API;
 * the server stamps who reviewed and when (PlayerReviewListener), and
 * OpenRegister's write rule on the player lets only game masters do it.
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const OBJECTS = '/apps/openregister/api/objects/larpinq'

/**
 * Mark players reviewed, one write each.
 *
 * @param {Array<string>} ids The player ids.
 * @return {Promise<{reviewed: Array<string>, refused: Array<string>}>} What happened to each.
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */
export async function markPlayersReviewed(ids) {
	const reviewed = []
	const refused = []
	for (const id of ids || []) {
		let ok
		try {
			const response = await fetch(
				generateUrl(`${OBJECTS}/player/${encodeURIComponent(id)}`),
				{
					method: 'PATCH',
					headers: {
						requesttoken: getRequestToken(),
						Accept: 'application/json',
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({ awaitingReview: false }),
				},
			)
			ok = response.ok
		} catch {
			ok = false
		}
		;(ok ? reviewed : refused).push(id)
	}
	return { reviewed, refused }
}
