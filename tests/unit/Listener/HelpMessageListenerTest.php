<?php

/**
 * The help desk thread (communication-help-desk, REQ-CHD-002, REQ-CHD-003):
 * the asker is copied from the player, a closed question refuses messages,
 * and the question's status follows its thread.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\HelpDeskCaller;
use OCA\Larpinq\Listener\HelpMessageListener;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An ObjectEntity with a schema and a payload.
 */
class HelpObjectEntity extends ObjectEntity {
	/**
	 * Build an entity.
	 *
	 * @param string              $schema The schema id.
	 * @param array<string,mixed> $data   The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * The listener over the real OpenRegister events.
 */
class HelpMessageListenerTest extends TestCase {

	private const QUESTION_SCHEMA = 'question-schema-id';

	private const MESSAGE_SCHEMA = 'message-schema-id';

	private const QUESTION = '11111111-1111-4111-8111-111111111111';

	private const PLAYER = '22222222-2222-4222-8222-222222222222';

	/** @var ?string The signed-in user. */
	private ?string $signedIn = 'joris';

	/** @var array<string,mixed> The stored question. */
	private array $question = ['subject' => 'Can I bring a real bow?', 'askerUid' => 'anna', 'ownerRef' => self::PLAYER, 'status' => 'open', 'player' => self::PLAYER];

	/** @var list<array{0:string,1:array<string,mixed>,2:?string}> The writes through the fetcher. */
	private array $saved = [];

