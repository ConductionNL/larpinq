<?php

/**
 * Unit tests for SettingsMapBuilder.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\SettingsMapBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SettingsMapBuilder.
 */
class SettingsMapBuilderTest extends TestCase {

	/** @var SettingsMapBuilder The builder. */
	private SettingsMapBuilder $builder;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->builder = new SettingsMapBuilder();
	}

	/**
	 * Build schema slug map from arrays.
	 *
	 * @return void
	 */
	public function testBuildSchemaSlugMapFromArrays(): void {
		$schemas = [
			['slug' => 'character', 'id' => 'schema-1'],
			['slug' => 'skill', 'id' => 'schema-2'],
		];

		$result = $this->builder->buildSchemaSlugMap($schemas);

		self::assertSame(expected: 'schema-1', actual: $result['character']);
		self::assertSame(expected: 'schema-2', actual: $result['skill']);
	}

	/**
	 * Build schema slug map skips entries without slug.
	 *
	 * @return void
	 */
	public function testBuildSchemaSlugMapSkipsEntriesWithoutSlug(): void {
		$schemas = [
			['id' => 'schema-1'], // No slug.
			['slug' => 'skill', 'id' => 'schema-2'],
		];

		$result = $this->builder->buildSchemaSlugMap($schemas);

		self::assertCount(expectedCount: 1, haystack: $result);
		self::assertSame(expected: 'schema-2', actual: $result['skill']);
	}

	/**
	 * Build schema slug map uses UUID as fallback.
	 *
	 * @return void
	 */
	public function testBuildSchemaSlugMapUsesUuidAsFallback(): void {
		$schemas = [
			['slug' => 'character', 'uuid' => 'uuid-1'],
		];

		$result = $this->builder->buildSchemaSlugMap($schemas);

		self::assertSame(expected: 'uuid-1', actual: $result['character']);
	}

	/**
	 * Build schema slug map from JSON serializable.
	 *
	 * @return void
	 */
	public function testBuildSchemaSlugMapFromJsonSerializable(): void {
		$mockSchema = new class implements \JsonSerializable {

			public function jsonSerialize(): array {
				return ['slug' => 'event', 'id' => 'schema-evt'];
			}
		};

		$result = $this->builder->buildSchemaSlugMap([$mockSchema]);

		self::assertSame(expected: 'schema-evt', actual: $result['event']);
	}

	/**
	 * Build schema slug map returns empty for empty input.
	 *
	 * @return void
	 */
	public function testBuildSchemaSlugMapReturnsEmptyForEmptyInput(): void {
		$result = $this->builder->buildSchemaSlugMap([]);

		self::assertEmpty(actual: $result);
	}

	/**
	 * Find register id by slug matches larpingapp.
	 *
	 * @return void
	 */
	public function testFindRegisterIdBySlugMatchesLarpingapp(): void {
		$registers = [
			['slug' => 'other-app', 'id' => 'reg-1'],
			['slug' => 'larpinq', 'id' => 'reg-2'],
		];

		$result = $this->builder->findRegisterIdBySlug($registers);

		self::assertSame(expected: 'reg-2', actual: $result);
	}

	/**
	 * Find register id by slug returns null when not found.
	 *
	 * @return void
	 */
	public function testFindRegisterIdBySlugReturnsNullWhenNotFound(): void {
		$registers = [
			['slug' => 'other-app', 'id' => 'reg-1'],
		];

		$result = $this->builder->findRegisterIdBySlug($registers);

		self::assertNull(actual: $result);
	}

	/**
	 * Find register id by slug returns null for empty input.
	 *
	 * @return void
	 */
	public function testFindRegisterIdBySlugReturnsNullForEmptyInput(): void {
		$result = $this->builder->findRegisterIdBySlug([]);

		self::assertNull(actual: $result);
	}

	/**
	 * Find register id by slug skips entries without slug.
	 *
	 * @return void
	 */
	public function testFindRegisterIdBySlugSkipsEntriesWithoutSlug(): void {
		$registers = [
			['id' => 'reg-1'], // No slug.
		];

		$result = $this->builder->findRegisterIdBySlug($registers);

		self::assertNull(actual: $result);
	}
}
