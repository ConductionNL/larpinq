<?php

/**
 * Larpinq Help Message Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * The help desk thread.
 *
 * Before a question is stored, its asker (`askerUid`) and owner reference
 * (`ownerRef`) are copied from the player it names. Before a message is
 * stored, it is refused on a closed question or from someone who is neither
 * a game master nor the asker; otherwise its author, `fromOrganiser`, the
 * asker and the time are filled in by the server. After a message is stored,
 * the question follows the thread: a game master's message marks an open
 * question answered, a reply from the asker opens an answered question
 * again, and `lastMessageAt` moves to the message.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister event classes are an optional dependency.
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
 */
class HelpMessageListener implements IEventListener {

	/**
	 * The group whose members answer questions.
	 */
	public const ORGANISERS = 'gamemasters';

	/**
	 * The status a message moves its question to, keyed by whether a game
	 * master wrote it (1) or the asker (0) and the question's status.
	 *
	 * @var array<string, string>
	 */
	private const NEXT_STATUS = [
		'1:open'     => 'answered',
		'0:answered' => 'open',
	];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objects Reads players and questions, and writes the question's status.
	 * @param IAppConfig            $config  Config (question and message schema ids).
	 * @param HelpDeskCaller        $caller  The acting user, and whether they are a game master.
	 * @param LoggerInterface       $logger  The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objects,
		private readonly IAppConfig $config,
		private readonly HelpDeskCaller $caller,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle an OpenRegister pre-create or post-create event.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
	 */
	public function handle(Event $event): void {
		if ($event instanceof ObjectCreatingEvent) {
			// @phpstan-ignore-next-line
			$this->onCreating(event: $event, entity: $event->getObject());
			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatedEvent) {
			// @phpstan-ignore-next-line
			$entity = $event->getObject();
			if ($this->isOf(entity: $entity, key: 'helpmessage_schema') === true) {
				$this->followThread(message: (array)$entity->getObject());
			}
		}
	}//end handle()

	/**
	 * Before a question or a message is stored: prepare or refuse it.
	 *
	 * @param Event  $event  The creating event.
	 * @param object $entity The object being created.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/help-desk/spec.md
	 */
	private function onCreating(Event $event, object $entity): void {
		if ($this->isOf(entity: $entity, key: 'helpquestion_schema') === true) {
			$this->prepareQuestion(event: $event, question: (array)$entity->getObject());
		} else if ($this->isOf(entity: $entity, key: 'helpmessage_schema') === true) {
			$this->prepareMessage(event: $event, message: (array)$entity->getObject());
		}
	}//end onCreating()

	/**
	 * Name the asker of a new question, refusing a question asked as someone else.
	 *
	 * A signed-in player who is not a game master asks as their own player: a
	 * question naming another player is refused, and one naming no readable
	 * player takes the signed-in user as its asker, whatever asker fields it
	 * carried. Game masters may ask on a player's behalf; writes without a
	 * signed-in user (the portal, an import) keep what they carry.
	 *
	 * @param Event               $event    The creating event.
	 * @param array<string,mixed> $question The question as written.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-players-ask-questions-req-chd-001
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) PreWriteEvent is a pure helper over the event it is handed.
	 */
	private function prepareQuestion(Event $event, array $question): void {
		$asker = $this->askerOf(question: $question);
		$uid   = $this->caller->uid();
		if ($uid === '' || $this->caller->isOrganiser(uid: $uid) === true) {
			PreWriteEvent::mergeModified(event: $event, data: $asker);
			return;
		}

		$refusal = self::askRefusal(asker: $asker, uid: $uid);
		if ($refusal !== null) {
			PreWriteEvent::refuseOnErrors(event: $event, errors: $refusal);
			return;
		}

		PreWriteEvent::mergeModified(event: $event, data: self::ownAsker(asker: $asker, uid: $uid));
	}//end prepareQuestion()

	/**
	 * The asker fields of a signed-in player's own question: those of the
	 * player it names, else the signed-in user with no owner reference.
	 *
	 * @param array<string,string> $asker The asker fields taken from the named player; empty when none was read.
	 * @param string               $uid   The signed-in player's user id.
	 *
	 * @return array<string,string>
	 */
	private static function ownAsker(array $asker, string $uid): array {
		if ($asker === []) {
			return ['askerUid' => $uid, 'ownerRef' => ''];
		}

		return $asker;
	}//end ownAsker()

