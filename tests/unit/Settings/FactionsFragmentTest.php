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
		$this->assertIsArray(actual: $monolith);

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
		$this->assertArrayHasKey(key: $key, array: $schemas, message: "the {$key} schema must be in the merged register");
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
			$this->assertContains(needle: $slug, haystack: $register['schemas']);
			$this->assertSame(expected: $slug, actual: $this->schema(key: $key)['slug']);
			$this->assertTrue(condition: $this->schema(key: $key)['configuration']['exportable']);
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

		$this->assertSame(expected: ['faction', 'group'], actual: $faction['properties']['kind']['enum']);
		$this->assertSame(expected: ['open', 'secret'], actual: $faction['properties']['visibility']['enum']);
		$this->assertSame(
			expected: [self::GM, $this->larpers(match: ['visibility' => 'open']), $this->larpers(match: ['leaderOwnerUid' => '$userId'])],
			actual: $faction['authorization']['read']
		);
		$this->assertSame(expected: [self::GM, $this->larpers(match: ['kind' => 'group'])], actual: $faction['authorization']['create']);
		$mine = $this->larpers(match: ['kind' => 'group', 'leaderOwnerUid' => '$userId']);
		$this->assertSame(expected: [self::GM, $mine], actual: $faction['authorization']['update']);
		$this->assertSame(expected: [self::GM, $mine], actual: $faction['authorization']['delete']);
		$this->assertSame(
			expected: ['type' => 'string', 'materialise' => true, 'expression' => ['prop' => '@ref.leader.ownerUid']],
			actual: $faction['properties']['leaderOwnerUid']['calculation']
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
			expected: [
				self::GM,
				$this->larpers(match: ['factionVisibility' => 'open']),
				$this->larpers(match: ['ownerUid' => '$userId']),
				$this->larpers(match: ['groupLeaderUid' => '$userId']),
			],
			actual: $member['authorization']['read']
		);
		$this->assertSame(expected: [self::GM, 'larpers'], actual: $member['authorization']['create']);
		$this->assertSame(
			expected: [self::GM, $this->larpers(match: ['ownerUid' => '$userId']), $this->larpers(match: ['groupLeaderUid' => '$userId'])],
			actual: $member['authorization']['update']
		);
		$this->assertSame(expected: ['gamemasters'], actual: $member['authorization']['delete']);
		$this->assertSame(
			expected: ['requested', 'invited', 'active', 'declined', 'left', 'removed'],
			actual: $member['properties']['status']['enum']
		);
		$this->assertSame(expected: ['leader', 'member'], actual: $member['properties']['role']['enum']);

		$expected = [
			'ownerUid' => '@ref.character.ownerUid',
			'groupLeaderUid' => '@ref.faction.leaderOwnerUid',
			'factionVisibility' => '@ref.faction.visibility',
			'factionName' => '@ref.faction.name',
		];
		foreach ($expected as $field => $prop) {
			$this->assertSame(
				expected: ['type' => 'string', 'materialise' => true, 'expression' => ['prop' => $prop]],
				actual: $member['properties'][$field]['calculation'],
				message: "{$field} is copied from {$prop}"
			);
		}

		$references = $member['configuration']['x-openregister-references'];
		$this->assertSame(expected: 'character', actual: $references['character']['schema']);
		$this->assertSame(expected: 'larping_faction', actual: $references['faction']['schema']);
	}//end testMembershipsKeepTheirHistoryAndHideSecretOnes()

	/**
	 * Accept, decline, leave and remove move the status, and nothing deletes.
	 *
	 * @return void
	 */
	public function testTheMembershipLifecycle(): void {
		$lifecycle = $this->schema(key: 'factionMember')['configuration']['x-openregister-lifecycle'];

		$this->assertSame(expected: 'status', actual: $lifecycle['field']);
		$this->assertSame(expected: 'requested', actual: $lifecycle['initial']);
		$this->assertSame(expected: ['requested', 'invited'], actual: $lifecycle['transitions']['accept']['from']);
		$this->assertSame(expected: 'active', actual: $lifecycle['transitions']['accept']['to']);
		$this->assertSame(expected: 'declined', actual: $lifecycle['transitions']['decline']['to']);
		$this->assertSame(expected: ['active'], actual: $lifecycle['transitions']['leave']['from']);
		$this->assertSame(expected: 'left', actual: $lifecycle['transitions']['leave']['to']);
		$this->assertSame(expected: 'removed', actual: $lifecycle['transitions']['remove']['to']);
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
			expected: ['family', 'ally', 'rival', 'romance', 'enemy', 'mentor', 'other'],
			actual: $relationship['properties']['kind']['enum']
		);
		$this->assertSame(expected: ['gamemasters', 'owners'], actual: $relationship['properties']['knownTo']['enum']);
		$this->assertSame(expected: 'gamemasters', actual: $relationship['properties']['knownTo']['default']);
		$this->assertSame(
			expected: [
				self::GM,
				$this->larpers(match: ['knownTo' => 'owners', 'fromOwnerUid' => '$userId']),
				$this->larpers(match: ['knownTo' => 'owners', 'toOwnerUid' => '$userId']),
			],
			actual: $relationship['authorization']['read']
		);
		$this->assertSame(expected: [self::GM, $this->larpers(match: ['knownTo' => 'owners'])], actual: $relationship['authorization']['create']);
		$this->assertSame(
			expected: [self::GM, $this->larpers(match: ['knownTo' => 'owners', 'fromOwnerUid' => '$userId'])],
			actual: $relationship['authorization']['update']
		);
		$this->assertSame(expected: '@ref.from.ownerUid', actual: $relationship['properties']['fromOwnerUid']['calculation']['expression']['prop']);
		$this->assertSame(expected: '@ref.to.ownerUid', actual: $relationship['properties']['toOwnerUid']['calculation']['expression']['prop']);
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
			$this->assertSame(expected: $schemas[$key]['slug'], actual: $slugs[$type] ?? null, message: "the import must configure {$type}_schema");
			$this->assertContains(needle: $type . '_schema', haystack: $keys);
			$this->assertContains(needle: $type . '_register', haystack: $keys);
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
				$this->assertNotSame(expected: 'user', actual: ($property['format'] ?? null), message: "{$key}.{$name}");
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
			$this->assertTrue(condition: $result->isValid(), message: "seed {$slug} must validate");
		}

		ksort($seen);
		$this->assertSame(expected: ['larping_faction' => 3, 'larping_faction_member' => 4, 'larping_relationship' => 3], actual: $seen);
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
