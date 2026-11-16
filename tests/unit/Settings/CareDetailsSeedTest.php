<?php

/**
 * The care-details demo data (players-care-details-and-erasure): Anna's care
 * details, her accepted registration for Winter Court 2026 and its care record.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-care-details-and-erasure/design.md#seed-data
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use DateInterval;
use DateTimeImmutable;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The seeded care record is the copy the registration would have made: it names
 * the event, the registration and the player, carries the player's care details,
 * and is due for erasure the event's retention days after the event ends.
 */
class CareDetailsSeedTest extends TestCase {

	/**
	 * The care fields a care record copies from its player.
	 *
	 * @var array<int, string>
	 */
	private const CARE_FIELDS = ['allergies', 'medicalNotes', 'dietaryNeeds', 'emergencyContactName', 'emergencyContactPhone'];

	/**
	 * The merged schemas, as the import sees them, by slug.
	 *
	 * @return array<string, array<string, mixed>> The schemas by slug.
	 */
	private function schemasBySlug(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		$bySlug = [];
		foreach ($merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath)['components']['schemas'] as $key => $schema) {
			$bySlug[(string)($schema['slug'] ?? $key)] = $schema;
		}

		return $bySlug;
	}//end schemasBySlug()

	/**
	 * The demo objects by slug.
	 *
	 * @return array<string, array<string, mixed>> The objects by slug.
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
	 * Anna carries the design's care details, and every seeded field is one her schema declares.
	 *
	 * @return void
	 */
	public function testAnnaCarriesHerCareDetails(): void {
		$anna = $this->demoObjects()['player-anna'];
		$player = $this->schemasBySlug()['player'];

		$this->assertSame(expected: 'Anna de Vries', actual: $anna['name']);
		$this->assertSame(expected: 'anna', actual: $anna['userUid']);
		$this->assertSame(expected: 'hazelnuts', actual: $anna['allergies']);
		$this->assertSame(expected: 'Joost de Vries', actual: $anna['emergencyContactName']);
		$this->assertSame(expected: '+31 6 00000000', actual: $anna['emergencyContactPhone']);
		$this->assertSame(expected: [], actual: array_diff(array_keys(array_diff_key($anna, ['@self' => true])), array_keys($player['properties'])));
	}//end testAnnaCarriesHerCareDetails()

	/**
	 * Her registration for Winter Court 2026 is accepted and points at the seeded event and player.
	 *
	 * @return void
	 */
	public function testHerRegistrationIsAccepted(): void {
		$objects = $this->demoObjects();
		$registration = $objects['larping-registration-anna-winter-court-2026'];
		$event = $objects['larping-event-winter-court-2026'];

		$this->assertSame(expected: 'Winter Court 2026', actual: $event['name']);
		$this->assertSame(expected: 30, actual: $event['careRetentionDays']);
		$this->assertSame(expected: 'accepted', actual: $registration['status']);
		$this->assertSame(expected: $event['@self']['uuid'], actual: $registration['event']);
		$this->assertSame(expected: $objects['player-anna']['@self']['uuid'], actual: $registration['player']);
	}//end testHerRegistrationIsAccepted()

	/**
	 * The care record copies her details and is erased the event's retention days after the event ends.
	 *
	 * @return void
	 */
	public function testTheCareRecordIsHerCopyDueAfterTheRetentionDays(): void {
		$objects = $this->demoObjects();
		$record = $objects['larping-event-care-record-anna-winter-court-2026'];
		$event = $objects['larping-event-winter-court-2026'];
		$anna = $objects['player-anna'];
		$schema = $this->schemasBySlug()['larping_event_care_record'];

		$this->assertSame(expected: 'larping_event_care_record', actual: $record['@self']['schema']);
		$this->assertSame(expected: $event['@self']['uuid'], actual: $record['event']);
		$this->assertSame(expected: $objects['larping-registration-anna-winter-court-2026']['@self']['uuid'], actual: $record['registration']);
		$this->assertSame(expected: $anna['@self']['uuid'], actual: $record['player']);
		foreach (self::CARE_FIELDS as $field) {
			$this->assertSame(expected: ($anna[$field] ?? null), actual: ($record[$field] ?? null), message: $field);
		}

		$this->assertSame(expected: $event['endDate'], actual: $record['eventEndDate']);
		$due = (new DateTimeImmutable($event['endDate']))->add(new DateInterval('P' . $event['careRetentionDays'] . 'D'));
		$this->assertEquals(expected: $due, actual: new DateTimeImmutable($record['eraseAfter']));

		foreach ($schema['required'] as $required) {
			$this->assertArrayHasKey(key: $required, array: $record);
		}

		$this->assertSame(expected: [], actual: array_diff(array_keys(array_diff_key($record, ['@self' => true])), array_keys($schema['properties'])));
	}//end testTheCareRecordIsHerCopyDueAfterTheRetentionDays()
}//end class
