<?php

/**
 * The person behind a registration and the address a message reaches them at.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves a registration to one person: the player's name and the email of
 * their Nextcloud account (the registration's `playerUid`, else the player's
 * `userUid`), keyed so one person with two registrations counts once.
 */
class ParticipantContact {

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the player.
	 * @param IUserManager $users Accounts, for the address.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IUserManager $users,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The person behind a registration: who they are (to count them once),
	 * their name and the email of their Nextcloud account, or '' when they
	 * have none.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array{key: string, name: string, email: string}
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	public function forRegistration(array $registration): array {
		$playerId = (string)($registration['player'] ?? '');
		$uid = (string)($registration['playerUid'] ?? '');
		$name = '';
		try {
			if ($playerId !== '') {
				$player = $this->fetcher->getObject(objectType: 'player', id: $playerId);
				$name = (string)($player['name'] ?? '');
				if ($uid === '') {
					$uid = (string)($player['userUid'] ?? '');
				}
			}
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: the player of a registration could not be read for a message.', ['exception' => $e]);
		}

		$user = null;
		$key = 'player:'.$playerId;
		if ($playerId === '') {
			$key = 'registration:'.(string)($registration['id'] ?? '');
		}

		if ($uid !== '') {
			$user = $this->users->get($uid);
			$key = 'uid:'.$uid;
		}

		if ($name === '') {
			$name = $user?->getDisplayName() ?? $uid;
		}

		if ($name === '') {
			$name = $playerId;
		}

		return ['key' => $key, 'name' => $name, 'email' => (string)($user?->getEMailAddress() ?? '')];
	}//end forRegistration()
}//end class
