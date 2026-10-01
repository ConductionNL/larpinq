<?php

/**
 * Larpinq object listener registrar
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category AppInfo
 * @package  OCA\Larpinq\AppInfo
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\AppInfo;

use OCA\Larpinq\Listener\CharacterStatusListener;
use OCA\Larpinq\Listener\FactionMembershipListener;
use OCA\Larpinq\Listener\FormSubmissionListener;
use OCA\Larpinq\Listener\PlayerReviewListener;
use OCA\Larpinq\Listener\PortalProfileListener;
use OCA\Larpinq\Listener\RegistrationListener;
use OCA\Larpinq\Listener\UniqueHolderListener;
use OCA\Larpinq\Listener\XpAwardProvenanceListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * The listeners on OpenRegister's object events that keep larpinq's records
 * whole, registered from Application::register().
 *
 * Split out of Application so its coupling stays under phpmd's limit as the
 * list grows; Application still decides when they are registered.
 *
 * @category AppInfo
 * @package  OCA\Larpinq\AppInfo
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
class ObjectListenerRegistrar {

	/**
	 * Register the object listeners on OpenRegister's write events.
	 *
	 * The unique-holder veto, faction membership, character status, XP award
	 * provenance, the portal profile and the player review stamp. Guarded on
	 * the event classes, so an OpenRegister without them degrades to data-only.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function register(IRegistrationContext $context): void {
		if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', UniqueHolderListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', FactionMembershipListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', CharacterStatusListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', XpAwardProvenanceListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', PortalProfileListener::class);
		}

		// A portal profile asks portaliq for its claim once it exists (players-self-signup).
		if (class_exists('OCA\OpenRegister\Event\ObjectCreatedEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatedEvent', PortalProfileListener::class);
		}

		if (class_exists('OCA\OpenRegister\Event\ObjectUpdatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', UniqueHolderListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', FactionMembershipListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', CharacterStatusListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', XpAwardProvenanceListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', PlayerReviewListener::class);
		}

		$this->registerRegistrationListeners(context: $context);
	}//end register()

	/**
	 * Register the listeners that turn sign-ups into registrations and keep
	 * capacity, the waiting list and the participants (registration-intake-and-capacity).
	 *
	 * Nextcloud Forms sorts before larpinq, so its classes are autoloadable
	 * here when it is enabled; without it the sign-up listener is skipped.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/registration-intake-and-capacity/specs/event-registration/spec.md
	 */
	private function registerRegistrationListeners(IRegistrationContext $context): void {
		if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', RegistrationListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', RegistrationListener::class);
		}

		if (class_exists('OCA\OpenRegister\Event\ObjectCreatedEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatedEvent', RegistrationListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatedEvent', RegistrationListener::class);
		}

		if (class_exists(FormSubmissionListener::SUBMITTED_EVENT) === true) {
			$context->registerEventListener(FormSubmissionListener::SUBMITTED_EVENT, FormSubmissionListener::class);
		}
	}//end registerRegistrationListeners()
}//end class
