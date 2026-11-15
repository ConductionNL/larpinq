<?php

/**
 * XpAwardProvenanceListener for Larpinq
 *
 * Stamps who made an XP award and when, on every write path
 * (events-xp-batch-award D4, REQ-EXB-002).
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IUserSession;

/**
 * On create, `awardedBy` is the acting user and `awardedAt` the time of the
 * write, whatever the client sent. On update, both keep their stored values.
 * A write with nobody signed in (an import, a repair step) keeps what it
 * carries.
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
 * @spec openspec/specs/event-xp-awards/spec.md
 */
class XpAwardProvenanceListener implements IEventListener {

	/**
	 * The provenance fields.
	 *
	 * @var string[]
	 */
	private const FIELDS = ['awardedBy', 'awardedAt'];

	/**
	 * Constructor.
	 *
	 * @param IUserSession $userSession The user session.
	 * @param IAppConfig $config Config (xpAward schema id).
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
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
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function handle(Event $event): void {
		if ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) {
			$this->keepProvenance(event: $event);
			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) {
			$this->stampProvenance(event: $event);
		}
	}//end handle()

	/**
	 * On update, write the stored provenance back over what the client sent.
	 *
	 * @param Event $event The pre-update event.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress MixedAssignment  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 */
	private function keepProvenance(Event $event): void {
		// @phpstan-ignore-next-line
		$old = $event->getOldObject();
		// @phpstan-ignore-next-line
		if ($old === null || $this->isAward(entity: $event->getNewObject()) === false) {
			return;
		}

		$kept = WriteStamp::stored(object: (array)$old->getObject(), fields: self::FIELDS);
		if ($kept !== []) {
			// @phpstan-ignore-next-line
			$event->setModifiedData($kept);
		}
	}//end keepProvenance()

	/**
	 * On create, stamp the acting user and the time of the write.
	 *
	 * @param Event $event The pre-create event.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall  OpenRegister event/entity classes are optional dependencies.
	 * @psalm-suppress UndefinedMethod  The OpenRegister event accessors are resolved at runtime.
	 */
	private function stampProvenance(Event $event): void {
		$user = $this->userSession->getUser();
		// @phpstan-ignore-next-line
		if ($user === null || $this->isAward(entity: $event->getObject()) === false) {
			return;
		}

		// @phpstan-ignore-next-line
		$event->setModifiedData(WriteStamp::now(fields: self::FIELDS, uid: $user->getUID()));
	}//end stampProvenance()

	/**
	 * Whether an entity is an XP award.
	 *
	 * @param object $entity The OpenRegister object entity.
	 *
	 * @return bool True for the xpAward schema.
	 */
	private function isAward(object $entity): bool {
		return (new ConfiguredSchema(config: $this->config, key: 'xpaward_schema'))->matches(entity: $entity);
	}//end isAward()
}//end class
