# Event payments

A player with a place has to pay for it. Larpinq does not handle money itself: shillinq, the finance app, holds the payment request, the payment link, receipts, invoices and the bank statement. Larpinq asks shillinq for the payment, shows the player what to pay, and follows the state shillinq reports.

## Set up an event that takes payment

1. Open the event and switch on **Payment required**.
2. Set **Pay by**, the date by which an accepted registration must be paid. Without a date, a registration is due 14 days after it is accepted.
3. Set a **Payment code** such as `WC26`. Every transfer reference of the event starts with it: `WC26-0001`, `WC26-0002`. Without a code, larpinq takes the first letters of the event name.

The amount comes from the registration's own ticket and options, at the price listed when the player chose them.

## When a registration is accepted

When you accept a registration as a game master, larpinq asks shillinq for one payment request for it. The registration then shows:

- **Payment**: open, then paid once shillinq reports the money in.
- **Reference**: what the player quotes on a bank transfer.
- **Payment link**: where the player pays online.
- **Pay by**: the date the payment is due.

A registration whose lines add up to nothing gets **No payment needed**.

Some registrations are accepted without a game master: a free place on sign-up, or a move up the waiting list. Shillinq only lets someone with its payment rights ask for money, so these registrations wait as **Payment to request**. Open the registration and choose **Request payment** from the actions. The same applies when your account lacks the payment rights in shillinq: ask the shillinq administrator for the `payment.request` action.

## Paid, reminded, released

- When shillinq captures the payment, through the link or a bank transfer it matched by the reference, the registration shows paid.
- Three days before the pay-by date, a player who has not paid gets a notification and an email with the link and the reference.
- A day after the pay-by date, a registration that is still unpaid is cancelled as **Not paid in time**. Its place goes to the first person on the waiting list.

Larpinq checks shillinq once a day as well, so a payment that arrived while the instance was busy is never missed.

## Who paid

The event page counts paid and open registrations next to the places taken, and lists each registration with its payment state and reference. The **Registrations** page filters on the payment state, so you can list who still owes. Larpinq shows counts, never money totals: those are in shillinq.

## Invoices

A player who needs an invoice, for example because a club pays, ticks **Request an invoice** on the registration before it is accepted. Larpinq passes the request on to shillinq, which makes the invoice.

## Without shillinq

Without shillinq, a registration of an event that takes payment waits as **Payment to request**. Game masters set the payment state by hand when the money comes in.
