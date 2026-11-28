<?php

/**
 * Minimal stand-in for OpenRegister's AppHost Bootstrap, holding only the
 * store-controller alias Application::register() calls.
 *
 * Like the real helper, it registers the generic store controller under the
 * leaf app's `<controllerNs>\StoreController` name. The factory is never
 * resolved in a unit run, so it returns nothing.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\AppInfo
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

namespace OCA\OpenRegister\AppHost;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

if (class_exists(\OCA\OpenRegister\AppHost\Bootstrap::class, false) === false) {
	/**
	 * Stand-in for OpenRegister's AppHost Bootstrap.
	 */
	class Bootstrap {

		/**
		 * Register the store controller under the leaf app's class name.
		 *
		 * @param IRegistrationContext $context      Leaf registration context.
		 * @param string               $appId        Leaf app id.
		 * @param string               $controllerNs Leaf controller namespace.
		 *
		 * @return void
		 */
		public static function aliasStoreController(IRegistrationContext $context, string $appId, string $controllerNs): void {
			$context->registerService(rtrim($controllerNs, '\\') . '\\StoreController', static fn () => null);
		}//end aliasStoreController()
	}//end class
}//end if
