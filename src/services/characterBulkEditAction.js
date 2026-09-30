/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Characters index bulk action "Edit selected", registered as a
 * `kind: 'handler'` entry in src/registry.js and named from the manifest.
 * It opens the bulk edit dialog with the selection, and reloads the list when
 * a character changed so the index shows the new values.
 *
 * @spec openspec/specs/character-management/spec.md
 */

import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import CharacterBulkEditDialog from '../dialogs/CharacterBulkEditDialog.vue'

/**
 * Open the bulk edit dialog for the selected characters.
 *
 * @param {{selectedIds: Array<string>}} scope The bulk action scope from the index page.
 * @return {Promise<void>}
 *
 * @spec openspec/specs/character-management/spec.md
 */
export async function bulkEditCharactersAction(scope) {
	const selectedIds = Array.isArray(scope?.selectedIds) ? scope.selectedIds : []
	if (selectedIds.length === 0) {
		return
	}
	const changed = await spawnDialog(CharacterBulkEditDialog, { selectedIds })
	if (Number(changed) > 0) {
		window.location.reload()
	}
}
