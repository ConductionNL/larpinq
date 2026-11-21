---
kind: code
depends_on: [registration-intake-and-capacity, registration-ticket-types-and-options]
---

# Proposal: registration-payments-through-shillinq

## Summary

Once a registration is accepted and priced, the player has to pay, the
organisers have to see who paid, a late payer needs a nudge, and a bank
transfer has to find its registration. Larpinq does none of it, and under
hydra ADR-107 it must not book money itself: shillinq is the fleet's ledger.
This change connects the two. An accepted registration asks shillinq for a
payment request through shillinq's payment requests leaf, shows the player the
payment link and a transfer reference, reads the payment state back onto the
registration and the event roster, reminds the player before the pay-by date,
and releases the place when the date passes unpaid. Invoices, receipts and
bank statement matching are shillinq's.

## Motivation

Five registration rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for all five: each has two competitors rated yes.

**`reg-online-payment`**, "Take payment for an event online." Larpinq rates it
no. Matrix evidence: "grep -rniE "payment" lib src --include=*.php
--include=*.vue --include=*.js: no hits".

- LarpManager (yes): "larpmanager/fixtures/payment_methods.yaml:23-100 PayPal, Stripe, SumUp, Redsys, Satispay, larpmanager/urls/user.py accounting_webhook_* routes" (source read at main 36f23d3).
- pretix (yes): "src/pretix/base/payment.py provider framework with in-tree providers src/pretix/plugins/stripe, src/pretix/plugins/paypal2 and src/pretix/plugins/banktransfer, driven by src/pretix/presale/checkoutflow.py:1274 PaymentStep" (source read at tag v2026.7.0).

**`reg-payment-status`**, "See who has paid and who still owes money for an
event." Larpinq rates it no, same evidence.

- LarpManager (yes): "larpmanager/urls/orga.py orga_registrations_accounting and orga_payments, larpmanager/fixtures/feature.yaml:621 deadlines page of overdue participants".
- pretix (yes): "src/pretix/base/models/orders.py:156-157 order status pending/paid and :1714 OrderPayment per payment, listed and filtered in src/pretix/control/views/orders.py:415 OrderList".

**`reg-invoices`**, "Send players an invoice or receipt for their
registration." Larpinq rates it no. Matrix evidence: "grep -rniE "invoice" lib
src ...: no hits".

- LarpManager (yes): "larpmanager/models/accounting.py:57-270 PaymentInvoice and ElectronicInvoice, larpmanager/fixtures/feature.yaml:957 receipts feature generating a PDF receipt per payment".
- pretix (yes): "src/pretix/base/models/invoices.py:68 Invoice rendered by src/pretix/base/invoicing/pdf.py and sent by email or e-invoice transmission (src/pretix/base/invoicing/email.py, peppol.py)".

**`reg-payment-reminders`**, "Remind players automatically when their
registration is still unpaid before it expires." Larpinq rates it no. Matrix
evidence: "there is no registration or order object ... and payment is not
modelled".

- LarpManager (yes): "larpmanager/fixtures/feature.yaml:90 remind feature, larpmanager/mail/remind.py:105-206 remember_pay, run daily by larpmanager/management/commands/automate.py:1076-1300 before payment deadlines".
- pretix (yes): "src/pretix/base/settings.py:2672 mail_days_order_expire_warning sends a reminder a set number of days before an unpaid order expires; 2026.7 also mails on incomplete bank transfers". Its changelog: https://pretix.eu/about/en/blog/20260729-release-2026-7-0/.

**`reg-bank-statement-match`**, "Upload a bank statement and have transfers
matched to the registrations they pay for." Larpinq rates it no, same
evidence as above.

- LarpManager (yes): "larpmanager/fixtures/feature.yaml:737 verification feature (upload bank statements to match and approve wire payments), larpmanager/urls/exe.py:388 exe_verification plus manual verification".
- pretix (yes): "src/pretix/plugins/banktransfer/views.py:68,424 imports CSV, MT940 and SEPA CAMT statements (src/pretix/plugins/banktransfer/camtimport.py) and matches them to orders". Its changelog: https://pretix.eu/about/en/blog/20260126-release-2026-1/.

All five ride on one link: a registration and its payment request in shillinq.

## Affected Projects

