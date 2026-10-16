<?php

/**
 * Factions, memberships and relationships on the merged register
 * (characters-factions-and-relationships).
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
 * @spec openspec/specs/character-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-CFR-001 to REQ-CFR-005 of characters-factions-and-relationships.
 */
class FactionsFragmentTest extends TestCase {

	private const GM = ['group' => 'gamemasters'];

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
	 * A larpers rule with a match.
	 *
	 * @param array<string, string> $match The match.
	 *
	 * @return array<string, mixed> The rule.
	 */
	private function larpers(array $match): array {
		return ['group' => 'larpers', 'match' => $match];
	}//end larpers()

	/**
	 * The three schemas are in the register and exportable.
	 *
	 * @return void
	 */
	public function testTheConnectionSchemasAreInTheRegister(): void {
		$register = $this->mergedRegister()['components']['registers']['larpinq'];
		foreach (['faction' => 'larping_faction', 'factionMember' => 'larping_faction_member', 'relationship' => 'larping_relationship'] as $key => $slug) {
			$this->assertContains($slug, $register['schemas']);
			$this->assertSame($slug, $this->schema(key: $key)['slug']);
			$this->assertTrue($this->schema(key: $key)['configuration']['exportable']);
		}
	}//end testTheConnectionSchemasAreInTheRegister()

	/**
	 * A secret faction is read by game masters and its leader only; players
	 * create and run groups, never factions (REQ-CFR-002, REQ-CFR-003).
	 *
	 * @return void
	 */
	public function testSecretFactionsStaySecretAndPlayersRunOnlyGroups(): void {
		$faction = $this->schema(key: 'faction');

		$this->assertSame(['faction', 'group'], $faction['properties']['kind']['enum']);
		$this->assertSame(['open', 'secret'], $faction['properties']['visibility']['enum']);
		$this->assertSame(
			[self::GM, $this->larpers(match: ['visibility' => 'open']), $this->larpers(match: ['leaderOwnerUid' => '$userId'])],
			$faction['authorization']['read']
		);
		$this->assertSame([self::GM, $this->larpers(match: ['kind' => 'group'])], $faction['authorization']['create']);
		$mine = $this->larpers(match: ['kind' => 'group', 'leaderOwnerUid' => '$userId']);
		$this->assertSame([self::GM, $mine], $faction['authorization']['update']);
		$this->assertSame([self::GM, $mine], $faction['authorization']['delete']);
		$this->assertSame(
			['type' => 'string', 'materialise' => true, 'expression' => ['prop' => '@ref.leader.ownerUid']],
			$faction['properties']['leaderOwnerUid']['calculation']
		);
	}//end testSecretFactionsStaySecretAndPlayersRunOnlyGroups()

	/**
	 * A membership is read by game masters, by anyone for an open faction, by
	 * the member's player and by the group leader; it is never deleted by a
	 * player, so the history stays (REQ-CFR-002, REQ-CFR-004).
	 *
	 * @return void
	 */
	public function testMembershipsKeepTheirHistoryAndHideSecretOnes(): void {
		$member = $this->schema(key: 'factionMember');

		$this->assertSame(
			[
				self::GM,
				$this->larpers(match: ['factionVisibility' => 'open']),
				$this->larpers(match: ['ownerUid' => '$userId']),
				$this->larpers(match: ['groupLeaderUid' => '$userId']),
			],
			$member['authorization']['read']
		);
		$this->assertSame([self::GM, 'larpers'], $member['authorization']['create']);
		$this->assertSame(
			[self::GM, $this->larpers(match: ['ownerUid' => '$userId']), $this->larpers(match: ['groupLeaderUid' => '$userId'])],
			$member['authorization']['update']
		);
		$this->assertSame(['gamemasters'], $member['authorization']['delete']);
		$this->assertSame(
			['requested', 'invited', 'active', 'declined', 'left', 'removed'],
			$member['properties']['status']['enum']
		);
		$this->assertSame(['leader', 'member'], $member['properties']['role']['enum']);

		$expected = [
			'ownerUid' => '@ref.character.ownerUid',
			'groupLeaderUid' => '@ref.faction.leaderOwnerUid',
			'factionVisibility' => '@ref.faction.visibility',
			'factionName' => '@ref.faction.name',
		];
		foreach ($expected as $field => $prop) {
			$this->assertSame(
				['type' => 'string', 'materialise' => true, 'expression' => ['prop' => $prop]],
				$member['properties'][$field]['calculation'],
				"{$field} is copied from {$prop}"
			);
		}

		$references = $member['configuration']['x-openregister-references'];
		$this->assertSame('character', $references['character']['schema']);
		$this->assertSame('larping_faction', $references['faction']['schema']);
	}//end testMembershipsKeepTheirHistoryAndHideSecretOnes()

