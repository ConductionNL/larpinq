/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/services/campaignActions.js: the Worlds page header
 * actions "Export campaign" and "Import campaign". Each case records the
 * dialogs shown, the import call and the navigation, in order.
 *
 * @spec openspec/specs/data-portability/spec.md
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const calls = []
const world = { config: null, fetched: null, file: null, imported: null }

vi.mock('@nextcloud/dialogs', () => ({
	showError: (message) => calls.push(['showError', message]),
	showSuccess: (message) => calls.push(['showSuccess', message]),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (match, key) => String(vars[key] ?? match)),
}))

vi.mock('../../src/store/modules/settings.js', () => ({
	useSettingsStore: () => ({
		getConfig: world.config,
		fetchSettings: async () => {
			calls.push(['fetchSettings'])
			return world.fetched
		},
	}),
}))

vi.mock('../../src/services/campaignPortability.js', () => ({
	resolveRegisterId: (config) => config?.register ?? null,
	campaignExportUrl: (id) => `/export/${id}`,
	importCampaign: async (file, id) => {
		calls.push(['importCampaign', file.name, id])
		if (world.imported instanceof Error) {
			throw world.imported
		}
		return world.imported
	},
}))

const { exportCampaignAction, importCampaignAction } = await import(
	'../../src/services/campaignActions.js'
)

beforeEach(() => {
	calls.length = 0
	Object.assign(world, { config: null, fetched: null, file: null, imported: null })
	globalThis.window = {
		location: { assign: (url) => calls.push(['assign', url]) },
	}
	globalThis.document = {
		createElement: () => {
			const input = {
				listeners: {},
				addEventListener: (name, handler) => {
					input.listeners[name] = handler
				},
				click: () => {
					calls.push(['pick', input.accept])
					input.files = world.file === null ? [] : [world.file]
					input.listeners.change()
				},
			}
			return input
		},
	}
})

const totals = { created: 2, updated: 1, unchanged: 3, failed: 0 }
const ACCEPT = '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
const NO_EXPORT = 'The campaign could not be exported, because no register is configured.'
const NO_IMPORT = 'The campaign could not be imported, because no register is configured.'

describe('campaign header actions', () => {
	it.each([
		[
			'export with loaded settings',
			{ config: { register: 'r1' } },
			'export',
			[['assign', '/export/r1']],
		],
		[
			'export fetches settings',
			{ fetched: { register: 'r2' } },
			'export',
			[['fetchSettings'], ['assign', '/export/r2']],
		],
		[
			'export without a register',
			{ fetched: {} },
			'export',
			[['fetchSettings'], ['showError', NO_EXPORT]],
		],
		['import without a register', { config: {} }, 'import', [['showError', NO_IMPORT]]],
		['import, nothing picked', { config: { register: 'r1' } }, 'import', [['pick', ACCEPT]]],
		[
			'import succeeds',
			{ config: { register: 'r1' }, file: { name: 'c.xlsx' }, imported: totals },
			'import',
			[
				['pick', ACCEPT],
				['importCampaign', 'c.xlsx', 'r1'],
				['showSuccess', 'Campaign imported: 2 created, 1 updated, 3 unchanged, 0 failed'],
			],
		],
		[
			'import fails',
			{ fetched: { register: 'r3' }, file: { name: 'c.xlsx' }, imported: new Error('bad sheet') },
			'import',
			[
				['fetchSettings'],
				['pick', ACCEPT],
				['importCampaign', 'c.xlsx', 'r3'],
				['showError', 'The campaign could not be imported: bad sheet'],
			],
		],
	])('%s', async (name, setup, action, expected) => {
		Object.assign(world, setup)
		await (action === 'export' ? exportCampaignAction() : importCampaignAction())
		expect(calls).toEqual(expected)
	})
})
