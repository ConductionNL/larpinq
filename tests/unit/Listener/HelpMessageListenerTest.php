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
	private array $question = ['subject' => 'Can I bring a real bow?', 'askerUid' => 'anna', 'status' => 'open', 'player' => self::PLAYER];

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

		return new HelpMessageListener(objects: $objects, config: $config, userSession: $session, groupManager: $groups, logger: $this->createMock(LoggerInterface::class));
	}
}
