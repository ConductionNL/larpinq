<?php

/**
 * UniqueHolderListener for Larpinq
 *
 * Server-side veto that keeps a unique item or a unique condition to one
 * character. Listens for OpenRegister's vetoable pre-write events
 * (ObjectCreatingEvent / ObjectUpdatingEvent) on the character, item and
 * condition schemas, and refuses a write that gives a unique item or condition
 * a second holder, naming the character who holds it now.
 *
 * Covers every write path (pages, REST, GraphQL, MCP) because OpenRegister
 * dispatches these events from its central write path.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\UniqueHolderService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Vetoes writes that give a unique item or condition a second holder.
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
 * @spec openspec/specs/rpg-system/spec.md
 */
class UniqueHolderListener implements IEventListener {

	/**
	 * Constructor.
	 *
	 * @param UniqueHolderService $holders The holder lookup.
	 * @param IdListNormaliser $idNormaliser Relation value normaliser.
	 * @param IAppConfig $config Config (schema id resolution).
	 * @param IL10N $l10n Translations for the refusal message.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly UniqueHolderService $holders,
		private readonly IdListNormaliser $idNormaliser,
		private readonly IAppConfig $config,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
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
	 * @psalm-suppress MixedArgument    OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function handle(Event $event): void {
		if (($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) === false
			&& ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) === false
		) {
			return;
		}

		try {
			[$entity, $old] = $this->entitiesOf(event: $event);
			$errors = $this->collectVeto(entity: $entity, old: $old);
			if ($errors !== null) {
				$event->stopPropagation();
				// @phpstan-ignore-next-line
				$event->setErrors($errors);
			}
		} catch (\Throwable $e) {
			// A game-rule validator that throws must not block every write:
			// log and let the write through (same contract as the requirement
			// listener).
			$this->logger->error(
				'Larpinq: unique-holder check errored; allowing the write.',
				['exception' => $e]
			);
		}//end try
	}//end handle()

	/**
	 * The new entity and the stored one (null on a create) of a pre-write event.
	 *
	 * ObjectUpdatingEvent has no getObject(), so the accessor follows the class.
	 *
	 * @param Event $event The Creating or Updating event.
	 *
	 * @return array{0:object,1:object|null} The new and the old entity.
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 */
	private function entitiesOf(Event $event): array {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) {
			// @phpstan-ignore-next-line
			return [$event->getNewObject(), $event->getOldObject()];
		}

