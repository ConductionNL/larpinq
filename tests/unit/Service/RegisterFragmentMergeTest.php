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

    /**
     * The event-checkin-roster fragment adds the larping_attendance schema
     * over the real monolith without removing any existing schema or property.
     *
     * @return void
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    public function testEventCheckinRosterFragmentAddsAttendanceSchema(): void
    {
        $baseDir  = dirname(__DIR__, 3);
        $monolith = json_decode((string) file_get_contents($baseDir.'/lib/Settings/larpingapp_register.json'), true);
        $fragment = json_decode((string) file_get_contents($baseDir.'/lib/Settings/register.d/event-checkin-roster.json'), true);

        $this->assertIsArray($monolith, 'monolith register JSON must parse');
        $this->assertIsArray($fragment, 'fragment JSON must parse');

        $existingSchemaNames = array_keys($monolith['components']['schemas']);

        $merged      = $this->deepMerge($monolith, $fragment);
        $mergedNames = array_keys($merged['components']['schemas']);

        // No existing schema is removed by the merge.
        foreach ($existingSchemaNames as $name) {
            $this->assertContains($name, $mergedNames, "existing schema {$name} must survive the merge");
        }

        // The attendance schema is added with the namespaced slug.
        $this->assertArrayHasKey('attendance', $merged['components']['schemas']);
        $attendance = $merged['components']['schemas']['attendance'];
        $this->assertSame('larping_attendance', $attendance['slug']);

        // All five properties are present, each with a non-empty title (gate-28).
        foreach (['event', 'character', 'status', 'checkedInAt', 'checkedInBy'] as $prop) {
            $this->assertArrayHasKey($prop, $attendance['properties'], "property {$prop} present");
            $this->assertNotEmpty($attendance['properties'][$prop]['title'], "property {$prop} has a title");
        }

        // Status is constrained to the three attendance states.
        $this->assertSame(
            ['registered', 'checked-in', 'no-show'],
            $attendance['properties']['status']['enum']
        );

        // GM-group write RBAC is delegated to OpenRegister on the schema.
        $this->assertSame(['gamemasters'], $attendance['authorization']['create']);

        // A representative existing schema keeps all its properties.
        $this->assertArrayHasKey(
            'awardedBy',
            $merged['components']['schemas']['xpAward']['properties'],
            'xpAward.awardedBy must survive the merge'
        );

    }//end testEventCheckinRosterFragmentAddsAttendanceSchema()
}//end class
