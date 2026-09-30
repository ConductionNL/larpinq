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
 * @spec openspec/changes/players-self-signup/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
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
 * @spec openspec/changes/players-self-signup/specs/portal-contribution/spec.md
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
	 * @spec openspec/changes/players-self-signup/specs/portal-contribution/spec.md
	 */
	public function handle(Event $event): void {
		if (($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) === false) {
			return;
		}

		$old = $event->getOldObject();
		$user = $this->userSession->getUser();
		if ($old === null || $user === null || $this->isPlayer(entity: $event->getNewObject()) === false) {
			return;
		}

		$before = (array)$old->getObject();
		$after = (array)$event->getNewObject()->getObject();
		$stamp = array_filter(
			array_intersect_key($before, array_flip(self::FIELDS)),
			static fn ($value): bool => $value !== null && $value !== ''
		);
		if (($before['awaitingReview'] ?? false) === true && ($after['awaitingReview'] ?? null) === false) {
			$stamp = [
				'reviewedBy' => $user->getUID(),
				'reviewedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
			];
		}

		if ($stamp !== []) {
			// @phpstan-ignore-next-line
			$event->setModifiedData(array_merge($event->getModifiedData(), $stamp));
		}
	}//end handle()

	/**
	 * Whether an entity is a player.
	 *
	 * @param object $entity The OpenRegister object entity.
	 *
	 * @return bool True for the player schema.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function isPlayer(object $entity): bool {
		$schema = $this->config->getValueString(Application::APP_ID, 'player_schema', '');

		return $schema !== ''
			&& is_callable([$entity, 'getSchema']) === true
			&& (string)$entity->getSchema() === $schema;
	}//end isPlayer()
}//end class