	/**
	 * Accept, decline, leave and remove move the status, and nothing deletes.
	 *
	 * @return void
	 */
	public function testTheMembershipLifecycle(): void {
		$lifecycle = $this->schema(key: 'factionMember')['configuration']['x-openregister-lifecycle'];

		$this->assertSame('status', $lifecycle['field']);
		$this->assertSame('requested', $lifecycle['initial']);
		$this->assertSame(['requested', 'invited'], $lifecycle['transitions']['accept']['from']);
		$this->assertSame('active', $lifecycle['transitions']['accept']['to']);
		$this->assertSame('declined', $lifecycle['transitions']['decline']['to']);
		$this->assertSame(['active'], $lifecycle['transitions']['leave']['from']);
		$this->assertSame('left', $lifecycle['transitions']['leave']['to']);
		$this->assertSame('removed', $lifecycle['transitions']['remove']['to']);
	}//end testTheMembershipLifecycle()

	/**
	 * A relationship known to owners is read by both players; one known to
	 * game masters by game masters only (REQ-CFR-005).
	 *
	 * @return void
	 */
	public function testRelationshipsAreReadByTheOwnersOfBothCharactersOnly(): void {
		$relationship = $this->schema(key: 'relationship');

		$this->assertSame(
			['family', 'ally', 'rival', 'romance', 'enemy', 'mentor', 'other'],
			$relationship['properties']['kind']['enum']
		);
		$this->assertSame(['gamemasters', 'owners'], $relationship['properties']['knownTo']['enum']);
		$this->assertSame('gamemasters', $relationship['properties']['knownTo']['default']);
		$this->assertSame(
			[
				self::GM,
				$this->larpers(match: ['knownTo' => 'owners', 'fromOwnerUid' => '$userId']),
				$this->larpers(match: ['knownTo' => 'owners', 'toOwnerUid' => '$userId']),
			],
			$relationship['authorization']['read']
		);
		$this->assertSame([self::GM, $this->larpers(match: ['knownTo' => 'owners'])], $relationship['authorization']['create']);
		$this->assertSame(
			[self::GM, $this->larpers(match: ['knownTo' => 'owners', 'fromOwnerUid' => '$userId'])],
			$relationship['authorization']['update']
		);
		$this->assertSame('@ref.from.ownerUid', $relationship['properties']['fromOwnerUid']['calculation']['expression']['prop']);
		$this->assertSame('@ref.to.ownerUid', $relationship['properties']['toOwnerUid']['calculation']['expression']['prop']);
	}//end testRelationshipsAreReadByTheOwnersOfBothCharactersOnly()

	/**
	 * The import writes a schema key for each of the three schemas, so the
	 * membership guard finds them in production and the settings page keeps them.
	 *
	 * @return void
	 */
	public function testTheImportConfiguresTheConnectionSchemas(): void {
		$slugs = (new ReflectionClass(\OCA\Larpinq\Service\SettingsLoadService::class))->getConstant('OBJECT_TYPE_SCHEMA_SLUGS');
		$keys = (new ReflectionClass(\OCA\Larpinq\Service\SettingsService::class))->getConstant('CONFIG_KEYS');
		$schemas = $this->mergedRegister()['components']['schemas'];
		foreach (['faction' => 'faction', 'factionmember' => 'factionMember', 'relationship' => 'relationship'] as $type => $key) {
			$this->assertSame($schemas[$key]['slug'], $slugs[$type] ?? null, "the import must configure {$type}_schema");
			$this->assertContains($type . '_schema', $keys);
			$this->assertContains($type . '_register', $keys);
		}
	}//end testTheImportConfiguresTheConnectionSchemas()

	/**
	 * No schema carries a `user` format: OpenRegister drops a schema that does.
	 *
	 * @return void
	 */
	public function testNoUserFormat(): void {
		foreach (['faction', 'factionMember', 'relationship'] as $key) {
			foreach ($this->schema(key: $key)['properties'] as $name => $property) {
				$this->assertNotSame('user', ($property['format'] ?? null), "{$key}.{$name}");
			}
		}
	}//end testNoUserFormat()

	/**
	 * The seed factions, memberships and relationships validate against the
	 * real schemas, three or more per schema (gate-101).
	 *
	 * @return void
	 */
	public function testTheSeedObjectsValidate(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$bySlug = ['larping_faction' => 'faction', 'larping_faction_member' => 'factionMember', 'larping_relationship' => 'relationship'];
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

		ksort($seen);
		$this->assertSame(['larping_faction' => 3, 'larping_faction_member' => 4, 'larping_relationship' => 3], $seen);
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
