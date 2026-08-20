<?php

/**
 * Unit tests for RegisterObjectFetcher.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use Exception;
use InvalidArgumentException;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for RegisterObjectFetcher service.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class RegisterObjectFetcherTest extends TestCase {

	/**
	 * The service under test.
	 *
	 * @var RegisterObjectFetcher
	 */
	private RegisterObjectFetcher $service;

	/**
	 * DI container mock.
	 *
	 * @var ContainerInterface&MockObject
	 */
	private ContainerInterface&MockObject $container;

	/**
	 * App manager mock.
	 *
	 * @var IAppManager&MockObject
	 */
	private IAppManager&MockObject $appManager;

	/**
	 * App config mock.
	 *
	 * @var IAppConfig&MockObject
	 */
	private IAppConfig&MockObject $config;

	/**
	 * Logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->config = $this->createMock(originalClassName: IAppConfig::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		$this->service = new RegisterObjectFetcher(
			container: $this->container,
			appManager: $this->appManager,
			config: $this->config,
			logger: $this->logger,
		);

	}//end setUp()

	/**
	 * Test that getObjects throws when OpenRegister is not installed.
	 *
	 * @return void
	 */
	public function testGetObjectsThrowsWhenOpenRegisterNotInstalled(): void {
		$this->appManager->method('getInstalledApps')->willReturn([]);

		$this->expectException(exception: Exception::class);
		$this->expectExceptionMessage(message: 'OpenRegister app is not installed');

		$this->service->getObjects('character');

	}//end testGetObjectsThrowsWhenOpenRegisterNotInstalled()

	/**
	 * Test that getObjects throws when the register is not configured.
	 *
	 * @return void
	 */
	public function testGetObjectsThrowsWhenRegisterNotConfigured(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$mockObjectService = new \stdClass();
		$this->container->method('get')
			->with('OCA\OpenRegister\Service\ObjectService')
			->willReturn($mockObjectService);

		$this->config->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default): string {
					// Returns empty string for all keys.
					return $default;
				}
			);

		$this->expectException(exception: Exception::class);
		$this->expectExceptionMessage(message: 'Register not configured for character');

		$this->service->getObjects('character');

	}//end testGetObjectsThrowsWhenRegisterNotConfigured()

	/**
	 * Test that getObjects throws when the schema is not configured.
	 *
	 * @return void
	 */
	public function testGetObjectsThrowsWhenSchemaNotConfigured(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$mockObjectService = new \stdClass();
		$this->container->method('get')
			->with('OCA\OpenRegister\Service\ObjectService')
			->willReturn($mockObjectService);

		$this->config->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default): string {
					if ($key === 'character_register') {
						return 'reg-123';
					}

					return $default;
				}
			);

		$this->expectException(exception: Exception::class);
		$this->expectExceptionMessage(message: 'Schema not configured for character');

		$this->service->getObjects('character');

	}//end testGetObjectsThrowsWhenSchemaNotConfigured()

	/**
	 * Test that getObject converts an uppercase object type to lowercase.
	 *
	 * @return void
	 */
	public function testGetObjectConvertsUppercaseTypeToLowercase(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$testUuid = '00000000-0000-0000-0000-000000000001';

		$mockMapper = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['find'])
			->getMock();
		$mockMapper->method('find')->willReturn(['id' => $testUuid, 'name' => 'Test']);

		$mockObjectService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getMapper'])
			->getMock();
		$mockObjectService->method('getMapper')->willReturn($mockMapper);

		$this->container->method('get')->willReturn($mockObjectService);

		// Should look up 'skill_register' and 'skill_schema' (lowercase).
		$this->config->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default): string {
					if ($key === 'skill_register') {
						return 'reg-1';
					}

					if ($key === 'skill_schema') {
						return 'sch-1';
					}

					return $default;
				}
			);

		// Pass 'Skill' with uppercase S.
		$result = $this->service->getObject('Skill', $testUuid);

		self::assertSame(expected: $testUuid, actual: $result['id']);
		self::assertSame(expected: 'Test', actual: $result['name']);

	}//end testGetObjectConvertsUppercaseTypeToLowercase()

	/**
	 * Test that getObject rejects URI-format IDs (closes #212).
	 *
	 * URL-slicing was removed because it silently derived an object ID from
	 * any valid URL regardless of domain, providing an IDOR primitive.
	 *
	 * @return void
	 */
	public function testGetObjectRejectsUriFormatId(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'Invalid object ID: expected a UUID');

		$this->service->getObject('character', 'https://example.com/api/objects/abc-123');

	}//end testGetObjectRejectsUriFormatId()

	/**
	 * Test that getObject rejects non-UUID string IDs.
	 *
	 * @return void
	 */
	public function testGetObjectRejectsNonUuidId(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'Invalid object ID: expected a UUID');

		$this->service->getObject('character', 'not-a-uuid');

	}//end testGetObjectRejectsNonUuidId()

	/**
	 * Test that getObjects returns arrays of arrays.
	 *
	 * @return void
	 */
	public function testGetObjectsReturnsArrayOfArrays(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$mockMapper = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['findAll'])
			->getMock();
		$mockMapper->method('findAll')->willReturn(
			[
				['id' => 'obj-1', 'name' => 'First'],
				['id' => 'obj-2', 'name' => 'Second'],
			]
		);

		$mockObjectService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getMapper'])
			->getMock();
		$mockObjectService->method('getMapper')->willReturn($mockMapper);

		$this->container->method('get')->willReturn($mockObjectService);

		$this->config->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default): string {
					if ($key === 'skill_register') {
						return 'reg-1';
					}

					if ($key === 'skill_schema') {
						return 'sch-1';
					}

					return $default;
				}
			);

		$results = $this->service->getObjects('skill');

		self::assertCount(expectedCount: 2, haystack: $results);
		self::assertSame(expected: 'obj-1', actual: $results[0]['id']);
		self::assertSame(expected: 'obj-2', actual: $results[1]['id']);

	}//end testGetObjectsReturnsArrayOfArrays()

	/**
	 * Test that getObjects passes a config array as the first argument to findAll.
	 *
	 * Regression guard for issue #204: the prior code passed positional scalar args
	 * ($limit, $offset, ...) which mis-mapped to OpenRegister's findAll(array $config, ...)
	 * signature, causing a TypeError on every request.
	 *
	 * @return void
	 */
	public function testGetObjectsPassesConfigArrayToFindAll(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$mockMapper = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['findAll'])
			->getMock();

		// Verify that findAll is called with a config array as the first positional argument,
		// not with positional scalar/null args (which would fail against OR's real signature).
		$mockMapper->expects($this->once())
			->method('findAll')
			->with(
				$this->callback(
					callback: function (mixed $firstArg): bool {
						return is_array($firstArg)
							&& array_key_exists('limit', $firstArg)
							&& array_key_exists('offset', $firstArg)
							&& array_key_exists('filters', $firstArg)
							&& array_key_exists('sort', $firstArg)
							&& array_key_exists('search', $firstArg);
					}
				)
			)
			->willReturn([]);

		$mockObjectService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getMapper'])
			->getMock();
		$mockObjectService->method('getMapper')->willReturn($mockMapper);

		$this->container->method('get')->willReturn($mockObjectService);

		$this->config->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default): string {
					if ($key === 'character_register') {
						return 'reg-1';
					}

					if ($key === 'character_schema') {
						return 'sch-1';
					}

					return $default;
				}
			);

		$results = $this->service->getObjects('character');

		self::assertSame(expected: [], actual: $results);

	}//end testGetObjectsPassesConfigArrayToFindAll()

	/**
	 * Test that getObject handles JSON-serializable objects.
	 *
	 * @return void
	 */
	public function testGetObjectHandlesJsonSerializableObjects(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$testUuid = '12345678-1234-1234-1234-123456789012';

		$mockObj = new class($testUuid) implements \JsonSerializable {
			/**
			 * Construct the serializable fixture.
			 *
			 * @param string $id The UUID for the object.
			 */
			public function __construct(
				private string $id,
			) {
			}//end __construct()

			/**
			 * Serialize the object to JSON.
			 *
			 * @return array<string,string>
			 */
			public function jsonSerialize(): array {
				return ['id' => $this->id, 'name' => 'Serializable'];
			}//end jsonSerialize()
		};

		$mockMapper = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['find'])
			->getMock();
		$mockMapper->method('find')->willReturn($mockObj);

		$mockObjectService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getMapper'])
			->getMock();
		$mockObjectService->method('getMapper')->willReturn($mockMapper);

		$this->container->method('get')->willReturn($mockObjectService);

		$this->config->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default): string {
					if ($key === 'character_register') {
						return 'reg-1';
					}

					if ($key === 'character_schema') {
						return 'sch-1';
					}

					return $default;
				}
			);

		$result = $this->service->getObject('character', $testUuid);

		self::assertSame(expected: $testUuid, actual: $result['id']);
		self::assertSame(expected: 'Serializable', actual: $result['name']);

	}//end testGetObjectHandlesJsonSerializableObjects()

	/**
	 * Test that the legacy IAppConfig fallback path is taken when the OpenRegister
	 * RegisterResolverService class is absent from the classpath.
	 *
	 * In the unit-test environment OpenRegister is not autoloadable, so
	 * `class_exists('OCA\OpenRegister\Service\RegisterResolverService')` is false
	 * and resolveRegisterAndSchema() MUST resolve register/schema IDs via
	 * IAppConfig::getValueString rather than the resolver. This guards the
	 * BC-safe fallback required by the resolver-absence scenario of
	 * larpingapp-adopt-or-abstractions.
	 *
	 * @return void
	 */
	public function testFallsBackToAppConfigWhenResolverAbsent(): void {
		$this->appManager->method('getInstalledApps')->willReturn(['openregister']);

		$testUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

		$mockMapper = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['find'])
			->getMock();
		$mockMapper->method('find')->willReturn(['id' => $testUuid, 'name' => 'Fallback']);

		$mockObjectService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getMapper'])
			->getMock();
		$mockObjectService->method('getMapper')->willReturn($mockMapper);

		$this->container->method('get')->willReturn($mockObjectService);

		// The fallback path MUST consult IAppConfig::getValueString for the
		// _register and _schema keys. We capture the keys it was called with and
		// assert both appear (proving the legacy resolution path ran).
		$seenKeys = [];
		$this->config->expects($this->atLeastOnce())
			->method('getValueString')
			->willReturnCallback(
				function (string $app, string $key, string $default) use (&$seenKeys): string {
					$seenKeys[] = $key;
					if ($key === 'character_register') {
						return 'reg-7';
					}

					if ($key === 'character_schema') {
						return 'sch-7';
					}

					return $default;
				}
			);

		$result = $this->service->getObject('character', $testUuid);

		self::assertSame(expected: $testUuid, actual: $result['id']);
		self::assertContains(needle: 'character_register', haystack: $seenKeys);
		self::assertContains(needle: 'character_schema', haystack: $seenKeys);

	}//end testFallsBackToAppConfigWhenResolverAbsent()
}//end class
