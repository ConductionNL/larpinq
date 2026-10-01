<?php

/**
 * Larpinq Registration Listener
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\RegistrationCharacterCheck;
use OCA\Larpinq\Service\RegistrationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Keeps registrations whole on every write.
 *
 * Before a registration is stored it gets its status from the capacity and
 * the approval setting, and a character a player picks is checked. After it
 * is stored the event's participants follow it and a freed place goes to the
 * waiting list.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister event classes are optional dependencies.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $config Config (the registration schema id).
	 * @param RegistrationService $service Capacity, waiting list and participants.
	 * @param RegistrationCharacterCheck $characterCheck Whether a player may bring a character.
	 * @param IUserSession $session Who is writing.
	 * @param IL10N $l10n Translations for refusals.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IAppConfig $config,
		private readonly RegistrationService $service,
		private readonly RegistrationCharacterCheck $characterCheck,
		private readonly IUserSession $session,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle an OpenRegister object event for a registration.
	 *
	 * The post-write work runs inline: the per-event lock taken before the
	 * write must be released in the same request, and the waiting list must
	 * move up under that same decision, or two background runs could both
	 * give away one freed place.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @listener-placement inline correctness: the capacity lock taken in the pre-write handler is
	 * released here, in the same request, and a freed place goes to the waiting list under the same
	 * per-event decision; deferring either would hold every sign-up of the event or double-book a place.
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) {
			$this->beforeWrite(event: $event, entity: $event->getObject(), old: null);
			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) {
			$this->beforeWrite(event: $event, entity: $event->getNewObject(), old: $event->getOldObject());
			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatedEvent && $this->isRegistration(entity: $event->getObject()) === true) {
			$this->service->afterWrite(new: $this->dataOf(entity: $event->getObject()), old: null);
			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatedEvent && $this->isRegistration(entity: $event->getNewObject()) === true) {
			$old = $event->getOldObject();
			$before = null;
			if ($old !== null) {
				$before = $this->dataOf(entity: $old);
			}

			$this->service->afterWrite(new: $this->dataOf(entity: $event->getNewObject()), old: $before);
		}
	}//end handle()

	/**
	 * Decide the status and check the character before a registration is stored.
	 *
	 * @param object $event The Creating or Updating event.
	 * @param object $entity The registration as it will be stored.
	 * @param object|null $old The stored registration, or null on create.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister event/entity classes are optional dependencies.
	 */
	private function beforeWrite(object $event, object $entity, ?object $old): void {
		if ($this->isRegistration(entity: $entity) === false) {
			return;
		}

		$new = $this->dataOf(entity: $entity);
		$before = [];
		if ($old !== null) {
			$before = $this->dataOf(entity: $old);
		}

		$character = (string)($new['character'] ?? '');
		if ($character !== '' && $character !== (string)($before['character'] ?? '')) {
			$refusal = $this->characterCheck->refusal(registration: $new);
			if ($refusal !== null) {
				$event->stopPropagation();
				// @phpstan-ignore-next-line
				$event->setErrors(['message' => $this->l10n->t($refusal)]);
				return;
			}
		}

		$changes = [];
		if ($old === null) {
			$changes = $this->service->beforeCreate(registration: $new);
		}

		if ($old !== null) {
			$changes = $this->service->beforeUpdate(new: $new, old: $before, actingUid: $this->actingUid());
		}

		if ($changes !== []) {
			// @phpstan-ignore-next-line
			$event->setModifiedData(array_merge($event->getModifiedData(), $changes));
		}
	}//end beforeWrite()

	/**
	 * Whether the object is a registration.
	 *
	 * @param object $entity The OpenRegister object.
	 *
	 * @return bool True for a registration.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function isRegistration(object $entity): bool {
		$schema = $this->config->getValueString('larpinq', 'registration_schema', '');
		if ($schema === '' || is_callable([$entity, 'getSchema']) === false) {
			return false;
		}

		return (string)$entity->getSchema() === $schema;
	}//end isRegistration()

	/**
	 * The object's data with its uuid as `id`.
	 *
	 * @param object $entity The OpenRegister object.
	 *
	 * @return array<string, mixed> The data.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function dataOf(object $entity): array {
		$data = (array)$entity->getObject();
		if (is_callable([$entity, 'getUuid']) === true && (string)$entity->getUuid() !== '') {
			$data['id'] = (string)$entity->getUuid();
		}

		return $data;
	}//end dataOf()

	/**
	 * The signed-in user, or an empty string for a system write.
	 *
	 * @return string The user id.
	 */
	private function actingUid(): string {
		$user = $this->session->getUser();
		if ($user === null) {
			$this->logger->debug('Larpinq: a registration changed without a signed-in user.');
			return '';
		}

		return $user->getUID();
	}//end actingUid()
}//end class
