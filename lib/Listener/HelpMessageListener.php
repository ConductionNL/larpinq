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

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
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
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objects      Reads players and questions, and writes the question's status.
	 * @param IAppConfig            $config       Config (question and message schema ids).
	 * @param IUserSession          $userSession  The acting user.
	 * @param IGroupManager         $groupManager Tells game masters apart.
	 * @param LoggerInterface       $logger       The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objects,
		private readonly IAppConfig $config,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
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
		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) {
			// @phpstan-ignore-next-line
			$entity = $event->getObject();
			if ($this->isOf(entity: $entity, key: 'helpquestion_schema') === true) {
				$this->merge(event: $event, data: $this->askerOf(question: (array)$entity->getObject()));
			} else if ($this->isOf(entity: $entity, key: 'helpmessage_schema') === true) {
				$this->prepareMessage(event: $event, message: (array)$entity->getObject());
			}

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
	 * The asker and owner reference of a question, copied from the player it names.
	 *
	 * @param array<string,mixed> $question The question as written.
	 *
	 * @return array<string,string> The fields to set; empty when the player cannot be read.
	 */
	private function askerOf(array $question): array {
		$playerId = self::idOf(value: ($question['player'] ?? null));
		if ($playerId === '') {
			return [];
		}

		try {
			$player = $this->objects->getObject(objectType: 'player', id: $playerId);
		} catch (\Throwable $e) {
			$this->logger->info('Larpinq: the player of a help question could not be read', ['player' => $playerId, 'exception' => $e->getMessage()]);
			return [];
		}

		return ['askerUid' => (string)($player['userUid'] ?? ''), 'ownerRef' => $playerId];
	}//end askerOf()

	/**
	 * Refuse a message on a closed question or from an outsider; otherwise fill in what the server decides.
	 *
	 * @param Event               $event   The pre-create event.
	 * @param array<string,mixed> $message The message as written.
	 *
	 * @return void
	 */
	private function prepareMessage(Event $event, array $message): void {
		$question = $this->questionOf(message: $message);
		$user     = $this->userSession->getUser();
		$uid      = '';
		if ($user !== null) {
			$uid = $user->getUID();
		}

		$organiser = ($uid !== '' && $this->groupManager->isInGroup($uid, self::ORGANISERS) === true);
		$refusal   = self::refusal(question: $question, uid: $uid, organiser: $organiser);
		if ($refusal !== null) {
			PreWriteEvent::refuseOnErrors(event: $event, errors: $refusal);
			return;
		}

		if ($uid === '') {
			// An import or a repair step keeps what it carries.
			return;
		}

		$this->merge(
			event: $event,
			data: [
				'author'        => $uid,
				'fromOrganiser' => $organiser,
				'askerUid'      => (string)($question['askerUid'] ?? ''),
				'createdAt'     => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
			]
		);
	}//end prepareMessage()

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

		if ($uid !== '' && $organiser === false && $uid !== (string)($question['askerUid'] ?? '')) {
			return ['status' => 403, 'code' => 'larpinq.help.not_asker', 'message' => 'Only the asker and the game masters write in this thread.'];
		}

		return null;
	}//end refusal()

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

		$next = self::statusAfter(status: (string)($question['status'] ?? ''), fromOrganiser: (($message['fromOrganiser'] ?? false) === true));
		$data = ['lastMessageAt' => (string)($message['createdAt'] ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM))];
		if ($next !== null) {
			$data['status'] = $next;
		}

		$id = self::idOf(value: ($message['question'] ?? null));
		try {
			$this->objects->saveObjectWithAppAuthority(objectType: 'helpquestion', data: array_merge(self::fieldsOf(question: $question), $data), uuid: $id);
		} catch (\Throwable $e) {
			$this->logger->warning('Larpinq: a help question did not follow its thread', ['question' => $id, 'exception' => $e->getMessage()]);
		}
	}//end followThread()

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
		if ($fromOrganiser === true && $status === 'open') {
			return 'answered';
		}

		if ($fromOrganiser === false && $status === 'answered') {
			return 'open';
		}

		return null;
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
	 * Merge fields into what other listeners changed.
	 *
	 * @param Event               $event The pre-create event.
	 * @param array<string,mixed> $data  The fields.
	 *
	 * @return void
	 */
	private function merge(Event $event, array $data): void {
		if ($data !== []) {
			// @phpstan-ignore-next-line
			$event->setModifiedData(array_merge($event->getModifiedData(), $data));
		}
	}//end merge()

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
