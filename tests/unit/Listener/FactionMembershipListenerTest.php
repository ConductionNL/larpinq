<?php

/**
 * FactionMembershipListener: who may move a membership, run a group and name
 * a relationship (characters-factions-and-relationships D3).
 *
 * Built with the OpenRegister event classes the listener receives in
 * production (ObjectCreatingEvent carries getObject(), ObjectUpdatingEvent
 * getNewObject() and getOldObject()) and the real CharacterConnectionGuard.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/characters-factions-and-relationships/specs/character-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\FactionMembershipListener;
use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An object entity with a schema, uuid and data, as OpenRegister hands it over.
 */
class ConnectionObjectEntity extends ObjectEntity {
	/**
	 * Constructor.
	 *
	 * @param string $schema The schema id.
	 * @param string $uuid The uuid.
	 * @param array<string,mixed> $data The object data.
	 */
	public function __construct(string $schema, string $uuid, array $data) {
		$this->schema = $schema;
		$this->uuid = $uuid;
		$this->object = $data;
	}
}

/**
 * REQ-CFR-003, REQ-CFR-004 and the relationship rule of REQ-CFR-005.
 */
class FactionMembershipListenerTest extends TestCase {

	private const GUARD = '10000000-0000-4000-8000-000000000001';

	private const LANTERNS = '10000000-0000-4000-8000-000000000002';

	private const CIRCLE = '10000000-0000-4000-8000-000000000003';

	private const TOMAS = '20000000-0000-4000-8000-000000000001';

	private const VENN = '20000000-0000-4000-8000-000000000002';

	private const HARROW = '20000000-0000-4000-8000-000000000003';

	private const MEMBERSHIP = '30000000-0000-4000-8000-000000000001';

