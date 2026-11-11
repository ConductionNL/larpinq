<?php

/**
 * Larpinq Pre-Write Event
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCP\EventDispatcher\Event;

/**
 * Reading and refusing an OpenRegister pre-write event (ObjectCreatingEvent or
 * ObjectUpdatingEvent), the same way for every write rule.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
final class PreWriteEvent {
	/**
	 * The new entity and the stored one (null on a create) of a pre-write event.
	 *
	 * ObjectUpdatingEvent has no getObject(), so the accessor follows the class.
	 *
	 * @param Event $event The Creating or Updating event.
	 *
	 * @return array{0:object,1:object|null} The new and the old entity.
	 *
	 * @psalm-suppress UndefinedMethod The OpenRegister event accessors are resolved at runtime.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public static function entitiesOf(Event $event): array {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) {
			// @phpstan-ignore-next-line
			return [$event->getNewObject(), $event->getOldObject()];
		}

		// @phpstan-ignore-next-line
		return [$event->getObject(), null];
	}//end entitiesOf()

	/**
	 * Stop the write and hand OpenRegister the errors, when a rule found any.
	 *
	 * @param Event $event The pre-write event.
	 * @param array<string, mixed>|null $errors The errors, or null to let the write pass.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public static function refuseOnErrors(Event $event, ?array $errors): void {
		if ($errors !== null) {
			$event->stopPropagation();
			// @phpstan-ignore-next-line
			$event->setErrors($errors);
		}
	}//end refuseOnErrors()

	/**
	 * The object's data with its uuid as `id`.
	 *
	 * @param object $entity The OpenRegister object.
	 *
	 * @return array<string, mixed> The data.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public static function dataOf(object $entity): array {
		$data = (array)$entity->getObject();
		if (is_callable([$entity, 'getUuid']) === true && (string)$entity->getUuid() !== '') {
			$data['id'] = (string)$entity->getUuid();
		}

		return $data;
	}//end dataOf()
}//end class
