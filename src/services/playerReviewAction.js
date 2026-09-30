/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The New players bulk action "Mark reviewed", registered as a
 * `kind: 'handler'` entry in src/registry.js and named from the manifest.
 * The writes live in playerReview.js; this file reports the result.
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { markPlayersReviewed } from './playerReview.js'

/**
 * The bulk action handler, registered in src/registry.js.
 *
 * @param {{selectedIds: Array<string>}} scope The bulk action scope from the index page.
 * @return {Promise<void>}
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */
export async function markPlayersReviewedAction(scope) {
	const selectedIds = Array.isArray(scope?.selectedIds) ? scope.selectedIds : []
	if (selectedIds.length === 0) {
		return
	}
	const { reviewed, refused } = await markPlayersReviewed(selectedIds)
	if (reviewed.length > 0) {
		showSuccess(
			t('larpinq', 'Players marked reviewed: {count}', {
				count: reviewed.length,
			}),
		)
	}
	if (refused.length > 0) {
		showError(
			t(
				'larpinq',
				'Players not marked reviewed: {count}. Only game masters can review players.',
				{ count: refused.length },
			),
		)
	}
	if (reviewed.length > 0) {
		window.location.reload()
	}
}
