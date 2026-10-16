<?php

/**
 * Keeps retired and dead characters out of new events
 * (characters-status-and-bulk-edit D2).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Refuses a character write that adds an event while the character is not
 * active. Removing events, and any other change to a retired or dead
 * character, passes. A character without a status counts as active.
 *
 * @spec openspec/specs/character-management/spec.md
 */
class CharacterStatusGuard {

	/**
	 * The status a character has when none is stored.
	 *
	 * @var string
	 */
	public const DEFAULT_STATUS = 'active';

	/**
	 * Constructor.
	 *
	 * @param IdListNormaliser $idList Reads relation lists as UUIDs.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IdListNormaliser $idList,
	) {
	}//end __construct()

	/**
	 * The refusal for a write, or null when it may pass.
	 *
	 * @param array<string, mixed> $candidate The character as it will be saved.
	 * @param array<string, mixed> $old The stored character (empty on a create).
	 *
	 * @return array<string, mixed>|null The errors payload, with the error on `events`.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function check(array $candidate, array $old): ?array {
		$status = (string)($candidate['status'] ?? '');
		if ($status === '' || $status === self::DEFAULT_STATUS) {
			return null;
		}

		$added = array_diff(
			$this->idList->normalise($candidate['events'] ?? []),
			$this->idList->normalise($old['events'] ?? [])
		);
		if ($added === []) {
			return null;
		}

		$message = 'A ' . $status . ' character cannot join new events. Set the status to active first.';

		return [
			'code' => 'character_not_active',
			'message' => $message,
			'fields' => ['events' => [$message]],
		];
	}//end check()
}//end class
