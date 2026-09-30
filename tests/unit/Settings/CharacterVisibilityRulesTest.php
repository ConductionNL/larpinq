<?php

/**
 * CharacterVisibilityRulesTest.
 *
 * The player-visibility rules live in a register fragment, and a fragment only
 * counts once ConfigFileLoaderService merges it over the monolith. So these
 * assertions read the MERGED register, the document OpenRegister imports.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * REQ-CPV-001 to REQ-CPV-004: the character rules in the imported register.
 */
class CharacterVisibilityRulesTest extends TestCase {

	private const GM = 'gamemasters';

	private const OWNER = ['group' => 'larpers', 'match' => ['ownerUid' => '$userId']];

	private const GM_WRITES = ['gold', 'silver', 'copper', 'skills', 'items', 'conditions', 'events', 'slNotesPublic', 'approved', 'ocName', 'ownerUid'];

	/**
	 * The merged character schema.
	 *
	 * @return array<string,mixed> The schema.
	 */
	private function character(): array {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(dirname(__DIR__, 3));
		$register = (new ConfigFileLoaderService($appManager))->loadConfigurationFile();

		return $register['components']['schemas']['character'];
	}//end character()

	/**
	 * A player reads their own and approved characters; writes only their own.
	 *
	 * @return void
	 */
	public function testRowRulesLetAPlayerReadOwnAndApprovedAndWriteOnlyOwn(): void {
		$auth = $this->character()['authorization'];

		$this->assertSame(
			[['group' => self::GM], self::OWNER, ['group' => 'larpers', 'match' => ['approved' => 'approved']]],
			$auth['read']
		);
		$this->assertSame([['group' => self::GM], self::OWNER], $auth['update']);
		$this->assertSame([self::GM, 'larpers'], $auth['create']);
		$this->assertSame([self::GM], $auth['delete']);
	}//end testRowRulesLetAPlayerReadOwnAndApprovedAndWriteOnlyOwn()

	/**
	 * The approved match uses a value the approved enum can hold.
	 *
	 * @return void
	 */
	public function testTheCastMatchIsAValidApprovedValue(): void {
		$this->assertContains('approved', $this->character()['properties']['approved']['enum']);
	}//end testTheCastMatchIsAValidApprovedValue()

	/**
	 * Private notes are game master only, for read and write.
	 *
	 * @return void
	 */
	public function testPrivateNotesAreGameMasterOnly(): void {
		$props = $this->character()['properties'];
		foreach (['slNotesPrivate', 'requirementOverrides'] as $field) {
			$this->assertSame(['read' => [self::GM], 'update' => [self::GM]], $props[$field]['authorization'], $field);
		}
	}//end testPrivateNotesAreGameMasterOnly()

	/**
	 * The owner writes the story fields, and only a game master writes the rest.
	 *
	 * @return void
	 */
	public function testTheOwnerWritesStoryFieldsOnly(): void {
		$props = $this->character()['properties'];
		foreach (['background', 'faith'] as $field) {
			$this->assertSame([self::GM, self::OWNER], $props[$field]['authorization']['update'], $field);
		}

		foreach (self::GM_WRITES as $field) {
			$this->assertSame([self::GM], $props[$field]['authorization']['update'], $field);
		}
	}//end testTheOwnerWritesStoryFieldsOnly()

	/**
	 * A cast entry shows name, type, description and status; everything else is owner or game master.
	 *
	 * @return void
	 */
	public function testACastEntryShowsOnlyNameTypeAndDescription(): void {
		$open = [];
		foreach ($this->character()['properties'] as $name => $property) {
			if (isset($property['authorization']['read']) === false) {
				$open[] = $name;
			}
		}

		sort($open);
		// The status (active, retired, dead) is a game fact the cast may see
		// (characters-status-and-bulk-edit).
		$this->assertSame(['approved', 'description', 'name', 'setting', 'status', 'type'], $open);
	}//end testACastEntryShowsOnlyNameTypeAndDescription()

	/**
	 * The fragment adds rules without dropping a single base property.
	 *
	 * @return void
	 */
	public function testTheMergeKeepsEveryBaseProperty(): void {
		$base = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_register.json'), true);
		$merged = $this->character()['properties'];
		foreach ($base['components']['schemas']['character']['properties'] as $name => $property) {
			$this->assertSame($property['type'] ?? null, $merged[$name]['type'] ?? null, $name);
		}
	}//end testTheMergeKeepsEveryBaseProperty()
}//end class
