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

use OCA\Larpinq\Listener\CastingPreferenceListener;
use OCA\Larpinq\Listener\CharacterRequirementListener;
use OCA\Larpinq\Listener\CharacterStatusListener;
use OCA\Larpinq\Listener\DeepLinkRegistrationListener;
use OCA\Larpinq\Listener\FactionMembershipListener;
use OCA\Larpinq\Listener\FormSubmissionListener;
use OCA\Larpinq\Listener\HelpMessageListener;
use OCA\Larpinq\Listener\PaymentRequestListener;
use OCA\Larpinq\Listener\PlayerCareListener;
use OCA\Larpinq\Listener\PlayerReviewListener;
use OCA\Larpinq\Listener\PortalProfileListener;
use OCA\Larpinq\Listener\RegistrationListener;
use OCA\Larpinq\Listener\SignupFormBindingListener;
use OCA\Larpinq\Listener\UniqueHolderListener;
use OCA\Larpinq\Listener\XpAwardProvenanceListener;
use OCA\Larpinq\Mcp\LarpinqScannableServices;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * The listeners on OpenRegister's object events that keep larpinq's records
 * whole, registered from Application::register().
 *
 * Split out of Application so its coupling stays under phpmd's limit as the
 * list grows; Application still decides when they are registered. Every other
 * hook into OpenRegister lives here too: the deep-link listener, the
 * character requirement guard and the curated MCP tool listing.
 *
 * @category AppInfo
 * @package  OCA\Larpinq\AppInfo
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Its one job is to name each
 * listener class, so its coupling grows with the list, as ServiceContractRegistrar's does.
 */
class ObjectListenerRegistrar {

	/**
	 * Register the object listeners on OpenRegister's write events.
	 *
	 * The unique-holder veto, faction membership, character status, the casting
	 * deadline, XP award provenance, the portal profile and the player review stamp. Guarded on
	 * the event classes, so an OpenRegister without them degrades to data-only.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function register(IRegistrationContext $context): void {
		$this->registerOpenRegisterHooks(context: $context);

		if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', UniqueHolderListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', FactionMembershipListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', CharacterStatusListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', CastingPreferenceListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', XpAwardProvenanceListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', PortalProfileListener::class);
			// A sign-up form binding is checked against the registration (forms-registration-is-checked-at-binding).
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', SignupFormBindingListener::class);
			// The help desk thread (communication-help-desk, REQ-CHD-002, REQ-CHD-003).
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', HelpMessageListener::class);
		}

		// A portal profile asks portaliq for its claim once it exists (players-self-signup).
		if (class_exists('OCA\OpenRegister\Event\ObjectCreatedEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatedEvent', PortalProfileListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatedEvent', HelpMessageListener::class);
		}

		// A player's changed care details follow into the event copies (players-care-details-and-erasure).
		$this->registerCareListeners(context: $context);

		if (class_exists('OCA\OpenRegister\Event\ObjectUpdatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', UniqueHolderListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', FactionMembershipListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', CharacterStatusListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', CastingPreferenceListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', XpAwardProvenanceListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', PlayerReviewListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', SignupFormBindingListener::class);
		}

		$this->registerRegistrationListeners(context: $context);
	}//end register()

	/**
	 * Register the hooks registered before the object listeners: the curated
	 * MCP tools, the deep-link listener and the character requirement guard.
	 *
	 * The curated MCP tools (hermiq-ai-tooling REQ-004): OpenRegister scans the
	 * classes LarpinqScannableServices lists for #[McpTool] methods; the
	 * interface is OpenRegister's, so the alias waits for it. The deep link
	 * serves OpenRegister's unified search. The requirement guard is the
	 * server-authoritative skill-requirement and XP-budget check on character
	 * writes (skill-requirement-enforcement), registered first so it runs before
	 * the other pre-write listeners, as it did from Application. Each is guarded
	 * on its OpenRegister class, so an older OpenRegister degrades to data-only.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	private function registerOpenRegisterHooks(IRegistrationContext $context): void {
		if (interface_exists('OCA\OpenRegister\Mcp\IMcpScannableServices') === true) {
			$context->registerServiceAlias(
				'OCA\OpenRegister\Mcp\IMcpScannableServices::' . Application::APP_ID,
				LarpinqScannableServices::class
			);
		}

		if (class_exists('OCA\OpenRegister\Event\DeepLinkRegistrationEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\DeepLinkRegistrationEvent', DeepLinkRegistrationListener::class);
		}

		if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', CharacterRequirementListener::class);
		}

		if (class_exists('OCA\OpenRegister\Event\ObjectUpdatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', CharacterRequirementListener::class);
		}
	}//end registerOpenRegisterHooks()

	/**
	 * Register the listener that carries a player's changed care details into
	 * the event copies (players-care-details-and-erasure).
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 */
	private function registerCareListeners(IRegistrationContext $context): void {
		if (class_exists('OCA\OpenRegister\Event\ObjectUpdatedEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatedEvent', PlayerCareListener::class);
		}
	}//end registerCareListeners()

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
	 * @spec openspec/specs/event-registration/spec.md
	 */
	private function registerRegistrationListeners(IRegistrationContext $context): void {
		if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatingEvent', RegistrationListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatingEvent', RegistrationListener::class);
		}

		if (class_exists('OCA\OpenRegister\Event\ObjectCreatedEvent') === true) {
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatedEvent', RegistrationListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatedEvent', RegistrationListener::class);
			// After the registration listener, so a registration's payment is
			// asked for once its place is settled (registration-payments-through-shillinq).
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectCreatedEvent', PaymentRequestListener::class);
			$context->registerEventListener('OCA\OpenRegister\Event\ObjectUpdatedEvent', PaymentRequestListener::class);
		}

		if (class_exists(FormSubmissionListener::SUBMITTED_EVENT) === true) {
			$context->registerEventListener(FormSubmissionListener::SUBMITTED_EVENT, FormSubmissionListener::class);
		}
	}//end registerRegistrationListeners()
}//end class
