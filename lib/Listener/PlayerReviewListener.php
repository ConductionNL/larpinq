<?php

/**
 * Larpinq Player Review Listener
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
 * @spec openspec/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserSession;

/**
 * When a game master marks a new player reviewed (`awaitingReview` goes from
 * true to false), `reviewedBy` is the acting user and `reviewedAt` the time of
 * the write, whatever the client sent. Any other update keeps the stored
 * stamp. A write with nobody signed in (an import, a repair step) keeps what
 * it carries.
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
 * @spec openspec/specs/portal-contribution/spec.md
 */
class PlayerReviewListener implements IEventListener {

	/**
	 * The review stamp.
	 *
	 * @var string[]
	 */
	private const FIELDS = ['reviewedBy', 'reviewedAt'];

	/**
	 * Constructor.
	 *
	 * @param IUserSession $userSession The user session.
	 * @param IAppConfig $config Config (player schema id).
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IAppConfig $config,
	) {
	}//end __construct()

	/**
	 * Handle an OpenRegister pre-update event.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	public function handle(Event $event): void {
		if (($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) === false) {
			return;
		}

		$user = $this->actingReviewer(event: $event);
		if ($user === null) {
			return;
		}

		$before = (array)$event->getOldObject()->getObject();
		$stamp = WriteStamp::stored(object: $before, fields: self::FIELDS);
		if ($this->marksReviewed(before: $before, event: $event) === true) {
			$stamp = WriteStamp::now(fields: self::FIELDS, uid: $user->getUID());
		}

		$this->mergeStamp(event: $event, stamp: $stamp);
	}//end handle()

	/**
	 * The signed-in user, when the update is of a stored player.
	 *
	 * @param ObjectUpdatingEvent $event The pre-update event.
	 *
	 * @return IUser|null The acting user, or null when the update is left alone.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument   OpenRegister event/entity classes are optional dependencies.
	 */
	private function actingReviewer(ObjectUpdatingEvent $event): ?IUser {
		// @phpstan-ignore-next-line
		$old = $event->getOldObject();
		$user = $this->userSession->getUser();
		// @phpstan-ignore-next-line
		if ($old === null || $user === null || $this->isPlayer(entity: $event->getNewObject()) === false) {
			return null;
		}

		return $user;
	}//end actingReviewer()

	/**
	 * Whether the update takes the player out of review.
	 *
	 * @param array<string,mixed> $before The stored player.
	 * @param ObjectUpdatingEvent $event  The pre-update event carrying the player as written.
	 *
	 * @return bool True when `awaitingReview` goes from true to false.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister event/entity classes are optional dependencies.
	 */
	private function marksReviewed(array $before, ObjectUpdatingEvent $event): bool {
		// @phpstan-ignore-next-line
		$after = (array)$event->getNewObject()->getObject();

		return ($before['awaitingReview'] ?? false) === true && ($after['awaitingReview'] ?? null) === false;
	}//end marksReviewed()

	/**
	 * Merge the stamp into what other listeners changed, when there is one.
	 *
	 * @param ObjectUpdatingEvent $event The pre-update event.
	 * @param array<string,mixed> $stamp The review stamp.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister event classes are optional dependencies.
	 * @psalm-suppress MixedArgument   OpenRegister event classes are optional dependencies.
	 */
	private function mergeStamp(ObjectUpdatingEvent $event, array $stamp): void {
		if ($stamp !== []) {
			// @phpstan-ignore-next-line
			$event->setModifiedData(array_merge($event->getModifiedData(), $stamp));
		}
	}//end mergeStamp()

	/**
	 * Whether an entity is a player.
	 *
	 * @param object $entity The OpenRegister object entity.
	 *
	 * @return bool True for the player schema.
	 */
	private function isPlayer(object $entity): bool {
		return (new ConfiguredSchema(config: $this->config, key: 'player_schema'))->matches(entity: $entity);
	}//end isPlayer()
}//end class
