<?php

/**
 * Store-controller binding in Application::register().
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

namespace OCA\Larpinq\Tests\Unit\AppInfo;

use OCA\Larpinq\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The /api/store/items route from Routes::standard() resolves to
 * OCA\Larpinq\Controller\StoreController, a class larpinq does not ship.
 * register() must bind that name to OpenRegister's generic store controller,
 * or every store request answers 500.
 */
class ApplicationStoreRouteTest extends TestCase {

	/**
	 * When OpenRegister's AppHost Bootstrap resolves, register() binds the
	 * store controller under larpinq's class name.
	 *
	 * @return void
	 */
	public function testStoreControllerIsBoundWhenOpenRegisterIsResolvable(): void {
		require_once __DIR__ . '/fixtures/openregister-apphost-bootstrap.php';

		$services = [];
		$context = $this->createMock(originalClassName: IRegistrationContext::class);
		$context->method('registerService')
			->willReturnCallback(
				static function (string $name) use (&$services): void {
					$services[] = $name;
				}
			);

		$application = (new ReflectionClass(objectOrClass: Application::class))
			->newInstanceWithoutConstructor();
		$application->register($context);

		$this->assertFalse(
			condition: class_exists('OCA\Larpinq\Controller\StoreController'),
			message: 'larpinq now ships its own StoreController, so this binding is no longer needed.'
		);
		$this->assertContains(
			needle: 'OCA\Larpinq\Controller\StoreController',
			haystack: $services
		);

	}//end testStoreControllerIsBoundWhenOpenRegisterIsResolvable()

}//end class
