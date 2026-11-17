<?php

/**
 * Unit tests for the container side of the cross-app identity resolver.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

use OCA\Larpinq\Support\FleetAppId;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;

/**
 * Pins how FleetAppId fetches another fleet app's service whichever namespace
 * that app ships under, and how it finds an interface by name.
 *
 * @covers \OCA\Larpinq\Support\FleetAppId
 */
final class FleetAppIdServiceTest extends TestCase {

	/**
	 * A container that answers the given ids and throws for every other one,
	 * recording each id it was asked for.
	 *
	 * @param array<string, mixed> $services Service id => what get() returns.
	 * @param list<string>         $asked    Filled with every id asked for.
	 *
	 * @return ContainerInterface The container.
	 */
	private function containerWith(array $services, array &$asked): ContainerInterface {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($services, &$asked): mixed {
				$asked[] = $id;
				if (array_key_exists($id, $services) === false) {
					throw new RuntimeException('not registered: '.$id);
				}

				return $services[$id];
			}
		);

		return $container;
	}//end containerWith()

	/**
	 * The service under the new namespace is returned without asking for the old one.
	 *
	 * @return void
	 */
	public function testGetServicePrefersTheNewNamespace(): void {
		$asked   = [];
		$service = new stdClass();
		$old     = new stdClass();

		$result = FleetAppId::getService(
			container: $this->containerWith(
				services: [
					'OCA\Filinq\Service\PdfService'   => $service,
					'OCA\DocuDesk\Service\PdfService' => $old,
				],
				asked: $asked
			),
			canonical: 'filinq',
			relative: 'Service\PdfService'
		);

		$this->assertSame(expected: $service, actual: $result);
		$this->assertSame(expected: ['OCA\Filinq\Service\PdfService'], actual: $asked);
	}//end testGetServicePrefersTheNewNamespace()

	/**
	 * A container that throws for the new namespace falls back to the retired one.
	 *
	 * @return void
	 */
	public function testGetServiceFallsBackToTheRetiredNamespace(): void {
		$asked   = [];
		$service = new stdClass();

		$result = FleetAppId::getService(
			container: $this->containerWith(
				services: ['OCA\OpenConnector\Service\CallService' => $service],
				asked: $asked
			),
			canonical: 'integriq',
			relative: '\Service\CallService'
		);

		$this->assertSame(expected: $service, actual: $result);
		$this->assertSame(
			expected: ['OCA\Integriq\Service\CallService', 'OCA\OpenConnector\Service\CallService'],
			actual: $asked
		);
	}//end testGetServiceFallsBackToTheRetiredNamespace()

	/**
	 * A candidate that resolves to something other than an object is skipped.
	 *
	 * @return void
	 */
	public function testGetServiceSkipsANonObjectEntry(): void {
		$asked   = [];
		$service = new stdClass();

		$result = FleetAppId::getService(
			container: $this->containerWith(
				services: [
					'OCA\Decidiq\Service\VoteService'  => 'not-a-service',
					'OCA\Decidesk\Service\VoteService' => $service,
				],
				asked: $asked
			),
			canonical: 'decidiq',
			relative: 'Service\VoteService'
		);

		$this->assertSame(expected: $service, actual: $result);
		$this->assertCount(expectedCount: 2, haystack: $asked);
	}//end testGetServiceSkipsANonObjectEntry()

	/**
	 * When no candidate is registered the result is null, after trying each name once.
	 *
	 * @return void
	 */
	public function testGetServiceIsNullWhenNoCandidateResolves(): void {
		$asked = [];

		$result = FleetAppId::getService(
			container: $this->containerWith(services: [], asked: $asked),
			canonical: 'buildiq',
			relative: 'Service\PermitService'
		);

		$this->assertNull(actual: $result);
		$this->assertSame(
			expected: ['OCA\Buildiq\Service\PermitService', 'OCA\OpenBuilt\Service\PermitService'],
			actual: $asked
		);
	}//end testGetServiceIsNullWhenNoCandidateResolves()

	/**
	 * An unknown app has no candidates, so the container is never asked.
	 *
	 * @return void
	 */
	public function testGetServiceNeverAsksTheContainerForAnUnknownApp(): void {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->expects($this->never())->method('get');

		$this->assertNull(
			actual: FleetAppId::getService(container: $container, canonical: 'notafleetapp', relative: 'Service\Thing')
		);
	}//end testGetServiceNeverAsksTheContainerForAnUnknownApp()

	/**
	 * An interface under the retired namespace is found by resolveClass.
	 *
	 * @return void
	 */
	public function testResolveClassFindsAnInterfaceUnderTheRetiredNamespace(): void {
		if (interface_exists('OCA\Doriath\Contract\ArchiveProbeInterface') === false) {
			eval('namespace OCA\Doriath\Contract; interface ArchiveProbeInterface {}');
		}

		$this->assertSame(
			expected: 'OCA\Doriath\Contract\ArchiveProbeInterface',
			actual: FleetAppId::resolveClass(canonical: 'keepiq', relative: 'Contract\ArchiveProbeInterface')
		);
	}//end testResolveClassFindsAnInterfaceUnderTheRetiredNamespace()
}//end class
