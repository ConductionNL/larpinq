<?php

/**
 * Larpinq Check-in Codes
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
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * The check-in code of a registration: 26 base32 characters from 128 random
 * bits, made by the server when a registration is accepted and again when it
 * changes hands.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
class CheckinCodes {

	/**
	 * The base32 alphabet (RFC 4648).
	 *
	 * @var string
	 */
	private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * The length of a code.
	 *
	 * @var int
	 */
	public const LENGTH = 26;

	/**
	 * The registration status that carries a code.
	 *
	 * @var string
	 */
	private const ACCEPTED = 'accepted';

	/**
	 * A new random code.
	 *
	 * @return string The code.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	public function generate(): string {
		$bits = '';
		foreach (str_split(random_bytes(16)) as $byte) {
			$bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
		}

		$code = '';
		foreach (str_split(str_pad($bits, self::LENGTH * 5, '0'), 5) as $chunk) {
			$code .= self::ALPHABET[bindec($chunk)];
		}

		return $code;
	}//end generate()

	/**
	 * A typed or scanned code without case, spaces or dashes, or '' when it is not a code.
	 *
	 * @param string $code The code as entered.
	 *
	 * @return string The code, or ''.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	public function normalise(string $code): string {
		$clean = strtoupper(str_replace([' ', '-'], '', $code));
		if (preg_match('/^[A-Z2-7]{' . self::LENGTH . '}$/', $clean) !== 1) {
			return '';
		}

		return $clean;
	}//end normalise()

	/**
	 * The code a registration gets on this write, or [] when it keeps what it has.
	 *
	 * Only the server sets the code: an accepted registration keeps its stored
	 * code, gets a new one when it has none or changes player, and one that is
	 * not accepted keeps what it had (none on create).
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed>|null $old The stored registration, or null on create.
	 * @param string $status The status it will have after this write.
	 *
	 * @return array<string, mixed> The field to set.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	public function forWrite(array $new, ?array $old, string $status): array {
		$sent = (string)($new['checkinCode'] ?? '');
		$code = $this->codeAfter(new: $new, old: $old, status: $status);
		if ($code === $sent) {
			return [];
		}

		return ['checkinCode' => $code];
	}//end forWrite()

	/**
	 * The code a registration has after this write.
	 *
	 * @param array<string, mixed> $new The registration as it will be.
	 * @param array<string, mixed>|null $old The stored registration, or null on create.
	 * @param string $status The status it will have after this write.
	 *
	 * @return string The code, or ''.
	 */
	private function codeAfter(array $new, ?array $old, string $status): string {
		$stored = (string)($old['checkinCode'] ?? '');
		if ($status !== self::ACCEPTED) {
			return $stored;
		}

		if ($old !== null && (string)($new['player'] ?? '') !== (string)($old['player'] ?? '')) {
			return $this->generate();
		}

		if ($stored !== '') {
			return $stored;
		}

		// A stored registration without a code may take a well-formed one (the repair step's).
		$sent = $this->normalise(code: (string)($new['checkinCode'] ?? ''));
		if ($old !== null && $sent !== '') {
			return $sent;
		}

		return $this->generate();
	}//end codeAfter()
}//end class
