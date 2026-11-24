<?php

/**
 * Larpinq Registration Credit Share
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
 * How much of a registration's payment request shillinq paid from the
 * player's open credit, and what is still open (REQ-RCT-005). Shillinq applies
 * open credit when it creates a request and records it as a settlement with
 * method `credit`; larpinq reads that from the request and keeps no balance
 * of its own.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
 */
class RegistrationCreditShare {

	/**
	 * The settlement method shillinq records for money paid from open credit.
	 */
	public const METHOD = 'credit';

	/**
	 * The registration fields for the part paid from credit, or none when
	 * credit paid nothing.
	 *
	 * @param array<string, mixed> $request The payment request as shillinq returns it.
	 *
	 * @return array{paidFromCredit?: float, amountOpen?: float} The fields to store.
	 *
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
	 */
	public function fields(array $request): array {
		$credit = $this->sum(request: $request, method: self::METHOD);
		if ($credit <= 0.0) {
			return [];
		}

		$open = round(((float)($request['amount'] ?? 0)) - $this->sum(request: $request, method: null), 2);

		return ['paidFromCredit' => $credit, 'amountOpen' => max(0.0, $open)];
	}//end fields()

	/**
	 * Whether credit alone paid the whole request.
	 *
	 * @param array<string, mixed> $request The payment request as shillinq returns it.
	 *
	 * @return bool True when credit paid it and nothing is open.
	 *
	 * @spec openspec/changes/registration-cancel-transfer-refund/specs/event-registration/spec.md#requirement-a-credit-pays-towards-the-players-next-registration-req-rct-005
	 */
	public function paidInFull(array $request): bool {
		$fields = $this->fields(request: $request);

		return $fields !== [] && $fields['amountOpen'] <= 0.0;
	}//end paidInFull()

	/**
	 * The settled amount of the request, of one method or of all.
	 *
	 * @param array<string, mixed> $request The payment request.
	 * @param string|null $method Only settlements with this method, or all.
	 *
	 * @return float The amount, rounded to cents.
	 */
	private function sum(array $request, ?string $method): float {
		$settlements = $request['settlements'] ?? [];
		if (is_array($settlements) === false) {
			return 0.0;
		}

		$total = 0.0;
		foreach ($settlements as $settlement) {
			if (is_array($settlement) === false || is_numeric($settlement['amount'] ?? null) === false) {
				continue;
			}

			if ($method !== null && (string)($settlement['method'] ?? '') !== $method) {
				continue;
			}

			$total += (float)$settlement['amount'];
		}

		return round($total, 2);
	}//end sum()
}//end class
