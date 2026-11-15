<?php

/**
 * Submitting a rejected character again.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Throwable;

/**
 * Moves a rejected character back to "waiting for approval" (REQ-CTA-001).
 *
 * Only game masters may write the approval field, so the owner cannot make this
 * move with a plain save. The caller's right to the character is checked first
 * (its owner, or a game master); the write then carries the app's authority and
 * changes nothing but the approval state. The register's lifecycle matches the
 * move to its "resubmit" transition, which tells the game masters.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md
 */
class CharacterResubmissionService {

	/**
	 * The approval state of a rejected character.
	 *
	 * @var string
	 */
	public const REJECTED = 'rejected';

	/**
	 * The approval state of a character that waits for a game master.
	 *
	 * @var string
	 */
	public const WAITING = 'no';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes register objects.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
	) {
	}//end __construct()

	/**
	 * Submit a rejected character again.
	 *
	 * @param string $characterId  The character.
	 * @param string $uid          The caller.
	 * @param bool   $isGameMaster Whether the caller is a game master or administrator.
	 *
	 * @return array<string, mixed> The saved character.
	 *
	 * @throws ResubmissionRefusedException 404 when the character cannot be read, 403 when the
	 *                                      caller is neither its owner nor a game master, 409 when
	 *                                      it is not rejected.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
	 */
	public function resubmit(string $characterId, string $uid, bool $isGameMaster): array {
		try {
			$character = $this->fetcher->getObjectOfType(objectType: 'character', id: $characterId);
		} catch (Throwable) {
			throw new ResubmissionRefusedException(message: 'Character not found', status: 404);
		}

		$owner = (string)($character['ownerUid'] ?? '');
		if ($isGameMaster === false && ($owner === '' || $owner !== $uid)) {
			throw new ResubmissionRefusedException(message: 'Only the owner and game masters submit this character', status: 403);
		}

		if ((string)($character['approved'] ?? '') !== self::REJECTED) {
			throw new ResubmissionRefusedException(message: 'Only a rejected character is submitted again', status: 409);
		}

		$uuid = (string)($character['id'] ?? ($character['@self']['id'] ?? $characterId));
		unset($character['@self']);
		$character['approved'] = self::WAITING;

		// The whole character goes back as read, so property rules the caller does
		// not hold (private notes) are not mistaken for a change they make. The
		// rejection reason stays: the game master reads it with the new version.
		return $this->fetcher->saveObjectWithAppAuthority(objectType: 'character', data: $character, uuid: $uuid);
	}//end resubmit()
}//end class
