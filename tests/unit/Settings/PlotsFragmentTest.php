<?php

/**
 * Plots, plot parts and the writing fields on the merged register
 * (characters-plot-threads-and-writing).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes, and
 * validates the seed objects of the mock register against the merged schemas
 * with Opis, the validator OpenRegister uses.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/story-writing/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-CPW-001 to REQ-CPW-003 of characters-plot-threads-and-writing.
 */
class PlotsFragmentTest extends TestCase {

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$this->assertIsArray($monolith);

		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($loader, $monolith, $appPath);
		return $merged;
	}//end mergedRegister()

	/**
	 * One merged schema.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function schema(string $key): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey($key, $schemas, "the {$key} schema must be in the merged register");
		return $schemas[$key];
	}//end schema()

	/**
	 * Both schemas are in the register, exportable, and only game masters
	 * touch a plot.
	 *
	 * @return void
	 */
	public function testThePlotSchemasAreInTheRegister(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];
		$this->assertContains('larping_plot', $register['schemas']);
		$this->assertContains('larping_plot_part', $register['schemas']);

		$plot = $this->schema(key: 'plot');
		$this->assertSame('larping_plot', $plot['slug']);
		$this->assertTrue($plot['configuration']['exportable']);
		foreach (['read', 'create', 'update', 'delete'] as $action) {
			$this->assertSame(['gamemasters'], $plot['authorization'][$action], "only game masters may {$action} a plot");
		}

		$this->assertSame('larping_plot_part', $this->schema(key: 'plotPart')['slug']);
		$this->assertTrue($this->schema(key: 'plotPart')['configuration']['exportable']);
	}//end testThePlotSchemasAreInTheRegister()

	/**
	 * A plot moves through ready, approve and reopen on its writing step.
	 *
	 * @return void
	 */
	public function testThePlotStepHasItsTransitions(): void {
		$lifecycle = $this->schema(key: 'plot')['configuration']['x-openregister-lifecycle'];

		$this->assertSame('writingStep', $lifecycle['field']);
		$this->assertSame('draft', $lifecycle['initial']);
		$this->assertSame(['draft'], $lifecycle['transitions']['ready']['from']);
		$this->assertSame('ready', $lifecycle['transitions']['ready']['to']);
		$this->assertSame('approved', $lifecycle['transitions']['approve']['to']);
		$this->assertSame('draft', $lifecycle['transitions']['reopen']['to']);
	}//end testThePlotStepHasItsTransitions()

	/**
	 * A player reads the parts of their own character, and never a private text.
	 *
	 * @return void
	 */
	public function testAPlayerReadsOnlyTheirOwnPartWithoutThePrivateText(): void {
		$part = $this->schema(key: 'plotPart');

		$this->assertSame(
			[['group' => 'gamemasters'], ['group' => 'larpers', 'match' => ['ownerUid' => '$userId']]],
			$part['authorization']['read']
		);
		$this->assertSame(['gamemasters'], $part['properties']['privateText']['authorization']['read']);
		$this->assertArrayNotHasKey('authorization', $part['properties']['playerText']);
		foreach (['create', 'update', 'delete'] as $action) {
			$this->assertSame(['gamemasters'], $part['authorization'][$action]);
		}
	}//end testAPlayerReadsOnlyTheirOwnPartWithoutThePrivateText()

	/**
	 * The part's owner is copied from its character on every save, so the row
	 * rule can match it.
	 *
	 * @return void
	 */
	public function testTheOwnerIsCopiedFromTheCharacter(): void {
		$part = $this->schema(key: 'plotPart');

		$this->assertSame(
			['mode' => 'relatedObject', 'schema' => 'character', 'field' => 'character'],
			$part['configuration']['x-openregister-references']['character']
		);
		$this->assertSame(
			['type' => 'string', 'materialise' => true, 'expression' => ['prop' => '@ref.character.ownerUid']],
			$part['properties']['ownerUid']['calculation']
		);
	}//end testTheOwnerIsCopiedFromTheCharacter()

	/**
	 * A character carries a writer and a writing step that only game masters
	 * read and change, and keeps its approval lifecycle.
	 *
	 * @return void
	 */
	public function testACharacterHasAWriterAndAWritingStep(): void {
		$character = $this->schema(key: 'character');

		// A Nextcloud user id as a plain string: OpenRegister has no `user`
		// format and drops the whole schema when it meets one.
		$this->assertArrayNotHasKey('format', $character['properties']['writer']);
		$this->assertArrayNotHasKey('format', $this->schema(key: 'plot')['properties']['writer']);
		$this->assertSame(['draft', 'ready', 'approved'], $character['properties']['writingStep']['enum']);
		$this->assertSame('draft', $character['properties']['writingStep']['default']);
		foreach (['writer', 'writingStep'] as $field) {
			$this->assertSame(['gamemasters'], $character['properties'][$field]['authorization']['read']);
			$this->assertSame(['gamemasters'], $character['properties'][$field]['authorization']['update']);
		}
		$this->assertSame('approved', $character['configuration']['x-openregister-lifecycle']['field']);
	}//end testACharacterHasAWriterAndAWritingStep()

	/**
	 * The seed plot and parts validate against the real schemas.
	 *
	 * @return void
	 */
	public function testTheSeedObjectsValidate(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$bySlug = ['larping_plot' => 'plot', 'larping_plot_part' => 'plotPart'];
		$validator = new Validator();
		$seen = [];
		foreach ($mock['components']['objects'] as $object) {
			$slug = $object['@self']['schema'] ?? '';
			if (isset($bySlug[$slug]) === false) {
				continue;
			}
			$seen[$slug] = ($seen[$slug] ?? 0) + 1;
			$schema = json_decode((string)json_encode($this->forOpis(schema: $this->schema(key: $bySlug[$slug]))));
			unset($object['@self']);
			$result = $validator->validate(json_decode((string)json_encode($object)), $schema);
			$this->assertTrue($result->isValid(), "seed {$slug} must validate");
		}
		// ADR-111 rule 1 (gate-101): at least three demo objects per schema.
		$this->assertSame(['larping_plot' => 3, 'larping_plot_part' => 3], $seen);
	}//end testTheSeedObjectsValidate()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string, and the OpenRegister-only keys are not JSON Schema.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed> The JSON Schema.
	 */
	private function forOpis(array $schema): array {
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
