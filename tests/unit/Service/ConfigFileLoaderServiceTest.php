<?php

/**
 * Unit tests for ConfigFileLoaderService.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for ConfigFileLoaderService.
 */
class ConfigFileLoaderServiceTest extends TestCase {

	/** @var ConfigFileLoaderService The service. */
	private ConfigFileLoaderService $service;
	/** @var IAppManager The app manager. */
	private IAppManager $appManager;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->service = new ConfigFileLoaderService(appManager: $this->appManager);
	}

	/**
	 * Load configuration file throws when file not found.
	 *
	 * @return void
	 */
	public function testLoadConfigurationFileThrowsWhenFileNotFound(): void {
		$this->appManager->method('getAppPath')
			->with('larpinq')
			->willReturn('/nonexistent/path');

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionMessage(message: 'Configuration file not found');

		$this->service->loadConfigurationFile();
	}

	/**
	 * Ensure source type sets default source type.
	 *
	 * @return void
	 */
	public function testEnsureSourceTypeSetsDefaultSourceType(): void {
		$data = [];

		$result = $this->service->ensureSourceType($data);

		self::assertArrayHasKey(key: 'x-openregister', array: $result);
		self::assertSame(expected: 'local', actual: $result['x-openregister']['sourceType']);
	}

	/**
	 * Ensure source type preserves existing source type.
	 *
	 * @return void
	 */
	public function testEnsureSourceTypePreservesExistingSourceType(): void {
		$data = [
			'x-openregister' => [
				'sourceType' => 'remote',
			],
		];

		$result = $this->service->ensureSourceType($data);

		self::assertSame(expected: 'remote', actual: $result['x-openregister']['sourceType']);
	}

	/**
	 * Ensure source type handles non array openregister.
	 *
	 * @return void
	 */
	public function testEnsureSourceTypeHandlesNonArrayOpenregister(): void {
		$data = [
			'x-openregister' => 'invalid',
		];

		$result = $this->service->ensureSourceType($data);

		self::assertIsArray(actual: $result['x-openregister']);
		self::assertSame(expected: 'local', actual: $result['x-openregister']['sourceType']);
	}

	/**
	 * Ensure source type adds to existing openregister.
	 *
	 * @return void
	 */
	public function testEnsureSourceTypeAddsToExistingOpenregister(): void {
		$data = [
			'x-openregister' => [
				'otherKey' => 'value',
			],
		];

		$result = $this->service->ensureSourceType($data);

		self::assertSame(expected: 'local', actual: $result['x-openregister']['sourceType']);
		self::assertSame(expected: 'value', actual: $result['x-openregister']['otherKey']);
	}
}
