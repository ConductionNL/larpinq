/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pins the Characters index "Edit selected" action: nothing opens without
 * a selection, the dialog gets the selected ids, and the page reloads only
 * when a character changed.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const spawnDialog = vi.fn()
vi.mock('@nextcloud/vue/functions/dialog', () => ({ spawnDialog: (...args) => spawnDialog(...args) }))
vi.mock('../../src/dialogs/CharacterBulkEditDialog.vue', () => ({ default: { name: 'CharacterBulkEditDialog' } }))

const { bulkEditCharactersAction } = await import('../../src/services/characterBulkEditAction.js')

let reload

beforeEach(() => {
	spawnDialog.mockReset()
	reload = vi.fn()
	globalThis.window = { location: { reload } }
})

afterEach(() => {
	delete globalThis.window
})

describe('bulkEditCharactersAction', () => {
	it('opens nothing without a selection', async () => {
		await bulkEditCharactersAction({ selectedIds: [] })
		await bulkEditCharactersAction({ selectedIds: 'c-1' })
		await bulkEditCharactersAction(undefined)
		expect(spawnDialog).not.toHaveBeenCalled()
		expect(reload).not.toHaveBeenCalled()
	})

	it('opens the bulk edit dialog with the selected ids', async () => {
		spawnDialog.mockResolvedValue(0)
		await bulkEditCharactersAction({ selectedIds: ['c-1', 'c-2'] })
		expect(spawnDialog).toHaveBeenCalledWith({ name: 'CharacterBulkEditDialog' }, { selectedIds: ['c-1', 'c-2'] })
	})

	it('reloads the list when a character changed', async () => {
		spawnDialog.mockResolvedValue(2)
		await bulkEditCharactersAction({ selectedIds: ['c-1'] })
		expect(reload).toHaveBeenCalledTimes(1)
	})

	it('does not reload when nothing changed or the dialog was cancelled', async () => {
		spawnDialog.mockResolvedValueOnce(0).mockResolvedValueOnce(undefined).mockResolvedValueOnce('0')
		await bulkEditCharactersAction({ selectedIds: ['c-1'] })
		await bulkEditCharactersAction({ selectedIds: ['c-1'] })
		await bulkEditCharactersAction({ selectedIds: ['c-1'] })
		expect(reload).not.toHaveBeenCalled()
	})
})
