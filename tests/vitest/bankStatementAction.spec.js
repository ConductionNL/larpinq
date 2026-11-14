/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Registrations header action "Upload and match a bank statement"
 * (registration-payments-through-shillinq REQ-RPS-007) opens shillinq's bank
 * statement import, shows only to game masters and is registered by name.
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#requirement-a-game-master-uploads-a-bank-statement-to-match-transfers-req-rps-007
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => `/index.php${url}` }))

const { bankStatementImportUrl, openBankStatementImportAction, SHILLINQ_BANK_IMPORT_PATH } = await import('../../src/services/bankStatementAction.js')

const here = path.dirname(fileURLToPath(import.meta.url))

describe('the bank statement action', () => {
	it('points at shillinq\'s bank statement import page', () => {
		expect(SHILLINQ_BANK_IMPORT_PATH).toBe('/apps/shillinq/configuratie/bank-import')
		expect(bankStatementImportUrl()).toBe('/index.php/apps/shillinq/configuratie/bank-import')
	})

	it('opens that page', () => {
		const location = { assign: vi.fn() }

		openBankStatementImportAction(location)

		expect(location.assign).toHaveBeenCalledWith('/index.php/apps/shillinq/configuratie/bank-import')
	})

	it('is a header action of the Registrations page, for game masters only', () => {
		const fragment = JSON.parse(fs.readFileSync(path.join(here, '../../src/manifest.d/registration-intake-and-capacity.json'), 'utf8'))
		const page = fragment.pages.find((p) => p.id === 'Registrations')
		const action = page.config.headerActions.find((a) => a.id === 'bank-statement')

		expect(action.handler).toBe('larpinqOpenBankStatementImport')
		expect(action.visibleWhen).toEqual({ endpoint: '/apps/larpinq/api/payments/access', field: 'allowed', op: 'eq', value: true })
	})

	it('is registered under the name the manifest uses', () => {
		const registry = fs.readFileSync(path.join(here, '../../src/registry.js'), 'utf8')

		expect(registry).toMatch(/larpinqOpenBankStatementImport: \{\s*kind: 'handler',\s*handler: openBankStatementImportAction,/)
	})
})
