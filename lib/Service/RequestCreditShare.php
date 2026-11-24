<?php

/**
 * Larpinq Request Credit Share
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * How much of a payment request shillinq paid from the debtor's open credit,
 * and what is still open (REQ-RCT-005). Shillinq applies open credit when it
 * creates a request and records it as a settlement with method `credit`;
 * when the credit covers the whole amount it stamps `settledVia: credit`.
 * Larpinq only copies those two numbers onto the registration, in cents, so
 * the player sees them. It keeps no balance of its own: the credit lives in
 * shillinq.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */
class RequestCreditShare {

	/**
	 * The settlement method and `settledVia` value shillinq uses for credit.
	 *
	 * @var string
	 */
	public const CREDIT = 'credit';

	/**
	 * The registration fields for the part of a request paid from credit.
	 *
	 * @param array<string, mixed> $registration The registration as stored.
	 * @param array<string, mixed> $request      Shillinq's payment request.
	 *
	 * @return array<string, int> `paidFromCredit` and `amountOpen` in cents,
	 *                            or nothing when no credit was used or the
	 *                            registration already shows these numbers.
	 *
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
	 */
	public function fields(array $registration, array $request): array {
		$credit = $this->creditCents(request: $request);
		if ($credit <= 0) {
			return [];
		}

		$due = $this->cents(value: $request['amount'] ?? null);
		$credit = min($credit, $due);
		$fields = ['paidFromCredit' => $credit, 'amountOpen' => max(0, $due - $credit)];
		if (($registration['paidFromCredit'] ?? null) === $fields['paidFromCredit']
			&& ($registration['amountOpen'] ?? null) === $fields['amountOpen']
		) {
			return [];
		}

		return $fields;
	}//end fields()

	/**
	 * Whether shillinq settled the whole request from credit.
	 *
	 * @param array<string, mixed> $request Shillinq's payment request.
	 *
	 * @return bool True when the request is settled by credit.
	 *
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
	 */
	public function coversAll(array $request): bool {
		return (string)($request['settledVia'] ?? '') === self::CREDIT
			&& trim((string)($request['settledAt'] ?? '')) !== '';
	}//end coversAll()

	/**
	 * The sum of the request's credit settlements, in cents.
	 *
	 * @param array<string, mixed> $request Shillinq's payment request.
	 *
	 * @return int The credit used.
	 */
	private function creditCents(array $request): int {
		$settlements = $request['settlements'] ?? [];
		if (is_array($settlements) === false) {
			return 0;
		}

		$cents = 0;
		foreach ($settlements as $settlement) {
			if (is_array($settlement) === true && (string)($settlement['method'] ?? '') === self::CREDIT) {
				$cents += $this->cents(value: $settlement['amount'] ?? null);
			}
		}

		return $cents;
	}//end creditCents()

	/**
	 * A decimal amount from shillinq in cents; zero when it is not a number.
	 *
	 * @param mixed $value The amount, such as 85 or "85.00".
	 *
	 * @return int The amount in cents, never below zero.
	 */
	private function cents(mixed $value): int {
		if (is_numeric($value) === false) {
			return 0;
		}

		return max(0, (int)round((float)$value * 100));
	}//end cents()
}//end class
