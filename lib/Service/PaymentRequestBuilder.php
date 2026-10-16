<?php

/**
 * Larpinq Payment Request Builder
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What a registration asks shillinq for: the amount of its own price lines in
 * currency units (hydra ADR-107: one registration's own lines, nothing added
 * up across registrations), the pay-by date, the debtor, and a transfer
 * reference unique per event (`<code>-<4 digits>`, such as WC26-0042).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class PaymentRequestBuilder {

	/**
	 * Days after acceptance a registration is due when its event names no pay-by date.
	 *
	 * @var integer
	 */
	private const DEFAULT_DAYS = 14;

	/**
	 * Upper bound of the registrations one event's references are counted over.
	 *
	 * @var integer
	 */
	private const MAX_ROWS = 2000;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the player and the event's registrations.
	 * @param IUserManager $users The player's email address.
	 * @param ITimeFactory $time The clock.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IUserManager $users,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The amount of a registration in cents: the sum of its own price lines.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return int The amount in cents.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function cents(array $registration): int {
		$cents = 0;
		foreach ((array)($registration['lines'] ?? []) as $line) {
			if (is_array($line) === true && is_numeric($line['amount'] ?? null) === true) {
				$cents += (int)$line['amount'];
			}
		}

		return $cents;
	}//end cents()

	/**
	 * When a registration must be paid: its own date, else the event's, else 14 days from now.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $event The event.
	 *
	 * @return string The moment (ATOM).
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function payBy(array $registration, array $event): string {
		foreach ([$registration['payBy'] ?? '', $event['payBy'] ?? ''] as $moment) {
			if (is_string($moment) === true && $moment !== '') {
				return $moment;
			}
		}

		return $this->time->now()->add(new DateInterval('P' . self::DEFAULT_DAYS . 'D'))->format(DateTimeInterface::ATOM);
	}//end payBy()

	/**
	 * The registration's reference, or the event's next one.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $event The event.
	 *
	 * @return string The reference.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function reference(array $registration, array $event): string {
		$kept = (string)($registration['paymentReference'] ?? '');
		if ($kept !== '') {
			return $kept;
		}

		$code = $this->code(event: $event);
		$highest = 0;
		$filters = ['event' => (string)($event['id'] ?? '')];
		$rows = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: $filters, limit: self::MAX_ROWS);
		foreach ($rows as $row) {
			if (preg_match('/^' . preg_quote($code, '/') . '-(\d+)$/', (string)($row['paymentReference'] ?? ''), $match) === 1) {
				$highest = max($highest, (int)$match[1]);
			}
		}

		return sprintf('%s-%04d', $code, $highest + 1);
	}//end reference()

	/**
	 * The leaf payload for a registration.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param array<string, mixed> $event The event.
	 * @param string $reference The transfer reference.
	 * @param string $payBy The pay-by moment.
	 *
	 * @return array<string, mixed> The payload.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function payload(array $registration, array $event, string $reference, string $payBy): array {
		$debtor = $this->debtor(registration: $registration);

		return [
			'subjectType' => 'registration',
			'requestType' => 'other',
			'amount' => round($this->cents(registration: $registration) / 100, 2),
			'currency' => $this->currency(registration: $registration),
			'description' => sprintf('%s, %s, %s', (string)($event['name'] ?? ''), $debtor['name'], $reference),
			'debtor' => $debtor,
			'dueAt' => $payBy,
			'invoiceRequested' => (($registration['invoiceRequested'] ?? false) === true),
		];
	}//end payload()

	/**
	 * Now, as stored.
	 *
	 * @return DateTimeImmutable The moment.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function now(): DateTimeImmutable {
		return $this->time->now();
	}//end now()

	/**
	 * The event's reference code: its own, else letters of its name.
	 *
	 * @param array<string, mixed> $event The event.
	 *
	 * @return string The code.
	 */
	private function code(array $event): string {
		$code = (string)($event['paymentCode'] ?? '');
		if ($code !== '') {
			return $code;
		}

		$letters = strtoupper(substr((string)preg_replace('/[^A-Za-z0-9]/', '', (string)($event['name'] ?? '')), 0, 4));
		if (strlen($letters) < 2) {
			return 'LARP';
		}

		return $letters;
	}//end code()

	/**
	 * The currency of the lines, EUR when they name none.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return string The currency.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function currency(array $registration): string {
		foreach ((array)($registration['lines'] ?? []) as $line) {
			if (is_array($line) === true && (string)($line['currency'] ?? '') !== '') {
				return (string)$line['currency'];
			}
		}

		return 'EUR';
	}//end currency()

	/**
	 * Who pays: the player's name, and their email when their account has one.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array{name: string, email?: string} The debtor.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function debtor(array $registration): array {
		$uid = (string)($registration['playerUid'] ?? '');
		$debtor = ['name' => $uid];
		try {
			$player = $this->fetcher->getObject(objectType: 'player', id: (string)($registration['player'] ?? ''));
			$debtor['name'] = (string)($player['name'] ?? $uid);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: the player of a registration could not be read for its payment.', ['exception' => $e]);
		}

		if ($uid === '') {
			return $debtor;
		}

		$email = (string)($this->users->get($uid)?->getEMailAddress() ?? '');
		if ($email !== '') {
			$debtor['email'] = $email;
		}

		return $debtor;
	}//end debtor()
}//end class
