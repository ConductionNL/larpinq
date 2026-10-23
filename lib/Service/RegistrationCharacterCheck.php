<?php

/**
 * Larpinq Registration Character Check
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Throwable;

/**
 * Whether a registration may bring a character: the registration's player's
 * own, active, and of the event's world.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationCharacterCheck {

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the character and the event as the signed-in user.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
	) {
	}//end __construct()

	/**
	 * Why a player may not bring this character, or null when they may.
	 *
	 * @param array<string, mixed> $registration The registration as it will be.
	 *
	 * @return string|null The reason, untranslated, or null.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function refusal(array $registration): ?string {
		try {
			$character = $this->fetcher->getObject(objectType: 'character', id: (string)($registration['character'] ?? ''));
		} catch (Throwable $e) {
			return 'This character does not exist or is not yours.';
		}

		$player = (string)($registration['player'] ?? '');
		if ($player === '' || (string)($character['ocName'] ?? '') !== $player) {
			return 'You can only bring one of your own characters.';
		}

		if ((string)($character['status'] ?? 'active') !== 'active') {
			return 'A retired or dead character cannot be brought to an event.';
		}

		$world = $this->worldOf(eventId: (string)($registration['event'] ?? ''));
		if ($world !== '' && (string)($character['setting'] ?? '') !== $world) {
			return 'The character must belong to the world of the event.';
		}

		return null;
	}//end refusal()

	/**
	 * The world of the event, or empty when it has none or cannot be read.
	 *
	 * @param string $eventId The event.
	 *
	 * @return string The world uuid.
	 */
	private function worldOf(string $eventId): string {
		try {
			return (string)($this->fetcher->getObject(objectType: 'event', id: $eventId)['setting'] ?? '');
		} catch (Throwable $e) {
			return '';
		}
	}//end worldOf()
}//end class
