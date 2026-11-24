/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The invoice a player asked for on their registration is issued by shillinq
 * (registration-payments-through-shillinq REQ-RPS-006); larpinq stores where
 * it is downloaded in `invoiceUrl`. A link cell on My registrations shows that
 * link worded as "Download invoice" rather than as the URL itself.
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
 */
import { translate as t } from '@nextcloud/l10n'

/**
 * The text of an invoice link cell: "Download invoice" when the registration
 * has an invoice link, else nothing.
 *
 * @param {unknown} value The stored `invoiceUrl`.
 * @return {string} The cell text.
 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
 */
export function invoiceDownloadLabel(value) {
	if (typeof value !== 'string' || value.trim() === '') {
		return ''
	}
	return t('larpinq', 'Download invoice')
}
