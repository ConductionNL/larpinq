<?php

/**
 * The character to player to Nextcloud user chain (character-player-picker),
 * as the schemas declare it and the demo data shows it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/character-player-picker/design.md#seed-data
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A character names its player, the player names a Nextcloud user, and the
 * character's ownerUid is derived from that user; the demo data links two
 * characters to seeded players.
 */
class CharacterPlayerPickerSeedTest extends TestCase {

	/**
	 * The merged schemas, as the import sees them.
	 *
	 * @return array<string, mixed> The schemas by key.
	 */
	private function schemas(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		return $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath)['components']['schemas'];
	}//end schemas()

	/**
	 * The demo objects by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function demoObjects(): array {
		$demo = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'), true);
		$bySlug = [];
		foreach ($demo['components']['objects'] as $object) {
			$bySlug[$object['@self']['slug']] = $object;
		}

		return $bySlug;
	}//end demoObjects()

	/**
	 * The schemas declare the chain the seeds rely on.
	 *
	 * @return void
	 */
	public function testTheSchemasDeclareTheChain(): void {
		$schemas = $this->schemas();
		$character = $schemas['character'];

		$this->assertSame(expected: 'player', actual: $character['properties']['ocName']['$ref']);
		$this->assertContains(needle: 'ocName', haystack: $character['required']);
		$this->assertSame(expected: ['schema' => 'player', 'mode' => 'relatedObject', 'field' => 'ocName'], actual: $character['x-openregister-references']['player']);
		$this->assertSame(expected: ['prop' => '@ref.player.userUid'], actual: $character['x-openregister-calculations']['ownerUid']['expression']);
		$this->assertTrue(condition: $character['x-openregister-calculations']['ownerUid']['materialise']);
		$this->assertArrayHasKey(key: 'userUid', array: $schemas['player']['properties']);
	}//end testTheSchemasDeclareTheChain()

	/**
	 * Three seeded players each name a Nextcloud user.
	 *
	 * @return void
	 */
	public function testTheDemoPlayersNameTheirUsers(): void {
		$objects = $this->demoObjects();

		foreach (['player-alice' => 'alice', 'player-bob' => 'bob', 'player-carol' => 'carol'] as $slug => $uid) {
			$this->assertArrayHasKey(key: $slug, array: $objects);
			$this->assertSame(expected: 'player', actual: $objects[$slug]['@self']['schema']);
			$this->assertSame(expected: $uid, actual: $objects[$slug]['userUid']);
			$this->assertNotSame(expected: '', actual: $objects[$slug]['name']);
		}
	}//end testTheDemoPlayersNameTheirUsers()

	/**
	 * Two demo characters point at a seeded player by that player's fixed uuid, and
	 * leave ownerUid to the calculation.
	 *
	 * @return void
	 */
	public function testTheDemoCharactersPointAtSeededPlayers(): void {
		$objects = $this->demoObjects();

		foreach (['character-voorbeeld-name-1-1' => 'player-alice', 'character-voorbeeld-name-2-2' => 'player-bob'] as $slug => $player) {
			$character = $objects[$slug];
			$this->assertSame(expected: 'character', actual: $character['@self']['schema']);
			$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', string: $objects[$player]['@self']['uuid']);
			$this->assertSame(expected: $objects[$player]['@self']['uuid'], actual: $character['ocName']);
			$this->assertArrayNotHasKey(key: 'ownerUid', array: $character, message: 'ownerUid is derived, never seeded');
		}
	}//end testTheDemoCharactersPointAtSeededPlayers()
}//end class