		// @phpstan-ignore-next-line
		return [$event->getObject(), null];
	}//end entitiesOf()

	/**
	 * The veto payload for a write, or null when it may pass.
	 *
	 * @param object $entity The new object entity.
	 * @param object|null $old The stored object entity on an update.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function collectVeto(object $entity, ?object $old): ?array {
		$schema = $this->schemaOf(entity: $entity);
		if ($schema === '') {
			return null;
		}

		$new = (array)$entity->getObject();
		$previous = [];
		if ($old !== null) {
			$previous = (array)$old->getObject();
		}

		if ($schema === $this->config->getValueString(Application::APP_ID, 'character_schema', '')) {
			return $this->vetoCharacter(
				character: $new,
				old: $previous,
				characterId: $this->entityId(entity: $entity, data: $new)
			);
		}

		foreach (array_keys(UniqueHolderService::CHARACTER_FIELD) as $kind) {
			if ($schema === $this->config->getValueString(Application::APP_ID, $kind . '_schema', '')) {
				return $this->vetoHeldObject(
					kind: $kind,
					object: $new,
					old: $previous,
					objectId: $this->entityId(entity: $entity, data: $new),
					isCreate: $old === null
				);
			}
		}

		return null;
	}//end collectVeto()

	/**
	 * Refuse a character write that adds a unique item or condition held elsewhere.
	 *
	 * @param array<string,mixed> $character The candidate character.
	 * @param array<string,mixed> $old The stored character ([] on create).
	 * @param string $characterId The character's id ('' on a create without one).
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 */
	private function vetoCharacter(array $character, array $old, string $characterId): ?array {
		foreach (UniqueHolderService::CHARACTER_FIELD as $kind => $field) {
			$added = array_diff(
				$this->idNormaliser->normalise(value: $character[$field] ?? []),
				$this->idNormaliser->normalise(value: $old[$field] ?? [])
			);

			foreach ($added as $objectId) {
				$object = $this->holders->readObject(kind: $kind, id: $objectId);
				if ($object === null || $this->holders->isUnique(kind: $kind, object: $object) === false) {
					continue;
				}

				$exceptCharacter = null;
				if ($characterId !== '') {
					$exceptCharacter = $characterId;
				}

				$holders = $this->holders->otherHolders(
					kind: $kind,
					id: $objectId,
					objectSide: $this->idNormaliser->normalise(value: $object['characters'] ?? []),
					exceptCharacter: $exceptCharacter,
					max: 1
				);
				if ($holders === []) {
					continue;
				}

				$name = $this->nameOf(object: $object, fallback: $objectId);
				$holder = $holders[0]['name'];
				$code = 'unique_' . $kind . '_held';
				$message = $this->l10n->t('%1$s is already held by %2$s. Remove it there first.', [$name, $holder]);
				if ($kind === 'condition') {
					$message = $this->l10n->t('%1$s is already on %2$s. Remove it there first.', [$name, $holder]);
				}

				return [
					'message' => $message,
					$field => [
						[
							'code' => $code,
							$kind => $objectId,
							'heldBy' => $holder,
							'message' => $message,
						],
					],
				];
			}//end foreach
		}//end foreach

		return null;
	}//end vetoCharacter()

	/**
	 * Refuse an item or condition write that leaves a unique object with two holders.
	 *
	 * Checked only when the write adds a holder to `characters[]` or switches
	 * `unique` on, so an old conflict never blocks an unrelated edit.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param array<string,mixed> $object The candidate object.
	 * @param array<string,mixed> $old The stored object ([] on create).
	 * @param string $objectId The object's id ('' on a create without one).
	 * @param bool $isCreate Whether this is a create.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 */
	private function vetoHeldObject(string $kind, array $object, array $old, string $objectId, bool $isCreate): ?array {
		if ($this->holders->isUnique(kind: $kind, object: $object) === false) {
			return null;
		}

		$side = $this->idNormaliser->normalise(value: $object['characters'] ?? []);
		$gained = array_diff($side, $this->idNormaliser->normalise(value: $old['characters'] ?? []));
		$switchedOn = $isCreate === false && $this->holders->isUnique(kind: $kind, object: $old) === false;
		if ($gained === [] && $switchedOn === false && $isCreate === false) {
			return null;
		}

		$holders = $this->currentHolders(kind: $kind, objectId: $objectId, side: $side);
		if (count($holders) < 2) {
			return null;
		}

		$field = 'characters';
		if ($gained === [] && $switchedOn === true) {
			$field = 'unique';
		}

		$names = implode(', ', array_map(static fn (array $holder): string => $holder['name'], $holders));
		$message = $this->l10n->t(
			'%1$s can have only one holder, and %2$s hold it now. Remove all but one first.',
			[$this->nameOf(object: $object, fallback: $objectId), $names]
		);

		return [
			'message' => $message,
			$field => [
				[
					'code' => 'unique_' . $kind . '_several_holders',
					$kind => $objectId,
					'heldBy' => array_map(static fn (array $holder): string => $holder['name'], $holders),
					'message' => $message,
				],
			],
		];
	}//end vetoHeldObject()

	/**
	 * The holders of an item or condition, counted on both sides of the relation.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param string $objectId The object's id ('' on a create without one).
	 * @param array<int,string> $side The object's own `characters[]`.
	 *
	 * @return array<int,array{id:string,name:string}> Up to three holders.
	 */
	private function currentHolders(string $kind, string $objectId, array $side): array {
		if ($objectId !== '') {
			return $this->holders->otherHolders(kind: $kind, id: $objectId, objectSide: $side, exceptCharacter: null, max: 3);
		}

		// A new object without an id: only its own list can hold it yet.
		$holders = [];
		foreach (array_values(array_unique($side)) as $characterId) {
			$holders[] = ['id' => $characterId, 'name' => $characterId];
		}

		return $holders;
	}//end currentHolders()

	/**
	 * The schema id of an OpenRegister entity.
	 *
	 * The probe is `is_callable()`, never `method_exists()`: `getSchema()` is a
	 * magic accessor on the real ObjectEntity (see CharacterRequirementListener
	 * and larpinq#308).
	 *
	 * @param object $entity The entity.
	 *
	 * @return string The schema id, or ''.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function schemaOf(object $entity): string {
		if (is_callable([$entity, 'getSchema']) === false) {
			return '';
		}

		return (string)$entity->getSchema();
	}//end schemaOf()

	/**
	 * The id of the object being written.
	 *
	 * @param object $entity The entity.
	 * @param array<string,mixed> $data The object data.
	 *
	 * @return string The id, or '' on a create that has none yet.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function entityId(object $entity, array $data): string {
		if (is_callable([$entity, 'getUuid']) === true) {
			$uuid = $entity->getUuid();
			if (is_string($uuid) === true && $uuid !== '') {
				return $uuid;
			}
		}

		return $this->holders->objectId(object: $data);
	}//end entityId()

	/**
	 * The `name` of an object, or the fallback.
	 *
	 * @param array<string,mixed> $object The object data.
	 * @param string $fallback Returned when the object has no name.
	 *
	 * @return string The name.
	 */
	private function nameOf(array $object, string $fallback): string {
		$name = $object['name'] ?? '';
		if (is_string($name) === true && trim($name) !== '') {
			return $name;
		}

		return $fallback;
	}//end nameOf()
}//end class
