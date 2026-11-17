/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The invoice shillinq issued on request (registration-payments-through-shillinq
 * REQ-RPS-006): My registrations words the link "Download invoice", and the
 * registration page offers the download only once the invoice is there.
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => `${app}:${text}` }))

const { invoiceDownloadLabel } = await import('../../src/services/invoiceDownload.js')

const here = path.dirname(fileURLToPath(import.meta.url))
const fragment = JSON.parse(fs.readFileSync(path.join(here, '../../src/manifest.d/registration-intake-and-capacity.json'), 'utf8'))
const page = (id) => fragment.pages.find((p) => p.id === id)

describe('the invoice download', () => {
	it('words a registration with an invoice link as a download', () => {
		expect(invoiceDownloadLabel('https://cloud.example.org/apps/shillinq/api/v1/invoices/a1/pdf')).toBe('larpinq:Download invoice')
	})

	it('shows nothing without an invoice link', () => {
		expect(invoiceDownloadLabel('')).toBe('')
		expect(invoiceDownloadLabel('   ')).toBe('')
		expect(invoiceDownloadLabel(null)).toBe('')
		expect(invoiceDownloadLabel(undefined)).toBe('')
	})

	it('links the column on My registrations to the stored invoice link', () => {
		const column = page('MyRegistrations').config.columns.find((c) => c.key === 'invoiceUrl')
		expect(column.widget).toBe('link')
		expect(column.widgetProps.href).toBe('{invoiceUrl}')
		expect(column.formatter).toBe('invoiceDownload')
	})

	it('offers the download on the registration page only once the invoice is there', () => {
		const action = page('RegistrationDetail').config.headerActions.find((a) => a.id === 'download-invoice')
		expect(action.href).toBe('/apps/shillinq/api/v1/invoices/@object.invoiceId/pdf')
		expect(action.visibleWhen).toEqual({ field: 'invoiceId', op: 'notEmpty' })
	})
})
