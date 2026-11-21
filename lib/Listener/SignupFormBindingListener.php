<?php

/**
 * Larpinq Sign-up Form Binding Listener
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
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Kernel\Contracts\SignupFormBindingInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Refuses an event write that binds a sign-up form, or changes its question
 * mapping, when the binding is not valid against the registration schema.
 *
 * Only a write that changes the binding is judged, so an unrelated edit of an
 * event with an older binding still saves. A binding that cannot be judged is
 * refused too: an unchecked binding is what this check exists to prevent.
 *
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
 *
 * @template-implements IEventListener<Event>
 */
class SignupFormBindingListener implements IEventListener {

	private const UNVERIFIABLE = [
		'code' => 'binding_unverifiable',
		'message' => 'The sign-up form could not be checked against the registration, so it is not bound.',
	];

	/**
	 * Constructor.
	 *
	 * @param SignupFormBindingInterface $binding Judges the binding.
	 * @param LoggerInterface            $logger  Records why a binding could not be judged.
	 * @param IAppConfig                 $config  Holds the configured event schema.
	 */
	public function __construct(
		private readonly SignupFormBindingInterface $binding,
		private readonly LoggerInterface $logger,
		private readonly IAppConfig $config,
	) {
	}//end __construct()

	/**
	 * Judge the binding of an event write.
	 *
	 * @param Event $event The OpenRegister pre-write event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-binding-must-be-valid-against-the-registration-schema-when-it-is-made
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) PreWriteEvent is a pure helper over the event it is handed.
	 */
	public function handle(Event $event): void {
		if (PreWriteEvent::isPreWrite(event: $event) === false) {
			return;
		}

		[$new, $old] = PreWriteEvent::entitiesOf(event: $event);
		if ((new ConfiguredSchema(config: $this->config, key: 'event_schema'))->matches(entity: $new) === false) {
			return;
		}

		$candidate = (array)$new->getObject();
		if ($this->binding->bindingChanged(candidate: $candidate, old: (array)($old?->getObject() ?? [])) === false) {
			return;
		}

		PreWriteEvent::refuseOnErrors(event: $event, errors: $this->errorsOf(candidate: $candidate));
	}//end handle()

	/**
	 * The refusal for a binding, null when it is valid.
	 *
	 * @param array<string, mixed> $candidate The event as it will be stored.
	 *
	 * @return array<string, list<array<string, mixed>>>|null The errors keyed by the mapping property.
	 */
	private function errorsOf(array $candidate): ?array {
		try {
			$findings = $this->binding->findings(event: $candidate);
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: the sign-up form binding could not be checked; it is refused.', ['exception' => $e]);

			return ['signupFormMapping' => [self::UNVERIFIABLE]];
		}

		if ($findings === []) {
			return null;
		}

		return ['signupFormMapping' => array_values($findings)];
	}//end errorsOf()
}//end class
