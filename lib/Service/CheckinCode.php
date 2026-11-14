<?php

/**
 * The check-in code of an accepted registration.
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
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Makes and reads check-in codes: 26 characters of base32 (A-Z, 2-7) from 16
 * random bytes, so a code cannot be guessed.
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
 */
final class CheckinCode {

	/**
	 * The base32 alphabet (RFC 4648), without padding.
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
	 * A new code.
	 *
	 * @return string 26 characters of base32.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#1.1
	 */
	public static function generate(): string {
		return self::encode(bytes: random_bytes(16));
	}//end generate()

	/**
	 * The base32 text of 16 bytes.
	 *
	 * @param string $bytes The bytes.
	 *
	 * @return string The code.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#1.1
	 */
	public static function encode(string $bytes): string {
		$bits = '';
		foreach (str_split($bytes) as $byte) {
			$bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
		}

		$code = '';
		foreach (str_split($bits, 5) as $chunk) {
			$code .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
		}

		return substr($code, 0, self::LENGTH);
	}//end encode()

	/**
	 * A typed or scanned code as stored: trimmed, upper case, without spaces or dashes.
	 *
	 * @param string $code The code as entered.
	 *
	 * @return string The normalised code, or '' when it cannot be one.
	 *
	 * @spec openspec/changes/events-qr-checkin/tasks.md#2.1
	 */
	public static function normalise(string $code): string {
		$code = strtoupper(str_replace([' ', '-'], '', trim($code)));
		if (preg_match('/^[A-Z2-7]{' . self::LENGTH . '}$/', $code) !== 1) {
			return '';
		}

		return $code;
	}//end normalise()
}//end class
