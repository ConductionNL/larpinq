<?php

/**
 * Larpinq Write Stamp
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A server-set "who and when" pair of fields on an object: the stamp a write
 * receives, and the stored values an update keeps.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
final class WriteStamp {

	/**
	 * The stamp for a write by a user now.
	 *
	 * @param string[] $fields The "who" field, then the "when" field.
	 * @param string   $uid    The acting user's id.
	 *
	 * @return array<string,string> The who field set to the user, the when field to now.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public static function now(array $fields, string $uid): array {
		return [
			$fields[0] => $uid,
			$fields[1] => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
		];
	}//end now()

	/**
	 * The stamp fields an object already carries with a value.
	 *
	 * @param array<string,mixed> $object The stored object payload.
	 * @param string[]            $fields The stamp fields.
	 *
	 * @return array<string,mixed> The stamp fields that are neither null nor empty.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public static function stored(array $object, array $fields): array {
		return array_filter(
			array_intersect_key($object, array_flip($fields)),
			static fn ($value): bool => $value !== null && $value !== ''
		);
	}//end stored()
}//end class