- [ ] Project: `larpinq`: a payment request per accepted registration through the shillinq leaf, payment fields on the registration, a listener on payment request changes, a daily job for reminders and expiry, and payment columns on the event pages.
- [ ] Project: `shillinq` (not specified here, reported to the coordinator): the object payment request, its leaf, receipts, invoices and bank matching, see Cross-Project Dependencies.

## Scope

### In Scope

- `event.payBy` (a date, or a number of days after acceptance) and `event.paymentRequired`.
- When a registration becomes accepted with price lines above zero, larpinq creates one payment request for it through the `shillinq-payment-requests` leaf, with the amount of its lines, a description, the player as debtor, `dueAt` = the pay-by date, and a transfer reference.
- `registration.paymentState` (`not-needed`, `open`, `paid`, `expired`), the payment link and the reference, shown to the player on My registrations.
- The state follows the payment request: a listener on shillinq's payment request object events sets `paid` when shillinq reports it captured.
- A daily job reminds a player with an open request 3 days before the pay-by date (Nextcloud notification and email) and cancels an accepted registration whose pay-by date passed unpaid, which frees the place for the waiting list.
- Paid and owed columns and a filter on the event's registrations list; a count of paid and open on the event page.
- A "Request an invoice" choice on the registration, passed on the payment request.

### Out of Scope

- Payment providers, the ledger, receipts, invoice documents, VAT, dunning and bank statement import: all shillinq.
- Refunds and credit: `registration-cancel-transfer-refund`.
- Money totals across registrations: shillinq reports them (ADR-107; row `ins-event-finance-report` was decided no for larpinq).

## Approach

The payment request is appended through shillinq's data-provider leaf
(hydra ADR-066), never by calling shillinq's code (hydra ADR-041). The state
comes back as OpenRegister object events on shillinq's `PaymentRequest`,
filtered to requests whose subject is a larpinq registration. A `TimedJob`
does reminders and expiry. Details in design.md.

## New Dependencies

None. Shillinq is optional: without it, `paymentRequired` events show "payment
handled outside larpinq" and the state is set by hand.

## Impact

- `lib/Settings/register.d/registration-payments-through-shillinq.json` (new): event and registration payment fields, the reminder notification rule.
- `lib/Service/RegistrationPaymentService.php`, `lib/Listener/PaymentRequestListener.php`, `lib/BackgroundJob/RegistrationPaymentJob.php` (new), registered in `lib/AppInfo/Application.php` and `appinfo/info.xml`.
- `src/manifest.json`: payment columns and counts on EventDetail and the registrations pages (in place).

## Cross-Project Dependencies

Shillinq, read on shillinq development (27 September 2026):

- `PaymentRequest` on an object (`subjectKind = object`, `subject`, `requestType`, `amount`, `description`, `debtor`, `dueAt`) and the leaf `shillinq-payment-requests` with `list` and `create`: the open change `case-payment-requests` (REQ-SOPR-001, REQ-SOPR-003), `lib/Integration/PaymentRequestLeafProvider.php`.
- Payment links and the signature-gated provider webhook: spec `ar-invoice-payment-links`.
- Bank statement import and matching rules: spec `bookkeeping-bank-reconciliation` (REQ-BR-001, REQ-BR-004).

Assumed and not yet specified in shillinq, reported for the coordinator:
matching a bank line to an object payment request by its reference; a
confirmation (receipt) mail to the debtor on capture of an object request; an
invoice made on request from an object payment request; and a `requestType`
suited to event fees (today `leges`, `dwangsom`, `deposit`, `other`), so this
change uses `other` until then.

## Risks

### Risk 1: The payment request exists but the registration never hears back
**Severity:** High. **Mitigation:** besides the object event listener, the daily job reads the leaf `list` for every open registration and corrects the state, so a missed event costs at most a day.

### Risk 2: A place is released while a payment is in flight
**Severity:** Medium. **Mitigation:** the job expires a registration only when the request is still `pending` a full day after the pay-by date; a captured payment after expiry is flagged to game masters to restore or refund.

### Risk 3: ADR-107 on summing
**Severity:** Low. **Mitigation:** the amount asked for one registration is the sum of its own listed lines, the same as shillinq's `case-payment-requests` precedent where the domain app passes the amount; nothing is aggregated across registrations, booked or reported in larpinq.

## Rollback Strategy

Unregister the listener and job, hide the columns. Payment requests stay in
shillinq, registrations keep their last state.

## Open Questions

None.
