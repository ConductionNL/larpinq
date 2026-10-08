<?php

/**
 * CharacterStatusListener for Larpinq
 *
 * Vetoes a character write that adds an event while the character is retired
 * or dead (characters-status-and-bulk-edit D2). Listens for OpenRegister's
 * vetoable pre-write events, scoped to the character schema.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\CharacterStatusGuard;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;

/**
 * Keeps retired and dead characters out of new events, on every write path.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister event classes are an optional dependency.
 *
 * @spec openspec/specs/character-management/spec.md
 */
class CharacterStatusListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param CharacterStatusGuard $guard The rule.
	 * @param IAppConfig $config Config (character schema id).
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly CharacterStatusGuard $guard,
		private readonly IAppConfig $config,
	) {
	}//end __construct()

	/**
	 * Handle an OpenRegister pre-write event.
	 *
	 * @param Event $event The dispatched event (Creating or Updating).
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function handle(Event $event): void {
		[$entity, $old] = $this->entitiesOf(event: $event);
		if ($entity === null) {
			return;
		}

		$schema = $this->config->getValueString(Application::APP_ID, 'character_schema', '');
		if ($schema === '' || is_callable([$entity, 'getSchema']) === false || (string)$entity->getSchema() !== $schema) {
			return;
		}

		$previous = [];
		if ($old !== null) {
			$previous = (array)$old->getObject();
		}

		$errors = $this->guard->check(candidate: (array)$entity->getObject(), old: $previous);
		if ($errors !== null) {
			$event->stopPropagation();
			// @phpstan-ignore-next-line
			$event->setErrors($errors);
		}
	}//end handle()

	/**
	 * The new entity and the stored one (null on a create) of a pre-write
	 * event, or two nulls for any other event. ObjectUpdatingEvent has no
	 * getObject(), so the accessor follows the class.
	 *
	 * @param Event $event The event.
	 *
	 * @return array{0: object|null, 1: object|null} The new and the old entity.
	 *
	 * @psalm-suppress MixedAssignment OpenRegister event classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod The OpenRegister event accessors are resolved at runtime.
	 */
	private function entitiesOf(Event $event): array {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) {
			// @phpstan-ignore-next-line
			return [$event->getNewObject(), $event->getOldObject()];
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) {
			// @phpstan-ignore-next-line
			return [$event->getObject(), null];
		}

		return [null, null];
	}//end entitiesOf()
}//end class
