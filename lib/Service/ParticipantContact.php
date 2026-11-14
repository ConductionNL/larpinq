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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves a registration to one person: the player's name and the email of
 * their Nextcloud account (the registration's `playerUid`, else the player's
 * `userUid`), else the email on the contact card linked to the player, keyed
 * so one person with two registrations counts once.
 */
class ParticipantContact {

	/**
	 * OpenRegister's contact links of an object (the player's contacts leaf).
	 */
	public const CONTACT_SERVICE = 'OCA\OpenRegister\Service\ContactServiceInterface';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads the player.
	 * @param IUserManager $users Accounts, for the address.
	 * @param LoggerInterface $logger The logger.
	 * @param ContainerInterface|null $container Resolves OpenRegister's contact links; null leaves the account address only.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IUserManager $users,
		private readonly LoggerInterface $logger,
		private readonly ?ContainerInterface $container = null,
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

		$email = (string)($user?->getEMailAddress() ?? '');
		if ($email === '' && $playerId !== '') {
			$email = $this->contactEmail(playerId: $playerId);
		}

		return ['key' => $key, 'name' => $name, 'email' => $email];
	}//end forRegistration()

	/**
	 * The email on the contact card linked to the player: the primary active
	 * one first, else the first active one with an address; '' when the player
	 * has none or the contact links cannot be read.
	 *
	 * @param string $playerId The player's object id.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function contactEmail(string $playerId): string {
		if ($this->container === null || interface_exists(self::CONTACT_SERVICE) === false) {
			return '';
		}

		try {
			$links = (array)($this->container->get(self::CONTACT_SERVICE)->getContactsForObject($playerId)['results'] ?? []);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: the contact card of a player could not be read for a message.', ['exception' => $e]);
			return '';
		}

		$addresses = [];
		foreach ($links as $link) {
			if (is_array($link) === false) {
				continue;
			}

			$email = trim((string)($link['email'] ?? ''));
			if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || ($link['active'] ?? true) === false) {
				continue;
			}

			if (($link['primaryParty'] ?? false) === true) {
				return $email;
			}

			$addresses[] = $email;
		}

		return ($addresses[0] ?? '');
	}//end contactEmail()
}//end class
