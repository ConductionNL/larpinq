<?php

/**
 * Larpinq Casting Preference Listener
 *
 * Applies the casting preference write rule on OpenRegister's vetoable
 * pre-write events, scoped to the casting preference schema, so the casting
 * deadline holds on every write path.
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
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\CastingPreferenceGuard;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IUserSession;

/**
 * Refuses a ranking after the casting deadline, or on someone else's registration.
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister event classes are an optional dependency.
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */
class CastingPreferenceListener implements IEventListener {

	/**
	 * The preference schema this listener looks at.
	 *
	 * @var ConfiguredSchema
	 */
	private readonly ConfiguredSchema $schema;

	/**
	 * Constructor.
	 *
	 * @param CastingPreferenceGuard $guard       The rule.
	 * @param IAppConfig             $config      Config (casting preference schema id).
	 * @param IUserSession           $userSession The signed-in user.
	 * @param ITimeFactory           $time        The clock.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 *
	 * @spec exclude pure framework plumbing: constructor dependency injection.
	 */
	public function __construct(
		private readonly CastingPreferenceGuard $guard,
		IAppConfig $config,
		private readonly IUserSession $userSession,
		private readonly ITimeFactory $time,
	) {
		$this->schema = new ConfiguredSchema(config: $config, key: 'castingpreference_schema');
	}//end __construct()

	/**
	 * Handle an OpenRegister pre-write event.
	 *
	 * @param Event $event The dispatched event (Creating or Updating).
	 *
	 * @return void
	 *
	 * @psalm-suppress UndefinedClass OpenRegister event classes are optional dependencies.
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) PreWriteEvent is the listeners' shared, stateless helper for OpenRegister's pre-write events.
	 */
	public function handle(Event $event): void {
		if (($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) === false
			&& ($event instanceof \OCA\OpenRegister\Event\ObjectUpdatingEvent) === false
		) {
			return;
		}

		[$entity] = PreWriteEvent::entitiesOf(event: $event);
		if ($this->schema->matches(entity: $entity) === false) {
			return;
		}

		$uid = '';
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$uid = $user->getUID();
		}

		$errors = $this->guard->check(candidate: PreWriteEvent::dataOf(entity: $entity), uid: $uid, now: $this->time->now());
		PreWriteEvent::refuseOnErrors(event: $event, errors: $errors);
	}//end handle()
}//end class
