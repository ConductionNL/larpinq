<?php

/**
 * Unit tests for the ADR-037 register fragment deep-merge.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the private static deepMergeConfig() that powers ADR-037 modular
 * register fragments. Disjoint fragments must union by key, lists concatenate,
 * and scalars overwrite — the OpenAPI assembly semantics that make concurrent
 * same-app builds conflict-free.
 */
class RegisterFragmentMergeTest extends TestCase
{
    /**
     * Invoke the private static ConfigFileLoaderService::deepMergeConfig().
     *
     * @param array<mixed> $base    The base array.
     * @param array<mixed> $overlay The overlay array.
     *
     * @return array<mixed> The merged result.
     */
    private function deepMerge(array $base, array $overlay): array
    {
        $method = new ReflectionMethod(ConfigFileLoaderService::class, 'deepMergeConfig');
        $method->setAccessible(true);

        // @var array<mixed> $result
        $result = $method->invoke(null, $base, $overlay);
        return $result;

    }//end deepMerge()

    /**
     * Disjoint schema keys union into a single map.
     *
     * @return void
     */
    public function testDisjointSchemasUnionByKey(): void
    {
        $base    = ['components' => ['schemas' => ['Character' => ['type' => 'object']]]];
        $overlay = ['components' => ['schemas' => ['Quest' => ['type' => 'object']]]];

        $merged = $this->deepMerge($base, $overlay);

        $this->assertArrayHasKey('Character', $merged['components']['schemas']);
        $this->assertArrayHasKey('Quest', $merged['components']['schemas']);
        $this->assertSame(['type' => 'object'], $merged['components']['schemas']['Character']);
        $this->assertSame(['type' => 'object'], $merged['components']['schemas']['Quest']);

    }//end testDisjointSchemasUnionByKey()

    /**
     * List arrays concatenate rather than overwrite.
     *
     * @return void
     */
    public function testListArraysConcatenate(): void
    {
        $base    = ['required' => ['name']];
        $overlay = ['required' => ['level']];

        $merged = $this->deepMerge($base, $overlay);

        $this->assertSame(['name', 'level'], $merged['required']);

    }//end testListArraysConcatenate()

    /**
     * Scalars in the overlay overwrite the base.
     *
     * @return void
     */
    public function testScalarOverlayOverwrites(): void
    {
        $base    = ['info' => ['version' => '1.0.0', 'title' => 'LarpingApp']];
        $overlay = ['info' => ['version' => '1.1.0']];

        $merged = $this->deepMerge($base, $overlay);

        $this->assertSame('1.1.0', $merged['info']['version']);
        $this->assertSame('LarpingApp', $merged['info']['title']);

    }//end testScalarOverlayOverwrites()

    /**
     * Nested objects merge recursively, preserving sibling keys at every depth.
     *
     * @return void
     */
    public function testNestedMergePreservesSiblings(): void
    {
        $base    = [
            'components' => [
                'schemas' => ['Character' => ['type' => 'object']],
                'paths'   => ['/characters' => ['get' => []]],
            ],
        ];
        $overlay = [
            'components' => [
                'schemas' => ['Item' => ['type' => 'object']],
            ],
        ];

        $merged = $this->deepMerge($base, $overlay);

        $this->assertArrayHasKey('Character', $merged['components']['schemas']);
        $this->assertArrayHasKey('Item', $merged['components']['schemas']);
        $this->assertArrayHasKey('/characters', $merged['components']['paths']);

    }//end testNestedMergePreservesSiblings()
}//end class
