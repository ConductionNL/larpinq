<?php

/**
 * Tests for ParticipantContact: the address a message reaches a participant at.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-messages/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\ParticipantContact;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Service\ContactServiceInterface;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * A participant is reached at their Nextcloud account's address, else at the
 * email on the contact card linked to their player (communication-mail-participants
 * task 1.2): the primary active card first, else the first active card with a
 * valid address.
 *
 * @spec openspec/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
 */
#[CoversClass(ParticipantContact::class)]
class ParticipantContactTest extends TestCase {

	/**
	 * The players' contact links asked for, by player id.
	 *
	 * @var list<string>
	 */
	private array $asked = [];

	/**
	 * The resolver over one player, the accounts given, and the contact links given.
	 *
	 * @param array<string, string>      $accounts      Account email by uid ('' for an account without one).
	 * @param list<array<string, mixed>> $links         The player's contact links.
	 * @param bool                       $withContainer Whether OpenRegister's contact service can be reached.
	 * @param LoggerInterface|null       $logger        The logger.
	 * @param bool                       $failing       Whether reading the links throws.
	 *
	 * @return ParticipantContact
	 */
	private function contact(array $accounts, array $links, bool $withContainer = true, ?LoggerInterface $logger = null, bool $failing = false): ParticipantContact {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(static fn (string $objectType, string $id): array => ['id' => $id, 'name' => 'Cees de Wit', 'userUid' => 'cees']);

		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid) use ($accounts): ?IUser {
			if (array_key_exists($uid, $accounts) === false) {
				return null;
			}
			$user = $this->createMock(IUser::class);
			$user->method('getEMailAddress')->willReturn($accounts[$uid] === '' ? null : $accounts[$uid]);
			$user->method('getDisplayName')->willReturn($uid);
			return $user;
		});

		$service = $this->createMock(ContactServiceInterface::class);
		$service->method('getContactsForObject')->willReturnCallback(function (string $objectUuid) use ($links, $failing): array {
			$this->asked[] = $objectUuid;
			if ($failing === true) {
				throw new RuntimeException('contacts unreadable');
			}
			return ['results' => $links, 'total' => count($links)];
		});
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(ParticipantContact::CONTACT_SERVICE)->willReturn($service);

		return new ParticipantContact(
			fetcher: $fetcher,
			users: $users,
			logger: ($logger ?? $this->createMock(LoggerInterface::class)),
			container: ($withContainer === true ? $container : null),
		);
	}//end contact()

	public function testTheAccountAddressWinsAndNoCardIsRead(): void {
		$person = $this->contact(accounts: ['cees' => 'cees@example.org'], links: [['email' => 'card@example.org', 'primaryParty' => true]])
			->forRegistration(registration: ['id' => 'r3', 'player' => 'p3']);

		$this->assertSame(['key' => 'uid:cees', 'name' => 'Cees de Wit', 'email' => 'cees@example.org'], $person);
		$this->assertSame([], $this->asked);
	}//end testTheAccountAddressWinsAndNoCardIsRead()

	public function testWithoutAnAccountAddressThePrimaryCardIsUsed(): void {
		$person = $this->contact(
			accounts: ['cees' => ''],
			links: [
				['email' => 'first@example.org', 'primaryParty' => false, 'active' => true],
				['email' => 'primary@example.org', 'primaryParty' => true, 'active' => true],
			]
		)->forRegistration(registration: ['id' => 'r3', 'player' => 'p3']);

		$this->assertSame('primary@example.org', $person['email']);
		$this->assertSame('uid:cees', $person['key']);
		$this->assertSame(['p3'], $this->asked);
	}//end testWithoutAnAccountAddressThePrimaryCardIsUsed()

	public function testWithoutAPrimaryCardTheFirstActiveValidAddressIsUsed(): void {
		$email = $this->contact(
			accounts: [],
			links: [
				['email' => '', 'primaryParty' => true],
				['email' => 'not an address', 'primaryParty' => false],
				['email' => 'old@example.org', 'primaryParty' => true, 'active' => false],
				['email' => ' cees.card@example.org ', 'primaryParty' => false],
				['email' => 'later@example.org'],
			]
		)->forRegistration(registration: ['id' => 'r3', 'player' => 'p3'])['email'];

		$this->assertSame('cees.card@example.org', $email);
	}//end testWithoutAPrimaryCardTheFirstActiveValidAddressIsUsed()

	public function testAPlayerWithoutAnAccountOrCardHasNoAddress(): void {
		$this->assertSame('', $this->contact(accounts: [], links: [])->forRegistration(registration: ['id' => 'r3', 'player' => 'p3'])['email']);
	}//end testAPlayerWithoutAnAccountOrCardHasNoAddress()

	public function testCardsThatCannotBeReadLeaveNoAddressAndAreLogged(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('debug')->with('Larpinq: the contact card of a player could not be read for a message.');

		$email = $this->contact(accounts: [], links: [], logger: $logger, failing: true)->forRegistration(registration: ['id' => 'r3', 'player' => 'p3'])['email'];

		$this->assertSame('', $email);
	}//end testCardsThatCannotBeReadLeaveNoAddressAndAreLogged()

	public function testWithoutOpenRegisterOnlyTheAccountCounts(): void {
		$email = $this->contact(accounts: [], links: [['email' => 'card@example.org', 'primaryParty' => true]], withContainer: false)
			->forRegistration(registration: ['id' => 'r3', 'player' => 'p3'])['email'];

		$this->assertSame('', $email);
		$this->assertSame([], $this->asked);
	}//end testWithoutOpenRegisterOnlyTheAccountCounts()

	public function testARegistrationWithoutAPlayerReadsNoCard(): void {
		$person = $this->contact(accounts: ['anna' => ''], links: [['email' => 'card@example.org']])
			->forRegistration(registration: ['id' => 'r9', 'playerUid' => 'anna']);

		$this->assertSame('', $person['email']);
		$this->assertSame([], $this->asked);
	}//end testARegistrationWithoutAPlayerReadsNoCard()
}//end class