	/**
	 * Objects the fetcher knows, by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $objects = [];

	/**
	 * Set up the world: a faction, an open group led by Tomas (player tom),
	 * a secret faction, and characters of tom, vera and karel.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->objects = [
			self::GUARD => ['id' => self::GUARD, 'name' => "The Crown's Guard", 'kind' => 'faction', 'visibility' => 'open'],
			self::LANTERNS => ['id' => self::LANTERNS, 'name' => 'The Lantern Bearers', 'kind' => 'group', 'visibility' => 'open', 'leader' => self::TOMAS, 'leaderOwnerUid' => 'tom'],
			self::CIRCLE => ['id' => self::CIRCLE, 'name' => 'The Ash Circle', 'kind' => 'faction', 'visibility' => 'secret'],
			self::TOMAS => ['id' => self::TOMAS, 'name' => 'Tomas', 'ownerUid' => 'tom'],
			self::VENN => ['id' => self::VENN, 'name' => 'Lady Venn', 'ownerUid' => 'vera'],
			self::HARROW => ['id' => self::HARROW, 'name' => 'Old Captain Harrow', 'ownerUid' => 'karel'],
		];
	}//end setUp()

	/**
	 * The listener with the real guard, acting as one user.
	 *
	 * @param string|null $uid The signed-in user, or null for a system write.
	 * @param bool $gameMaster Whether that user is a game master.
	 * @param bool $fetcherThrows Whether every read fails.
	 *
	 * @return FactionMembershipListener The listener.
	 */
	private function listener(?string $uid, bool $gameMaster = false, bool $fetcherThrows = false): FactionMembershipListener {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id) use ($fetcherThrows): array {
				if ($fetcherThrows === true || isset($this->objects[$id]) === false) {
					throw new \RuntimeException('not found');
				}

				return $this->objects[$id];
			}
		);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => [
				'faction_schema' => 'schema-faction',
				'factionmember_schema' => 'schema-member',
				'relationship_schema' => 'schema-relationship',
			][$key] ?? $default
		);

		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(
			fn (string $userId, string $group): bool => $gameMaster === true && $group === 'gamemasters'
		);
		$groups->method('isAdmin')->willReturn(false);

		return new FactionMembershipListener(
			new CharacterConnectionGuard($fetcher),
			$config,
			$session,
			$groups,
			$this->createMock(LoggerInterface::class)
		);
	}//end listener()

	/**
	 * A create event.
	 *
	 * @param string $schema The schema id.
	 * @param array<string,mixed> $data The new object.
	 *
	 * @return ObjectCreatingEvent The event.
	 */
	private function create(string $schema, array $data): ObjectCreatingEvent {
		return new ObjectCreatingEvent(new ConnectionObjectEntity($schema, self::MEMBERSHIP, $data));
	}//end create()

	/**
	 * An update event.
	 *
	 * @param string $schema The schema id.
	 * @param array<string,mixed> $new The new object.
	 * @param array<string,mixed> $old The stored object.
	 *
	 * @return ObjectUpdatingEvent The event.
	 */
	private function update(string $schema, array $new, array $old): ObjectUpdatingEvent {
		return new ObjectUpdatingEvent(
			new ConnectionObjectEntity($schema, self::MEMBERSHIP, $new),
			new ConnectionObjectEntity($schema, self::MEMBERSHIP, $old)
		);
	}//end update()

	/**
	 * Run the listener and return the refusal code, or null when it passed.
	 *
	 * @param FactionMembershipListener $listener The listener.
	 * @param ObjectCreatingEvent|ObjectUpdatingEvent $event The event.
	 *
	 * @return string|null The error code.
	 */
	private function refusal(FactionMembershipListener $listener, Event $event): ?string {
		$listener->handle($event);
		if ($event->isPropagationStopped() === false) {
			$this->assertSame([], $event->getErrors());
			return null;
		}

		$errors = $event->getErrors();
		$this->assertNotEmpty($errors['message'] ?? '', 'a refusal names its reason');
		return $errors['code'];
	}//end refusal()

	/**
	 * A membership row.
	 *
	 * @param string $faction The faction id.
	 * @param string $character The character id.
	 * @param string $status The status.
	 * @param string $role The role.
	 *
	 * @return array<string,string> The membership.
	 */
	private function member(string $faction, string $character, string $status, string $role = 'member'): array {
		return ['faction' => $faction, 'character' => $character, 'status' => $status, 'role' => $role];
	}//end member()

	/**
	 * Scenario "A player cannot join without asking" (REQ-CFR-003).
	 *
	 * @return void
	 */
	public function testAPlayerCannotMakeTheirCharacterAnActiveMember(): void {
		$event = $this->create(schema: 'schema-member', data: $this->member(faction: self::LANTERNS, character: self::HARROW, status: 'active'));
		$this->assertSame('membership_needs_invitation', $this->refusal(listener: $this->listener(uid: 'karel'), event: $event));
	}//end testAPlayerCannotMakeTheirCharacterAnActiveMember()

	/**
	 * A player asks to join an open group with their own character.
	 *
	 * @return void
	 */
	public function testAPlayerMayAskToJoinWithTheirOwnCharacter(): void {
		$event = $this->create(schema: 'schema-member', data: $this->member(faction: self::LANTERNS, character: self::HARROW, status: 'requested'));
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'karel'), event: $event));
	}//end testAPlayerMayAskToJoinWithTheirOwnCharacter()

	/**
	 * A player cannot ask on behalf of another player's character.
	 *
	 * @return void
	 */
	public function testAPlayerCannotAskForSomeoneElsesCharacter(): void {
		$event = $this->create(schema: 'schema-member', data: $this->member(faction: self::LANTERNS, character: self::VENN, status: 'requested'));
		$this->assertSame('membership_own_character_only', $this->refusal(listener: $this->listener(uid: 'karel'), event: $event));
	}//end testAPlayerCannotAskForSomeoneElsesCharacter()

	/**
	 * Only the leader invites; the leader may invite any character.
	 *
	 * @return void
	 */
	public function testOnlyTheLeaderInvites(): void {
		$invite = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'invited');
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'tom'), event: $this->create(schema: 'schema-member', data: $invite)));
		$this->assertSame(
			'membership_leader_only',
			$this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-member', data: $invite))
		);
	}//end testOnlyTheLeaderInvites()

	/**
	 * The leader places their own character as the active leader of the group.
	 *
	 * @return void
	 */
	public function testTheLeaderJoinsTheirOwnGroup(): void {
		$event = $this->create(schema: 'schema-member', data: $this->member(faction: self::LANTERNS, character: self::TOMAS, status: 'active', role: 'leader'));
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'tom'), event: $event));
	}//end testTheLeaderJoinsTheirOwnGroup()

	/**
	 * Game masters place characters in factions; players never do.
	 *
	 * @return void
	 */
	public function testOnlyGameMastersPlaceCharactersInFactions(): void {
		$data = $this->member(faction: self::GUARD, character: self::HARROW, status: 'requested');
		$this->assertSame(
			'faction_game_masters_only',
			$this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-member', data: $data))
		);

		$active = $this->member(faction: self::GUARD, character: self::HARROW, status: 'active');
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'gm', gameMaster: true), event: $this->create(schema: 'schema-member', data: $active)));
	}//end testOnlyGameMastersPlaceCharactersInFactions()

	/**
	 * Scenario "A leader invites and the invitee accepts" (REQ-CFR-003).
	 *
	 * @return void
	 */
	public function testTheInviteeAcceptsAndNobodyElse(): void {
		$old = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'invited');
		$new = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'active');

		$this->assertNull($this->refusal(listener: $this->listener(uid: 'vera'), event: $this->update(schema: 'schema-member', new: $new, old: $old)));
		$this->assertSame(
			'membership_member_only',
			$this->refusal(listener: $this->listener(uid: 'tom'), event: $this->update(schema: 'schema-member', new: $new, old: $old))
		);
	}//end testTheInviteeAcceptsAndNobodyElse()

	/**
	 * The leader accepts a request; the asking player cannot accept it.
	 *
	 * @return void
	 */
	public function testTheLeaderAcceptsARequestAndNobodyElse(): void {
		$old = $this->member(faction: self::LANTERNS, character: self::HARROW, status: 'requested');
		$new = $this->member(faction: self::LANTERNS, character: self::HARROW, status: 'active');

		$this->assertNull($this->refusal(listener: $this->listener(uid: 'tom'), event: $this->update(schema: 'schema-member', new: $new, old: $old)));
		$this->assertSame(
			'membership_leader_only',
			$this->refusal(listener: $this->listener(uid: 'karel'), event: $this->update(schema: 'schema-member', new: $new, old: $old))
		);
	}//end testTheLeaderAcceptsARequestAndNobodyElse()

	/**
	 * Scenario "A member leaves" (REQ-CFR-004): the member leaves, the leader
	 * removes, and a left membership cannot be brought back by a player.
	 *
	 * @return void
	 */
	public function testLeavingAndRemovalAreStatusChanges(): void {
		$active = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'active');
		$left = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'left');
		$removed = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'removed');

		$this->assertNull($this->refusal(listener: $this->listener(uid: 'vera'), event: $this->update(schema: 'schema-member', new: $left, old: $active)));
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'tom'), event: $this->update(schema: 'schema-member', new: $removed, old: $active)));
		$this->assertSame(
			'membership_status_not_allowed',
			$this->refusal(listener: $this->listener(uid: 'vera'), event: $this->update(schema: 'schema-member', new: $active, old: $left))
		);
	}//end testLeavingAndRemovalAreStatusChanges()

	/**
	 * A player cannot move a membership to another character or faction.
	 *
	 * @return void
	 */
	public function testAMembershipCannotBeMovedByAPlayer(): void {
		$old = $this->member(faction: self::LANTERNS, character: self::VENN, status: 'active');
		$new = $this->member(faction: self::CIRCLE, character: self::VENN, status: 'active');
		$this->assertSame(
			'membership_moved',
			$this->refusal(listener: $this->listener(uid: 'vera'), event: $this->update(schema: 'schema-member', new: $new, old: $old))
		);
	}//end testAMembershipCannotBeMovedByAPlayer()

	/**
	 * A player creates a group led by their own character, never a faction and
	 * never with someone else's character as leader.
	 *
	 * @return void
	 */
	public function testPlayersRunGroupsLedByTheirOwnCharacter(): void {
		$group = ['name' => 'The Night Watch', 'kind' => 'group', 'visibility' => 'open', 'leader' => self::HARROW];
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-faction', data: $group)));

		$faction = ['name' => 'The Night Watch', 'kind' => 'faction', 'leader' => self::HARROW];
		$this->assertSame(
			'faction_game_masters_only',
			$this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-faction', data: $faction))
		);

		$borrowed = ['name' => 'The Night Watch', 'kind' => 'group', 'leader' => self::VENN];
		$this->assertSame(
			'group_leader_own_character_only',
			$this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-faction', data: $borrowed))
		);
	}//end testPlayersRunGroupsLedByTheirOwnCharacter()

	/**
	 * Scenario "A player records a rival" (REQ-CFR-005): from their own
	 * character, known to owners; a game-master-only one is not theirs to make.
	 *
	 * @return void
	 */
	public function testAPlayerNamesRelationshipsFromTheirOwnCharacter(): void {
		$rival = ['from' => self::HARROW, 'to' => self::TOMAS, 'kind' => 'rival', 'knownTo' => 'owners'];
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-relationship', data: $rival)));

		$this->assertSame(
			'relationship_own_character_only',
			$this->refusal(listener: $this->listener(uid: 'vera'), event: $this->create(schema: 'schema-relationship', data: $rival))
		);

		$hidden = ['from' => self::HARROW, 'to' => self::TOMAS, 'kind' => 'family'];
		$this->assertSame(
			'relationship_game_masters_only',
			$this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-relationship', data: $hidden))
		);
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'gm', gameMaster: true), event: $this->create(schema: 'schema-relationship', data: $hidden)));
	}//end testAPlayerNamesRelationshipsFromTheirOwnCharacter()

	/**
	 * When the faction or character cannot be read the write is refused: an
	 * authorization check does not fail open.
	 *
	 * @return void
	 */
	public function testAnUnreadableFactionRefusesTheWrite(): void {
		$event = $this->create(schema: 'schema-member', data: $this->member(faction: self::LANTERNS, character: self::HARROW, status: 'requested'));
		$this->assertSame(
			'connection_unverifiable',
			$this->refusal(listener: $this->listener(uid: 'karel', fetcherThrows: true), event: $event)
		);
	}//end testAnUnreadableFactionRefusesTheWrite()

	/**
	 * Writes to other schemas and writes without a user (import, seeding)
	 * pass untouched.
	 *
	 * @return void
	 */
	public function testOtherSchemasAndSystemWritesPass(): void {
		$active = $this->member(faction: self::LANTERNS, character: self::HARROW, status: 'active');
		$this->assertNull($this->refusal(listener: $this->listener(uid: 'karel'), event: $this->create(schema: 'schema-character', data: $active)));
		$this->assertNull($this->refusal(listener: $this->listener(uid: null), event: $this->create(schema: 'schema-member', data: $active)));
	}//end testOtherSchemasAndSystemWritesPass()
}//end class
