<?php

/**
 * Unit tests for SettingsMapBuilder.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\SettingsMapBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SettingsMapBuilder.
 */
class SettingsMapBuilderTest extends TestCase
{

    private SettingsMapBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SettingsMapBuilder();
    }

    public function testBuildSchemaSlugMapFromArrays(): void
    {
        $schemas = [
            ['slug' => 'character', 'id' => 'schema-1'],
            ['slug' => 'skill', 'id' => 'schema-2'],
        ];

        $result = $this->builder->buildSchemaSlugMap($schemas);

        self::assertSame('schema-1', $result['character']);
        self::assertSame('schema-2', $result['skill']);
    }

    public function testBuildSchemaSlugMapSkipsEntriesWithoutSlug(): void
    {
        $schemas = [
            ['id' => 'schema-1'], // No slug.
            ['slug' => 'skill', 'id' => 'schema-2'],
        ];

        $result = $this->builder->buildSchemaSlugMap($schemas);

        self::assertCount(1, $result);
        self::assertSame('schema-2', $result['skill']);
    }

    public function testBuildSchemaSlugMapUsesUuidAsFallback(): void
    {
        $schemas = [
            ['slug' => 'character', 'uuid' => 'uuid-1'],
        ];

        $result = $this->builder->buildSchemaSlugMap($schemas);

        self::assertSame('uuid-1', $result['character']);
    }

    public function testBuildSchemaSlugMapFromJsonSerializable(): void
    {
        $mockSchema = new class implements \JsonSerializable {

            public function jsonSerialize(): array
            {
                return ['slug' => 'event', 'id' => 'schema-evt'];
            }
        };

        $result = $this->builder->buildSchemaSlugMap([$mockSchema]);

        self::assertSame('schema-evt', $result['event']);
    }

    public function testBuildSchemaSlugMapReturnsEmptyForEmptyInput(): void
    {
        $result = $this->builder->buildSchemaSlugMap([]);

        self::assertEmpty($result);
    }

    public function testFindRegisterIdBySlugMatchesLarpingapp(): void
    {
        $registers = [
            ['slug' => 'other-app', 'id' => 'reg-1'],
            ['slug' => 'larpingapp', 'id' => 'reg-2'],
        ];

        $result = $this->builder->findRegisterIdBySlug($registers);

        self::assertSame('reg-2', $result);
    }

    public function testFindRegisterIdBySlugReturnsNullWhenNotFound(): void
    {
        $registers = [
            ['slug' => 'other-app', 'id' => 'reg-1'],
        ];

        $result = $this->builder->findRegisterIdBySlug($registers);

        self::assertNull($result);
    }

    public function testFindRegisterIdBySlugReturnsNullForEmptyInput(): void
    {
        $result = $this->builder->findRegisterIdBySlug([]);

        self::assertNull($result);
    }

    public function testFindRegisterIdBySlugSkipsEntriesWithoutSlug(): void
    {
        $registers = [
            ['id' => 'reg-1'], // No slug.
        ];

        $result = $this->builder->findRegisterIdBySlug($registers);

        self::assertNull($result);
    }
}
