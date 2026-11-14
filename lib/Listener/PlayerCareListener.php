<?php

/**
 * Larpinq Player Care Listener
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
 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\EventCareRecords;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;

/**
 * When a player's care details change, the copies for their accepted
 * registrations follow, for events that have not started yet (REQ-PCE-002).
 * Any other change to a player writes nothing.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */
class PlayerCareListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $config Holds the player schema id.
	 * @param EventCareRecords $care Writes the copies.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IAppConfig $config,
		private readonly EventCareRecords $care,
	) {
	}//end __construct()

	/**
	 * Refresh the copies after a player's care details changed.
	 *
	 * @param Event $event OpenRegister's ObjectUpdatedEvent.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
	 */
	public function handle(Event $event): void {
		if (($event instanceof \OCA\OpenRegister\Event\ObjectUpdatedEvent) === false) {
			return;
		}

		$new = $event->getNewObject();
		if ($this->isPlayer(entity: $new) === false) {
			return;
		}

		$before = (array)($event->getOldObject()?->getObject() ?? []);
		$after = (array)$new->getObject();
		if (self::careChanged(before: $before, after: $after) === false) {
			return;
		}

		$this->care->refreshForPlayer(playerId: (string)$new->getUuid());
	}//end handle()

	/**
	 * Whether any care field differs between two versions of a player.
	 *
	 * @param array<string, mixed> $before The stored player.
	 * @param array<string, mixed> $after The updated player.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
	 */
	public static function careChanged(array $before, array $after): bool {
		foreach (EventCareRecords::FIELDS as $field) {
			if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
				return true;
			}
		}

		return false;
	}//end careChanged()

	/**
	 * Whether the object is a player.
	 *
	 * @param object $entity The written object.
	 *
	 * @return bool
	 */
	private function isPlayer(object $entity): bool {
		$schema = $this->config->getValueString('larpinq', 'player_schema', '');
		if ($schema === '' || is_callable([$entity, 'getSchema']) === false) {
			return false;
		}

		return (string)$entity->getSchema() === $schema;
	}//end isPlayer()
}//end class
