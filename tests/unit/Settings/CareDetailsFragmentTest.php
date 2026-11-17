<?php

/**
 * Tests for the care-details register fragment
 * (players-care-details-and-erasure REQ-PCE-001 to REQ-PCE-003).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-players-keep-care-details-that-only-they-and-game-masters-see-req-pce-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\EventCareRecords;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The register as OpenRegister imports it, monolith plus fragments.
 *
 * @covers \OCA\Larpinq\Service\ConfigFileLoaderService
 * @uses \OCA\Larpinq\Service\EventCareRecords
 */
class CareDetailsFragmentTest extends TestCase {

	/**
	 * The merged register.
	 *
	 * @return array<string, mixed>
	 */
	private function mergedRegister(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		return $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath);
	}//end mergedRegister()

	/**
	 * The player's care fields are encrypted and only the player's own account and game masters read or change them.
	 *
	 * @return void
	 */
	public function testThePlayersCareFieldsAreEncryptedAndPrivate(): void {
		$player = $this->mergedRegister()['components']['schemas']['player']['properties'];
		$own = [['group' => 'gamemasters'], ['group' => 'larpers', 'match' => ['userUid' => '$userId']]];

		foreach (EventCareRecords::FIELDS as $field) {
			$this->assertTrue($player[$field]['x-openregister-encrypted'], $field);
			$this->assertSame(['read' => $own, 'update' => $own], $player[$field]['authorization'], $field);
		}

		// The fragment adds; the shipped player fields are still there.
		foreach (['name', 'description', 'userUid'] as $kept) {
			$this->assertArrayHasKey($kept, $player);
		}
	}//end testThePlayersCareFieldsAreEncryptedAndPrivate()

	/**
	 * The event copy is in the register, game masters only, encrypted, and destroyed from its erase date.
	 *
	 * @return void
	 */
	public function testTheEventCopyIsForGameMastersAndIsDestroyed(): void {
		$register = $this->mergedRegister();
		$this->assertContains('larping_event_care_record', $register['components']['registers']['larpinq']['schemas']);

		$record = $register['components']['schemas']['eventCareRecord'];
		$this->assertSame('larping_event_care_record', $record['slug']);
		$gm = [['group' => 'gamemasters']];
		$this->assertSame(['create' => $gm, 'read' => $gm, 'update' => $gm, 'delete' => $gm], $record['authorization']);
		foreach (EventCareRecords::FIELDS as $field) {
			$this->assertTrue($record['properties'][$field]['x-openregister-encrypted'], $field);
		}

		$this->assertSame(
			['enabled' => true, 'defaultNominatie' => 'vernietigen', 'afleidingswijze' => 'eigenschap', 'bronEigenschap' => 'eraseAfter', 'defaultBewaartermijn' => 'P0D'],
			$record['archive']
		);
		$this->assertSame('date-time', $record['properties']['eraseAfter']['format']);
		$this->assertContains('eraseAfter', $record['required']);

		$days = $register['components']['schemas']['event']['properties']['careRetentionDays'];
		$this->assertSame(EventCareRecords::DEFAULT_RETENTION_DAYS, $days['default']);
		$this->assertSame(0, $days['minimum']);
	}//end testTheEventCopyIsForGameMastersAndIsDestroyed()
}//end class
