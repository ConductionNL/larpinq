/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Registrations page header action "Upload and match a bank statement"
 * (registration-payments-through-shillinq REQ-RPS-007): shillinq imports the
 * statement and matches each transfer to a payment request by its reference;
 * larpinq marks the matched registrations paid by bank transfer when shillinq
 * settles their requests. This only opens shillinq's import page; the lines
 * that matched nothing are listed there.
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#requirement-a-game-master-uploads-a-bank-statement-to-match-transfers-req-rps-007
 */

import { generateUrl } from '@nextcloud/router'

/** Shillinq's bank statement import page (its BankStatementWizard). */
export const SHILLINQ_BANK_IMPORT_PATH = '/apps/shillinq/configuratie/bank-import'

/**
 * The address of shillinq's bank statement import page.
 *
 * @return {string}
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#requirement-a-game-master-uploads-a-bank-statement-to-match-transfers-req-rps-007
 */
export function bankStatementImportUrl() {
	return generateUrl(SHILLINQ_BANK_IMPORT_PATH)
}

/**
 * Open shillinq's bank statement import page.
 *
 * @param {object} [location] Where to go; the window's location by default.
 *
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md#requirement-a-game-master-uploads-a-bank-statement-to-match-transfers-req-rps-007
 */
export function openBankStatementImportAction(location = window.location) {
	location.assign(bankStatementImportUrl())
}
