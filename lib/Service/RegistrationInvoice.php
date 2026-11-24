<?php

/**
 * Larpinq Registration Invoice
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
 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IURLGenerator;

/**
 * The invoice shillinq issued for a registration's payment request
 * (registration-payments-through-shillinq REQ-RPS-006). Shillinq issues the
 * invoice when the request is created with `invoiceRequested` and names it in
 * the request's `invoiceReference`; larpinq only shows where to download it
 * and never makes an invoice itself.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
 */
class RegistrationInvoice {

	/**
	 * Shillinq's route that serves an invoice as a document.
	 *
	 * @var string
	 */
	public const DOWNLOAD_ROUTE = 'shillinq.invoiceApi.pdf';

	/**
	 * Constructor.
	 *
	 * @param IURLGenerator $urls Builds the download link.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IURLGenerator $urls,
	) {
	}//end __construct()

	/**
	 * The invoice fields to write onto a registration: `invoiceId` and
	 * `invoiceUrl` when the request names an issued invoice the registration
	 * does not show yet, else none, so the invoice lands once.
	 *
	 * @param array<string, mixed> $registration The registration as stored.
	 * @param array<string, mixed> $request      Shillinq's payment request.
	 *
	 * @return array<string, string> The fields, or an empty array.
	 *
	 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
	 */
	public function fields(array $registration, array $request): array {
		$invoiceId = trim((string)($request['invoiceReference'] ?? ''));
		if ($invoiceId === '' || (string)($registration['invoiceId'] ?? '') === $invoiceId) {
			return [];
		}

		return [
			'invoiceId' => $invoiceId,
			'invoiceUrl' => $this->urls->linkToRouteAbsolute(self::DOWNLOAD_ROUTE, ['invoiceId' => $invoiceId]),
		];
	}//end fields()
}//end class
