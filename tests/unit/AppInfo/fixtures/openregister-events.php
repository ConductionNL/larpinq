<?php

/**
 * Minimal stand-ins for the OpenRegister event classes Application::register()
 * probes for with class_exists().
 *
 * register() only ever passes these class NAMES to
 * IRegistrationContext::registerEventListener() — it never constructs one, and
 * never touches a member. So a name is the entire contract this file has to
 * satisfy, and declaring anything more would be inventing an API that
 * OpenRegister owns.
 *
 * Loading this file simulates the world the ADR-040 autoload prelude creates:
 * OpenRegister's PSR-4 prefix on the autoloader, so `OCA\OpenRegister\` names
 * resolve from inside this app's own register(). Without the prelude they do
 * not, because Nextcloud registers apps in sorted order and `larpingapp` sorts
 * before `openregister`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Event;

if (class_exists(\OCA\OpenRegister\Event\DeepLinkRegistrationEvent::class, false) === false) {
	/**
	 * Stand-in for OpenRegister's unified-search deep-link registration event.
	 */
	class DeepLinkRegistrationEvent {
	}//end class
}

if (class_exists(\OCA\OpenRegister\Event\ObjectCreatingEvent::class, false) === false) {
	/**
	 * Stand-in for OpenRegister's pre-create object event.
	 */
	class ObjectCreatingEvent {
	}//end class
}

if (class_exists(\OCA\OpenRegister\Event\ObjectUpdatingEvent::class, false) === false) {
	/**
	 * Stand-in for OpenRegister's pre-update object event.
	 */
	class ObjectUpdatingEvent {
	}//end class
}
