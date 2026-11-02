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
use OCA\Larpinq\Service\UniqueHolderService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
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
	 * @param UniqueHolderVeto $veto The unique-holder rule.
	 * @param UniqueHolderService $holders The holder lookup (object ids).
	 * @param IAppConfig $config Config (schema id resolution).
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly UniqueHolderVeto $veto,
		private readonly UniqueHolderService $holders,
		private readonly IAppConfig $config,
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

		$subject = $this->subjectOf(event: $event);
		if ($subject === null) {
			return;
		}

		$errors = $this->vetoOrRefuse(subject: $subject);
		if ($errors !== null) {
			$event->stopPropagation();
			// @phpstan-ignore-next-line
			$event->setErrors($errors);
		}
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
	 * What a pre-write event writes, for the unique-holder rule: the subject
	 * (`character`, `item` or `condition`) and the write, or null for any other
	 * schema.
	 *
	 * When the event cannot be read, the error is logged and the write passes:
	 * without the event's data there is no telling whether it adds a holder.
	 *
	 * @param Event $event The Creating or Updating event.
	 *
	 * @return array{subject:string,write:ObjectWrite}|null The subject and the write.
	 */
	private function subjectOf(Event $event): ?array {
		try {
			[$entity, $old] = $this->entitiesOf(event: $event);
			$subject = $this->subjectKind(schema: $this->schemaOf(entity: $entity));
			if ($subject === null) {
				return null;
			}

			return ['subject' => $subject, 'write' => $this->writeOf(entity: $entity, old: $old)];
		} catch (\Throwable $e) {
			$this->logger->error('Larpinq: unique-holder check could not read the write; allowing it.', ['exception' => $e]);
			return null;
		}
	}//end subjectOf()

	/**
	 * The veto for a write. When the check itself fails, a write that adds a
	 * holder is refused with a try-again message and every other write passes.
	 *
	 * @param array{subject:string,write:ObjectWrite} $subject The subject and the write.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 */
	private function vetoOrRefuse(array $subject): ?array {
		try {
			return $this->veto->veto(subject: $subject['subject'], write: $subject['write']);
		} catch (\Throwable $e) {
			$this->logger->error('Larpinq: unique-holder check errored; refusing a write that adds a holder.', ['exception' => $e]);
			return $this->veto->uncheckedRefusal(subject: $subject['subject'], write: $subject['write']);
		}
	}//end vetoOrRefuse()

	/**
	 * The subject a schema holds: `character`, `item`, `condition`, or null.
	 *
	 * @param string $schema The schema id.
	 *
	 * @return string|null The subject.
	 */
	private function subjectKind(string $schema): ?string {
		if ($schema === '') {
			return null;
		}

		if ($schema === $this->config->getValueString(Application::APP_ID, 'character_schema', '')) {
			return 'character';
		}

		return $this->heldKindOf(schema: $schema);
	}//end subjectKind()

	/**
	 * The write a pre-write event carries.
	 *
	 * @param object $entity The new object entity.
	 * @param object|null $old The stored object entity on an update.
	 *
	 * @return ObjectWrite The write.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity is an optional dependency.
	 */
	private function writeOf(object $entity, ?object $old): ObjectWrite {
		$new = (array)$entity->getObject();
		$previous = [];
		if ($old !== null) {
			$previous = (array)$old->getObject();
		}

		return new ObjectWrite(
			data: $new,
			old: $previous,
			id: $this->entityId(entity: $entity, data: $new),
			isCreate: $old === null
		);
	}//end writeOf()

	/**
	 * The kind ('item' or 'condition') whose schema this is, or null.
	 *
	 * @param string $schema The schema id.
	 *
	 * @return string|null The kind.
	 */
	private function heldKindOf(string $schema): ?string {
		foreach (array_keys(UniqueHolderService::CHARACTER_FIELD) as $kind) {
			if ($schema === $this->config->getValueString(Application::APP_ID, $kind . '_schema', '')) {
				return $kind;
			}
		}

		return null;
	}//end heldKindOf()

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
}//end class
