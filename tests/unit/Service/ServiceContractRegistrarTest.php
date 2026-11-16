<?php

/**
 * Unit tests for ServiceContractRegistrar.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec exclude Structural contract wiring, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Kernel\Contracts\EventFeedbackServiceInterface;
use OCA\Larpinq\Kernel\Contracts\RegisterObjectFetcherInterface;
use OCA\Larpinq\Service\EventFeedbackService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\ServiceContractRegistrar;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Every Kernel contract is aliased once, to a Service class that implements it.
 *
 * @covers \OCA\Larpinq\Service\ServiceContractRegistrar
 */
final class ServiceContractRegistrarTest extends TestCase {

	/**
	 * The registrar binds each contract once, each to a class that implements it.
	 *
	 * @return void
	 */
	public function testEachContractIsBoundOnceToItsImplementation(): void {
		$aliases = [];
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->never())->method('registerService');
		$context->method('registerServiceAlias')->willReturnCallback(
			static function (string $alias, string $target) use (&$aliases): void {
				$aliases[] = [$alias, $target];
			}
		);

		(new ServiceContractRegistrar())->register(context: $context);

		$names = array_column($aliases, 0);
		$this->assertCount(27, $aliases);
		$this->assertSame($names, array_values(array_unique($names)));
		$this->assertContains([EventFeedbackServiceInterface::class, EventFeedbackService::class], $aliases);
		$this->assertContains([RegisterObjectFetcherInterface::class, RegisterObjectFetcher::class], $aliases);
		foreach ($aliases as [$alias, $target]) {
			$this->assertStringStartsWith('OCA\\Larpinq\\Kernel\\Contracts\\', $alias);
			$this->assertStringStartsWith('OCA\\Larpinq\\Service\\', $target);
			$this->assertTrue((new ReflectionClass($target))->implementsInterface($alias), $target.' implements '.$alias);
		}
	}//end testEachContractIsBoundOnceToItsImplementation()
}//end class