	/**
	 * A question names a player: the asker and owner reference are copied from it.
	 *
	 * @return void
	 */
	public function testAQuestionTakesItsAskerFromThePlayer(): void {
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => ['id' => self::PLAYER], 'subject' => 'Bow?', 'askerUid' => 'mallory']));
		$event->setModifiedData(['kept' => 1]);

		$this->listener()->handle($event);

		$this->assertSame(['kept' => 1, 'askerUid' => 'anna', 'ownerRef' => self::PLAYER], $event->getModifiedData());
	}

	/**
	 * Scenario "Lotte asks through the portal": the portal stamps only `ownerRef`;
	 * the question then names that player and takes the asker from it (REQ-CHD-004).
	 *
	 * @return void
	 */
	public function testAPortalQuestionNamesThePlayerItsOwnerRefHolds(): void {
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['ownerRef' => self::PLAYER, 'subject' => 'Bow?']));

		$this->listener()->handle($event);

		$this->assertSame(['player' => self::PLAYER, 'askerUid' => 'anna', 'ownerRef' => self::PLAYER], $event->getModifiedData());
	}

	/**
	 * A question naming neither a player nor an owner reference, or an unknown one, is left as written.
	 *
	 * @return void
	 */
	public function testAQuestionWithoutAReadablePlayerIsLeftAlone(): void {
		$none = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['subject' => 'Bow?']));
		$this->listener()->handle($none);
		$this->assertSame([], $none->getModifiedData());

		$unknown = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['ownerRef' => '99999999-9999-4999-8999-999999999999', 'subject' => 'Bow?']));
		$this->listener()->handle($unknown);
		$this->assertSame([], $unknown->getModifiedData());
	}

	/**
	 * Scenario "Joris answers": a game master's message is stamped as theirs and from an organiser, and the question becomes answered.
	 *
	 * @return void
	 */
	public function testAGameMastersMessageAnswersTheQuestion(): void {
		$creating = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'body' => 'Only LARP-safe bows.', 'fromOrganiser' => false, 'author' => 'x']));
		$before = time();

		$this->listener()->handle($creating);

		$modified = $creating->getModifiedData();
		$this->assertFalse($creating->isPropagationStopped());
		$this->assertSame(['joris', true, 'anna'], [$modified['author'], $modified['fromOrganiser'], $modified['askerUid']]);
		$this->assertSame(self::PLAYER, $modified['ownerRef'], 'The portal reads the answers through the question owner reference');
		$this->assertGreaterThanOrEqual($before, strtotime($modified['createdAt']));

		$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'fromOrganiser' => true, 'createdAt' => '2026-09-20T18:05:00+00:00'])));

		$this->assertCount(1, $this->saved);
		[$type, $data, $uuid] = $this->saved[0];
		$this->assertSame(['helpquestion', self::QUESTION], [$type, $uuid]);
		$this->assertSame('answered', $data['status']);
		$this->assertSame('2026-09-20T18:05:00+00:00', $data['lastMessageAt']);
		$this->assertSame('Can I bring a real bow?', $data['subject']);
		$this->assertArrayNotHasKey('@self', $data);
	}

	/**
	 * Scenario "Anna replies": the asker's reply on an answered question opens it again.
	 *
	 * @return void
	 */
	public function testTheAskersReplyReopensTheQuestion(): void {
		$this->signedIn = 'anna';
		$this->question['status'] = 'answered';
		$creating = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'body' => 'Thanks, and arrows?']));

		$this->listener()->handle($creating);
		$this->assertSame([false, 'anna'], [$creating->getModifiedData()['fromOrganiser'], $creating->getModifiedData()['author']]);

		$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'fromOrganiser' => false, 'createdAt' => '2026-09-21T09:00:00+00:00'])));
		$this->assertSame('open', $this->saved[0][1]['status']);
	}

	/**
	 * A closed question refuses new messages, and nothing follows.
	 *
	 * @return void
	 */
	public function testAClosedQuestionRefusesMessages(): void {
		$this->signedIn = 'anna';
		$this->question['status'] = 'closed';
		$creating = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'body' => 'One more thing']));

		$this->listener()->handle($creating);

		$this->assertTrue($creating->isPropagationStopped());
		$this->assertSame(422, $creating->getErrors()['status']);
		$this->assertSame('larpinq.help.closed', $creating->getErrors()['code']);
		$this->assertSame([], $creating->getModifiedData());
	}

	/**
	 * A player who names another player on their question is refused.
	 *
	 * @return void
	 */
	public function testAPlayerCannotAskAsAnotherPlayer(): void {
		$this->signedIn = 'mallory';
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => self::PLAYER, 'subject' => 'Bow?']));

		$this->listener()->handle($event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame([403, 'larpinq.help.not_own_player'], [$event->getErrors()['status'], $event->getErrors()['code']]);
		$this->assertSame([], $event->getModifiedData());
	}

	/**
	 * A player's question naming no readable player is theirs, whatever asker it carried;
	 * their own player is taken as before.
	 *
	 * @return void
	 */
	public function testAPlayersQuestionWithoutAPlayerIsTheirOwn(): void {
		$this->signedIn = 'mallory';
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['subject' => 'Bow?', 'askerUid' => 'anna', 'ownerRef' => '99999999-9999-4999-8999-999999999999']));

		$this->listener()->handle($event);

		$this->assertFalse($event->isPropagationStopped());
		$this->assertSame(['askerUid' => 'mallory', 'ownerRef' => ''], $event->getModifiedData());

		$this->signedIn = 'anna';
		$own = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => self::PLAYER, 'subject' => 'Bow?']));
		$this->listener()->handle($own);
		$this->assertSame(['askerUid' => 'anna', 'ownerRef' => self::PLAYER], $own->getModifiedData());
	}

	/**
	 * Someone who is neither the asker nor a game master cannot write in the thread.
	 *
	 * @return void
	 */
	public function testAnOutsiderIsRefused(): void {
		$this->signedIn = 'mallory';
		$creating = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'body' => 'Hi']));

		$this->listener()->handle($creating);

		$this->assertTrue($creating->isPropagationStopped());
		$this->assertSame(403, $creating->getErrors()['status']);
	}

	/**
	 * The status after a message, for every case.
	 *
	 * @return void
	 */
	public function testTheStatusAfterAMessage(): void {
		$this->assertSame('answered', HelpMessageListener::statusAfter(status: 'open', fromOrganiser: true));
		$this->assertNull(HelpMessageListener::statusAfter(status: 'answered', fromOrganiser: true));
		$this->assertSame('open', HelpMessageListener::statusAfter(status: 'answered', fromOrganiser: false));
		$this->assertNull(HelpMessageListener::statusAfter(status: 'open', fromOrganiser: false));
		$this->assertNull(HelpMessageListener::statusAfter(status: 'closed', fromOrganiser: true));
		$this->assertSame('larpinq.help.no_question', HelpMessageListener::refusal(question: null, uid: 'anna', organiser: false)['code']);
		$this->assertNull(HelpMessageListener::refusal(question: ['status' => 'open', 'askerUid' => 'anna'], uid: '', organiser: false));
	}

	/**
	 * Only a creating event is prepared or merged into: any other event that
	 * reaches the preparing steps is left as it is.
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 *
	 * @return void
	 */
	public function testOnlyACreatingEventIsPreparedOrMerged(): void {
		$listener = $this->listener();
		$plain = new \OCP\EventDispatcher\Event();

		$onCreating = new \ReflectionMethod($listener, 'onCreating');
		$onCreating->invoke($listener, $plain);
		$merge = new \ReflectionMethod($listener, 'merge');
		$merge->invoke($listener, $plain, ['askerUid' => 'anna']);
		$this->assertFalse($plain->isPropagationStopped());

		$creating = new ObjectCreatingEvent(new HelpObjectEntity('character-schema-id', []));
		$merge->invoke($listener, $creating, ['askerUid' => 'anna']);
		$this->assertSame(['askerUid' => 'anna'], $creating->getModifiedData());
	}

	/**
	 * Objects of other schemas, and a system write, are left alone.
	 *
	 * @return void
	 */
	public function testOtherObjectsAndSystemWritesAreLeftAlone(): void {
		$other = new ObjectCreatingEvent(new HelpObjectEntity('character-schema-id', ['question' => self::QUESTION]));
		$this->listener()->handle($other);
		$this->assertSame([], $other->getModifiedData());

		$this->signedIn = null;
		$system = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'author' => 'joris', 'fromOrganiser' => true]));
		$this->listener()->handle($system);
		$this->assertFalse($system->isPropagationStopped());
		$this->assertSame([], $system->getModifiedData());

		$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity('character-schema-id', ['question' => self::QUESTION])));
		$this->assertSame([], $this->saved);
	}

	/**
	 * A player reference written as an object with a uuid names that player too.
	 *
	 * @return void
	 */
	public function testAPlayerReferencedByItsUuidGivesTheAsker(): void {
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => ['uuid' => ' ' . self::PLAYER . ' '], 'subject' => 'Bow?']));

		$this->listener()->handle($event);

		$this->assertSame(['askerUid' => 'anna', 'ownerRef' => self::PLAYER], $event->getModifiedData());
	}

	/**
	 * A player reference that is no text, or an empty one, names nobody.
	 *
	 * @return void
	 */
	public function testAPlayerReferenceThatIsNoTextNamesNobody(): void {
		foreach ([42, ['name' => 'Anna'], '   ', null] as $reference) {
			$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => $reference, 'subject' => 'Bow?']));
			$this->listener()->handle($event);
			$this->assertFalse($event->isPropagationStopped());
			$this->assertSame([], $event->getModifiedData());
		}
	}

	/**
	 * A game master may file a question for any player: the asker is still the player's own user.
	 *
	 * @return void
	 */
	public function testAGameMasterFilesAQuestionForAPlayer(): void {
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => self::PLAYER, 'subject' => 'Lost and found', 'askerUid' => 'joris']));

		$this->listener()->handle($event);

		$this->assertFalse($event->isPropagationStopped());
		$this->assertSame(['askerUid' => 'anna', 'ownerRef' => self::PLAYER], $event->getModifiedData());
	}

	/**
	 * An import (nobody signed in) keeps the asker the player names, without a refusal.
	 *
	 * @return void
	 */
	public function testAnImportedQuestionTakesItsAskerFromThePlayer(): void {
		$this->signedIn = null;
		$event = new ObjectCreatingEvent(new HelpObjectEntity(self::QUESTION_SCHEMA, ['player' => self::PLAYER, 'subject' => 'Bow?']));

		$this->listener()->handle($event);

		$this->assertFalse($event->isPropagationStopped());
		$this->assertSame(['askerUid' => 'anna', 'ownerRef' => self::PLAYER], $event->getModifiedData());
	}

	/**
	 * A message naming an unknown question, or none, is refused as having no question.
	 *
	 * @return void
	 */
	public function testAMessageWithoutAReadableQuestionIsRefused(): void {
		foreach (['99999999-9999-4999-8999-999999999999', '', null] as $question) {
			$creating = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => $question, 'body' => 'Hello?']));

			$this->listener()->handle($creating);

			$this->assertTrue($creating->isPropagationStopped());
			$this->assertSame([422, 'larpinq.help.no_question'], [$creating->getErrors()['status'], $creating->getErrors()['code']]);
			$this->assertSame([], $creating->getModifiedData());
		}
	}

	/**
	 * A message naming its question by an object reference is stamped like any other.
	 *
	 * @return void
	 */
	public function testAMessageNamingItsQuestionByObjectIsStamped(): void {
		$creating = new ObjectCreatingEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => ['id' => self::QUESTION], 'body' => 'Noted.']));

		$this->listener()->handle($creating);

		$modified = $creating->getModifiedData();
		$this->assertSame(['joris', true, 'anna', self::PLAYER], [$modified['author'], $modified['fromOrganiser'], $modified['askerUid'], $modified['ownerRef']]);
	}

	/**
	 * A stored message whose question is gone moves nothing.
	 *
	 * @return void
	 */
	public function testAStoredMessageWithoutItsQuestionMovesNothing(): void {
		$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => '99999999-9999-4999-8999-999999999999', 'fromOrganiser' => true])));

		$this->assertSame([], $this->saved);
	}

	/**
	 * A message that moves no status still records when the thread last moved, and keeps the question's fields.
	 *
	 * @return void
	 */
	public function testAMessageThatKeepsTheStatusStillRecordsTheLastMessage(): void {
		$this->question['status'] = 'answered';
		$this->question['id'] = 'internal-id';

		$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'fromOrganiser' => true, 'createdAt' => '2026-09-22T10:00:00+00:00'])));

		$this->assertCount(1, $this->saved);
		[$type, $data, $uuid] = $this->saved[0];
		$this->assertSame(['helpquestion', self::QUESTION], [$type, $uuid]);
		$this->assertSame('answered', $data['status']);
		$this->assertSame('2026-09-22T10:00:00+00:00', $data['lastMessageAt']);
		$this->assertArrayNotHasKey('id', $data);
		$this->assertArrayNotHasKey('@self', $data);
	}

	/**
	 * A message stored without a time stamps the thread with now.
	 *
	 * @return void
	 */
	public function testAMessageWithoutATimeStampsTheThreadWithNow(): void {
		$before = time();

		$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'fromOrganiser' => true])));

		$this->assertSame('answered', $this->saved[0][1]['status']);
		$this->assertGreaterThanOrEqual($before, strtotime($this->saved[0][1]['lastMessageAt']));
	}

	/**
	 * Only a real `true` counts as a game master's message when the thread moves on.
	 *
	 * @return void
	 */
	public function testOnlyATrueFromOrganiserAnswersTheQuestion(): void {
		foreach (['1', 1, 'true'] as $flag) {
			$this->saved = [];
			$this->listener()->handle(new ObjectCreatedEvent(new HelpObjectEntity(self::MESSAGE_SCHEMA, ['question' => self::QUESTION, 'fromOrganiser' => $flag, 'createdAt' => '2026-09-22T10:00:00+00:00'])));
			$this->assertSame('open', $this->saved[0][1]['status']);
		}
	}

	/**
	 * The listener over mocks of its collaborators.
	 *
	 * @return HelpMessageListener
	 */
	private function listener(): HelpMessageListener {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				'helpquestion_schema' => self::QUESTION_SCHEMA,
				'helpmessage_schema' => self::MESSAGE_SCHEMA,
				default => $default,
			}
		);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturnCallback(
			function (): ?IUser {
				if ($this->signedIn === null) {
					return null;
				}

				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($this->signedIn);
				return $user;
			}
		);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(static fn (string $uid, string $group): bool => $uid === 'joris' && $group === 'gamemasters');

		$objects = $this->createMock(RegisterObjectFetcher::class);
		$objects->method('getObject')->willReturnCallback(
			fn (string $objectType, string $id): array => match ([$objectType, $id]) {
				['player', self::PLAYER] => ['name' => 'Anna', 'userUid' => 'anna'],
				['helpquestion', self::QUESTION] => $this->question + ['@self' => ['id' => self::QUESTION]],
				default => throw new \RuntimeException('not found'),
			}
		);
		$objects->method('saveObjectWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid = null): array {
				$this->saved[] = [$objectType, $data, $uuid];
				return $data;
			}
		);

		return new HelpMessageListener(objects: $objects, config: $config, caller: new HelpDeskCaller(userSession: $session, groupManager: $groups), logger: $this->createMock(LoggerInterface::class));
	}
}
