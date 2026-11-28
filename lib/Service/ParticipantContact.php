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
 * @spec openspec/specs/event-messages/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IUser;
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
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	public function forRegistration(array $registration): array {
		$playerId = (string)($registration['player'] ?? '');
		$player = $this->readPlayer(playerId: $playerId);
		$uid = (string)($registration['playerUid'] ?? '');
		if ($uid === '') {
			$uid = (string)($player['userUid'] ?? '');
		}

		$user = null;
		if ($uid !== '') {
			$user = $this->users->get($uid);
		}

		$email = (string)($user?->getEMailAddress() ?? '');
		if ($email === '' && $playerId !== '') {
			$email = $this->contactEmail(playerId: $playerId);
		}

		return [
			'key'   => $this->personKey(registration: $registration, playerId: $playerId, uid: $uid),
			'name'  => $this->personName(player: $player, user: $user, uid: $uid, playerId: $playerId),
			'email' => $email,
		];
	}//end forRegistration()

	/**
	 * The key that counts a person once: their account, else their player,
	 * else the registration itself.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string               $playerId     The player's object id, or ''.
	 * @param string               $uid          The account's user id, or ''.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function personKey(array $registration, string $playerId, string $uid): string {
		if ($uid !== '') {
			return 'uid:'.$uid;
		}

		if ($playerId !== '') {
			return 'player:'.$playerId;
		}

		return 'registration:'.(string)($registration['id'] ?? '');
	}//end personKey()

	/**
	 * The name to show: the player's name, else the account's display name,
	 * else the user id, else the player id.
	 *
	 * @param array<string, mixed> $player   The player object, or [].
	 * @param IUser|null           $user     The account, when there is one.
	 * @param string               $uid      The account's user id, or ''.
	 * @param string               $playerId The player's object id, or ''.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function personName(array $player, ?IUser $user, string $uid, string $playerId): string {
		$name = (string)($player['name'] ?? '');
		if ($name === '') {
			$name = $user?->getDisplayName() ?? $uid;
		}

		if ($name === '') {
			$name = $playerId;
		}

		return $name;
	}//end personName()

	/**
	 * The player object behind a registration, or an empty array when the
	 * registration names no player or the player cannot be read.
	 *
	 * @param string $playerId The player's object id, '' when there is none.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function readPlayer(string $playerId): array {
		if ($playerId === '') {
			return [];
		}

		try {
			return (array)$this->fetcher->getObject(objectType: 'player', id: $playerId);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: the player of a registration could not be read for a message.', ['exception' => $e]);
			return [];
		}
	}//end readPlayer()

	/**
	 * The email on the contact card linked to the player: the primary active
	 * one first, else the first active one with an address; '' when the player
	 * has none or the contact links cannot be read.
	 *
	 * @param string $playerId The player's object id.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function contactEmail(string $playerId): string {
		$addresses = [];
		foreach ($this->contactLinks(playerId: $playerId) as $link) {
			$email = $this->activeAddress(link: $link);
			if ($email === '') {
				continue;
			}

			if (($link['primaryParty'] ?? false) === true) {
				return $email;
			}

			$addresses[] = $email;
		}

		return ($addresses[0] ?? '');
	}//end contactEmail()

	/**
	 * The contact links of a player, or an empty array when the contacts
	 * service is absent or the links cannot be read.
	 *
	 * @param string $playerId The player's object id.
	 *
	 * @return array<int|string, mixed>
	 *
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function contactLinks(string $playerId): array {
		if ($this->container === null || interface_exists(self::CONTACT_SERVICE) === false) {
			return [];
		}

		try {
			return (array)($this->container->get(self::CONTACT_SERVICE)->getContactsForObject($playerId)['results'] ?? []);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: the contact card of a player could not be read for a message.', ['exception' => $e]);
			return [];
		}
	}//end contactLinks()

	/**
	 * The valid email of an active contact link, or '' when the link is not
	 * an array, is inactive or carries no valid address.
	 *
	 * @param mixed $link One contact link.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	private function activeAddress(mixed $link): string {
		if (is_array($link) === false || ($link['active'] ?? true) === false) {
			return '';
		}

		$email = trim((string)($link['email'] ?? ''));
		if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			return '';
		}

		return $email;
	}//end activeAddress()
}//end class