	/**
	 * Why a signed-in player may not ask this question, or null when they may.
	 *
	 * @param array<string,string> $asker The asker fields taken from the named player; empty when none was read.
	 * @param string               $uid   The signed-in player's user id.
	 *
	 * @return array{status:int,code:string,message:string}|null The refusal, or null.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-players-ask-questions-req-chd-001
	 */
	public static function askRefusal(array $asker, string $uid): ?array {
		if ($asker !== [] && ($asker['askerUid'] ?? '') !== $uid) {
			return ['status' => 403, 'code' => 'larpinq.help.not_own_player', 'message' => 'You can only ask a question as your own player.'];
		}

		return null;
	}//end askRefusal()

	/**
	 * The asker and owner reference of a question, copied from the player it names.
	 *
	 * A question asked through the portal names no player: the portal stamps
	 * `ownerRef` with the linked player's uuid instead, so that player is
	 * taken, and named on the question (REQ-CHD-004).
	 *
	 * @param array<string,mixed> $question The question as written.
	 *
	 * @return array<string,string> The fields to set; empty when the player cannot be read.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-portal-players-use-the-help-desk-too-req-chd-004
	 */
	private function askerOf(array $question): array {
		$playerId = self::idOf(value: ($question['player'] ?? null));
		$fields   = [];
		if ($playerId === '') {
			$playerId = self::idOf(value: ($question['ownerRef'] ?? null));
			$fields['player'] = $playerId;
		}

		$player = $this->playerOf(playerId: $playerId);
		if ($player === null) {
			return [];
		}

		return $fields + ['askerUid' => (string)($player['userUid'] ?? ''), 'ownerRef' => $playerId];
	}//end askerOf()

	/**
	 * The player a question names, or null when it names none or the player cannot be read.
	 *
	 * @param string $playerId The player's uuid, or ''.
	 *
	 * @return array<string,mixed>|null
	 */
	private function playerOf(string $playerId): ?array {
		if ($playerId === '') {
			return null;
		}

		try {
			return $this->objects->getObject(objectType: 'player', id: $playerId);
		} catch (\Throwable $e) {
			$this->logger->info('Larpinq: the player of a help question could not be read', ['player' => $playerId, 'exception' => $e->getMessage()]);
			return null;
		}
	}//end playerOf()

	/**
	 * Refuse a message on a closed question or from an outsider; otherwise fill in what the server decides.
	 *
	 * @param Event               $event   The pre-create event.
	 * @param array<string,mixed> $message The message as written.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) PreWriteEvent is a pure helper over the event it is handed.
	 */
	private function prepareMessage(Event $event, array $message): void {
		$question  = $this->questionOf(message: $message);
		$uid       = $this->caller->uid();
		$organiser = $this->caller->isOrganiser(uid: $uid);
		$refusal   = self::refusal(question: $question, uid: $uid, organiser: $organiser);
		if ($refusal !== null) {
			PreWriteEvent::refuseOnErrors(event: $event, errors: $refusal);
			return;
		}

		if ($uid === '') {
			// An import or a repair step keeps what it carries.
			return;
		}

		PreWriteEvent::mergeModified(event: $event, data: ['author' => $uid, 'fromOrganiser' => $organiser] + self::threadOf(question: (array)$question));
	}//end prepareMessage()

	/**
	 * The thread fields a new message takes from its question: the asker, the
	 * owner reference and the time it is written.
	 *
	 * @param array<string,mixed> $question The question.
	 *
	 * @return array<string,string>
	 * @spec openspec/specs/help-desk/spec.md
	 */
	private static function threadOf(array $question): array {
		return [
			'askerUid'  => (string)($question['askerUid'] ?? ''),
			'ownerRef'  => (string)($question['ownerRef'] ?? ''),
			'createdAt' => date(DATE_ATOM),
		];
	}//end threadOf()

	/**
	 * Why a message may not be stored, or null when it may.
	 *
	 * @param array<string,mixed>|null $question  The question, or null when it cannot be read.
	 * @param string                   $uid       The acting user, empty for a system write.
	 * @param bool                     $organiser Whether the acting user is a game master.
	 *
	 * @return array{status:int,code:string,message:string}|null
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
	 */
	public static function refusal(?array $question, string $uid, bool $organiser): ?array {
		if ($question === null) {
			return ['status' => 422, 'code' => 'larpinq.help.no_question', 'message' => 'A message needs the question it answers.'];
		}

		if (($question['status'] ?? '') === 'closed') {
			return ['status' => 422, 'code' => 'larpinq.help.closed', 'message' => 'This question is closed and takes no new messages.'];
		}

		return self::writerRefusal(question: $question, uid: $uid, organiser: $organiser);
	}//end refusal()

