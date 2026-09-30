<?php

/**
 * Larpinq Portal Profile Listener
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

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What the portal writes into larpinq, made whole.
 *
 * - A player created through the portal (it carries `portalSubjectRef`) is
 *   marked self-registered and awaiting review; a second one for the same
 *   portal account is refused.
 * - Once it exists, portaliq is asked to record the player's uuid as the
 *   account's `ownerRef` claim, which the `createCharacter` action and the
 *   `myCharacters` collection read. portaliq answers in the event's result
 *   slot; anything but `ok` is logged and the profile stays unlinked.
 * - A character created through the portal (it carries `ownerRef`) is played
 *   by that player: `ocName` is set to the owner when empty.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass OpenRegister and portaliq event classes are optional dependencies.
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */
class PortalProfileListener implements IEventListener {

	/**
	 * portaliq's claim event (hydra ADR-041), resolved at runtime.
	 *
	 * @var string
	 */
	private const CLAIM_EVENT = 'OCA\Portaliq\Event\PortalAccountClaimRequestedEvent';

	/**
	 * The claim the portal contribution scopes characters by.
	 *
	 * @var string
	 */
	private const CLAIM = 'ownerRef';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $config Config (player and character schema ids).
	 * @param RegisterObjectFetcher $fetcher Reads existing profiles with the app's authority.
	 * @param IEventDispatcher $dispatcher Dispatches portaliq's claim event.
	 * @param IL10N $l10n Translations for the refusal message.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IAppConfig $config,
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IEventDispatcher $dispatcher,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle an OpenRegister create event.
	 *
	 * @param Event $event The dispatched event (Creating or Created).
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
		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatedEvent) {
			$this->requestClaim(entity: $event->getObject());
			return;
		}

		if (($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) === false) {
			return;
		}

		$entity = $event->getObject();
		$data = (array)$entity->getObject();
		if ($this->isSchema(entity: $entity, key: 'character_schema') === true) {
			$owner = (string)($data['ownerRef'] ?? '');
			if ($owner !== '' && (string)($data['ocName'] ?? '') === '') {
				// @phpstan-ignore-next-line
				$event->setModifiedData(array_merge($event->getModifiedData(), ['ocName' => $owner]));
			}

			return;
		}

		$subject = (string)($data['portalSubjectRef'] ?? '');
		if ($subject === '' || $this->isSchema(entity: $entity, key: 'player_schema') === false) {
			return;
		}

		if ($this->hasProfile(subject: $subject) === true) {
			$event->stopPropagation();
			// @phpstan-ignore-next-line
			$event->setErrors(['message' => $this->l10n->t('This portal account already has a player profile.')]);
			return;
		}

		// @phpstan-ignore-next-line
		$event->setModifiedData(array_merge($event->getModifiedData(), ['selfRegistered' => true, 'awaitingReview' => true]));
	}//end handle()

	/**
	 * Whether the portal account already has a player profile.
	 *
	 * A failing read lets the create through (logged): a lookup error must not
	 * lock every new player out of the portal.
	 *
	 * @param string $subject The portal subject reference.
	 *
	 * @return bool True when a profile exists.
	 */
	private function hasProfile(string $subject): bool {
		try {
			return $this->fetcher->getObjectsWithAppAuthority('player', ['portalSubjectRef' => $subject], 1) !== [];
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: the one-profile check failed; allowing the create.', ['exception' => $e]);
			return false;
		}
	}//end hasProfile()

	/**
	 * Ask portaliq to link a new portal profile to its account.
	 *
	 * @param object $entity The created OpenRegister object.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister and portaliq classes are optional dependencies.
	 * @psalm-suppress MixedAssignment OpenRegister and portaliq classes are optional dependencies.
	 */
	private function requestClaim(object $entity): void {
		if ($this->isSchema(entity: $entity, key: 'player_schema') === false || is_callable([$entity, 'getObject']) === false) {
			return;
		}

		$subject = (string)(((array)$entity->getObject())['portalSubjectRef'] ?? '');
		$uuid = (string)(is_callable([$entity, 'getUuid']) === true ? $entity->getUuid() : '');
		if ($subject === '' || $uuid === '') {
			return;
		}

		if (class_exists(self::CLAIM_EVENT) === false) {
			$this->logger->warning('Larpinq: portaliq has no claim event; the new player profile stays unlinked.', ['player' => $uuid]);
			return;
		}

		$class = self::CLAIM_EVENT;
		$claim = new $class(Application::APP_ID, $subject, self::CLAIM, $uuid);
		$this->dispatcher->dispatchTyped($claim);
		// @phpstan-ignore-next-line
		$result = (string)$claim->getResult();
		if ($result !== 'ok') {
			$this->logger->warning('Larpinq: portaliq did not link the new player profile.', ['player' => $uuid, 'result' => $result]);
		}
	}//end requestClaim()

	/**
	 * Whether an entity belongs to the configured schema.
	 *
	 * @param object $entity The OpenRegister object entity.
	 * @param string $key The config key of the schema id.
	 *
	 * @return bool True when it does.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function isSchema(object $entity, string $key): bool {
		$schema = $this->config->getValueString(Application::APP_ID, $key, '');

		return $schema !== ''
			&& is_callable([$entity, 'getSchema']) === true
			&& (string)$entity->getSchema() === $schema;
	}//end isSchema()
}//end class
