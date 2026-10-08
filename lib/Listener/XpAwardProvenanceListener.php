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
			// @phpstan-ignore-next-line
			$old = $event->getOldObject();
			// @phpstan-ignore-next-line
			if ($old === null || $this->isAward(entity: $event->getNewObject()) === false) {
				return;
			}

			$kept = array_intersect_key((array)$old->getObject(), array_flip(self::FIELDS));
			$kept = array_filter($kept, static fn ($value): bool => $value !== null && $value !== '');
			if ($kept !== []) {
				// @phpstan-ignore-next-line
				$event->setModifiedData($kept);
			}

			return;
		}

		if ($event instanceof \OCA\OpenRegister\Event\ObjectCreatingEvent) {
			$user = $this->userSession->getUser();
			// @phpstan-ignore-next-line
			if ($user === null || $this->isAward(entity: $event->getObject()) === false) {
				return;
			}

			// @phpstan-ignore-next-line
			$event->setModifiedData(
				[
					'awardedBy' => $user->getUID(),
					'awardedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
				]
			);
		}
	}//end handle()

	/**
	 * Whether an entity is an XP award.
	 *
	 * @param object $entity The OpenRegister object entity.
	 *
	 * @return bool True for the xpAward schema.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 */
	private function isAward(object $entity): bool {
		$schema = $this->config->getValueString(Application::APP_ID, 'xpaward_schema', '');

		return $schema !== ''
			&& is_callable([$entity, 'getSchema']) === true
			&& (string)$entity->getSchema() === $schema;
	}//end isAward()
}//end class