	/**
	 * Refuse a signed-in writer who is neither a game master nor the asker.
	 *
	 * @param array<string,mixed> $question  The question.
	 * @param string              $uid       The acting user, empty for a system write.
	 * @param bool                $organiser Whether the acting user is a game master.
	 *
	 * @return array{status:int,code:string,message:string}|null
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
	 */
	private static function writerRefusal(array $question, string $uid, bool $organiser): ?array {
		if ($uid !== '' && $organiser === false && $uid !== (string)($question['askerUid'] ?? '')) {
			return ['status' => 403, 'code' => 'larpinq.help.not_asker', 'message' => 'Only the asker and the game masters write in this thread.'];
		}

		return null;
	}//end writerRefusal()

	/**
	 * After a message is stored, move the question's status and last-message time.
	 *
	 * @param array<string,mixed> $message The stored message.
	 *
	 * @return void
	 */
	private function followThread(array $message): void {
		$question = $this->questionOf(message: $message);
		if ($question === null) {
			return;
		}

		$id = self::idOf(value: ($message['question'] ?? null));
		try {
			$data = array_merge(self::fieldsOf(question: $question), self::followUp(question: $question, message: $message));
			$this->objects->saveObjectWithAppAuthority(objectType: 'helpquestion', data: $data, uuid: $id);
		} catch (\Throwable $e) {
			$this->logger->warning('Larpinq: a help question did not follow its thread', ['question' => $id, 'exception' => $e->getMessage()]);
		}
	}//end followThread()

	/**
	 * The fields a stored message changes on its question: the last-message
	 * time, and the status when the message moves it.
	 *
	 * @param array<string,mixed> $question The question.
	 * @param array<string,mixed> $message  The stored message.
	 *
	 * @return array<string,string>
	 * @spec openspec/specs/help-desk/spec.md
	 */
	private static function followUp(array $question, array $message): array {
		$next = self::statusAfter(status: (string)($question['status'] ?? ''), fromOrganiser: (($message['fromOrganiser'] ?? false) === true));
		$data = ['lastMessageAt' => (string)($message['createdAt'] ?? date(DATE_ATOM))];
		if ($next !== null) {
			$data['status'] = $next;
		}

		return $data;
	}//end followUp()

	/**
	 * The status a message leaves its question in, or null when it stays.
	 *
	 * @param string $status        The question's status.
	 * @param bool   $fromOrganiser Whether a game master wrote the message.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
	 */
	public static function statusAfter(string $status, bool $fromOrganiser): ?string {
		return (self::NEXT_STATUS[(int)$fromOrganiser . ':' . $status] ?? null);
	}//end statusAfter()

	/**
	 * The question a message belongs to, or null when it cannot be read.
	 *
	 * @param array<string,mixed> $message The message.
	 *
	 * @return array<string,mixed>|null
	 */
	private function questionOf(array $message): ?array {
		$id = self::idOf(value: ($message['question'] ?? null));
		if ($id === '') {
			return null;
		}

		try {
			return $this->objects->getObject(objectType: 'helpquestion', id: $id);
		} catch (\Throwable $e) {
			$this->logger->info('Larpinq: the question of a help message could not be read', ['question' => $id, 'exception' => $e->getMessage()]);
			return null;
		}
	}//end questionOf()

	/**
	 * A question's own fields, without the `@self` metadata block.
	 *
	 * @param array<string,mixed> $question The question as read.
	 *
	 * @return array<string,mixed>
	 */
	private static function fieldsOf(array $question): array {
		unset($question['@self'], $question['id']);
		return $question;
	}//end fieldsOf()

	/**
	 * A relation's id: the string itself, or the `id` / `uuid` of an expanded object.
	 *
	 * @param mixed $value The relation.
	 *
	 * @return string
	 */
	private static function idOf(mixed $value): string {
		if (is_array($value) === true) {
			$value = ($value['id'] ?? ($value['uuid'] ?? ''));
		}

		if (is_string($value) === false) {
			return '';
		}

		return trim($value);
	}//end idOf()

	/**
	 * Whether an entity is of the schema configured under a key.
	 *
	 * @param object $entity The OpenRegister object entity.
	 * @param string $key    The config key.
	 *
	 * @return bool
	 */
	private function isOf(object $entity, string $key): bool {
		return (new ConfiguredSchema(config: $this->config, key: $key))->matches(entity: $entity);
	}//end isOf()
}//end class
