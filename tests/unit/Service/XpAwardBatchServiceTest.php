<?php

/**
 * A game master awards XP to a whole event in one save
 * (events-xp-batch-award, REQ-EXB-001 and the event-xp-awards batch requirement).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SettingsLoadService;
use OCA\Larpinq\Service\SettingsService;
use OCA\Larpinq\Service\XpAwardBatchService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The award roster and the batch award, over the real EventRosterService.
 */
class XpAwardBatchServiceTest extends TestCase {

	private const EVENT = '5a5a5a5a-0000-4000-8000-000000000001';
	private const MIRELA = 'c1c1c1c1-0000-4000-8000-000000000001';
	private const BERTRAM = 'c1c1c1c1-0000-4000-8000-000000000002';
	private const HARROW = 'c1c1c1c1-0000-4000-8000-000000000003';
	private const NOBODY = 'c1c1c1c1-0000-4000-8000-000000000004';
	private const OUTSIDER = 'c1c1c1c1-0000-4000-8000-000000000009';

	/** @var array<string, array<int, array<string, mixed>>> Stored objects by type. */
	private array $objects = [];

	/** @var array<int, array{type: string, data: array<string, mixed>}> Every save. */
	private array $saved = [];

	private XpAwardBatchService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->objects = [
			'character' => [
				['id' => self::MIRELA, 'name' => 'Mirela the Wanderer', 'events' => [self::EVENT]],
				['id' => self::BERTRAM, 'name' => 'Sir Bertram', 'events' => [self::EVENT]],
				['id' => self::HARROW, 'name' => 'Old Captain Harrow', 'events' => [self::EVENT]],
				['id' => self::NOBODY, 'name' => 'Quiet Nell', 'events' => [self::EVENT]],
				['id' => self::OUTSIDER, 'name' => 'Stranger', 'events' => []],
			],
			'player' => [],
			'attendance' => [
				['id' => 'a1', 'event' => self::EVENT, 'character' => self::MIRELA, 'status' => 'checked-in'],
				['id' => 'a2', 'event' => self::EVENT, 'character' => self::BERTRAM, 'status' => 'checked-in'],
				['id' => 'a3', 'event' => self::EVENT, 'character' => self::HARROW, 'status' => 'no-show'],
			],
			'xpAward' => [],
		];

		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []): array {
				$rows = $this->objects[$objectType] ?? [];
				foreach (($filters ?? []) as $field => $value) {
					$rows = array_values(array_filter($rows, static fn (array $row): bool => ($row[$field] ?? null) === $value));
				}

				return $rows;
			}
		);
		$fetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid = null): array {
				$this->saved[] = ['type' => $objectType, 'data' => $data];
				$object = ['id' => 'award-' . count($this->saved)] + $data;
				$this->objects[$objectType][] = $object;
				return $object;
			}
		);

		$this->service = new XpAwardBatchService(new EventRosterService($fetcher), $fetcher);
	}//end setUp()

	/**
	 * The rows of the award roster, by character name.
	 *
	 * @return array<string, array<string, mixed>> The rows.
	 */
	private function rosterByName(): array {
		$rows = [];
		foreach ($this->service->awardRoster(eventId: self::EVENT)['rows'] as $row) {
			$rows[$row['name']] = $row;
		}

		return $rows;
	}//end rosterByName()

	/**
	 * Attendance decides the default ticks: checked in ticked, a no-show and
	 * a character without a recorded check-in not.
	 *
	 * @return void
	 */
	public function testAttendanceDecidesTheDefaultTicks(): void {
		$rows = $this->rosterByName();

		$this->assertSame(['Mirela the Wanderer', 'Old Captain Harrow', 'Quiet Nell', 'Sir Bertram'], array_keys($rows));
		$this->assertTrue($rows['Mirela the Wanderer']['ticked']);
		$this->assertTrue($rows['Sir Bertram']['ticked']);
		$this->assertFalse($rows['Old Captain Harrow']['ticked']);
		$this->assertSame('no-show', $rows['Old Captain Harrow']['attendance']);
		$this->assertFalse($rows['Quiet Nell']['ticked']);
		$this->assertSame('', $rows['Quiet Nell']['attendance'], 'no check-in recorded');
	}//end testAttendanceDecidesTheDefaultTicks()

	/**
	 * A character who already has an award for the event starts unticked and
	 * shows the award.
	 *
	 * @return void
	 */
	public function testReopeningDoesNotDoubleAward(): void {
		$this->objects['xpAward'][] = ['id' => 'x1', 'event' => self::EVENT, 'character' => self::BERTRAM, 'amount' => 5, 'reason' => 'Attended', 'awardedBy' => 'joris'];
		$this->objects['xpAward'][] = ['id' => 'x2', 'event' => 'another-event', 'character' => self::MIRELA, 'amount' => 3];

		$rows = $this->rosterByName();

		$this->assertFalse($rows['Sir Bertram']['ticked']);
		$this->assertSame('x1', $rows['Sir Bertram']['awards'][0]['id']);
		$this->assertSame('joris', $rows['Sir Bertram']['awards'][0]['awardedBy']);
		$this->assertTrue($rows['Mirela the Wanderer']['ticked'], 'an award for another event does not count');
		$this->assertSame([], $rows['Mirela the Wanderer']['awards']);
	}//end testReopeningDoesNotDoubleAward()

	/**
	 * One save creates an award per row, with per-row amount and reason.
	 *
	 * @return void
	 */
	public function testABatchCreatesOneAwardPerRow(): void {
		$result = $this->service->award(
			eventId: self::EVENT,
			rows: [
				['character' => self::MIRELA, 'amount' => 3],
				['character' => self::BERTRAM, 'amount' => 3],
				['character' => self::HARROW, 'amount' => '2', 'reason' => ' Saturday only '],
			],
			actingUid: 'joris'
		);

		$this->assertCount(3, $result['created']);
		$this->assertSame([], $result['refused']);
		$this->assertSame(['xpAward', 'xpAward', 'xpAward'], array_column($this->saved, 'type'));
		$harrow = $this->saved[2]['data'];
		$this->assertSame(self::EVENT, $harrow['event']);
		$this->assertSame(2, $harrow['amount']);
		$this->assertSame('Saturday only', $harrow['reason']);
		$this->assertSame('joris', $harrow['awardedBy']);
		$this->assertArrayNotHasKey('reason', $this->saved[0]['data']);
	}//end testABatchCreatesOneAwardPerRow()

	/**
	 * One duplicate in the batch: the new award is made, the duplicate refused.
	 *
	 * @return void
	 */
	public function testADuplicateIsRefusedAndTheRestIsCreated(): void {
		$this->objects['xpAward'][] = ['id' => 'x1', 'event' => self::EVENT, 'character' => self::BERTRAM, 'amount' => 5];

		$result = $this->service->award(
			eventId: self::EVENT,
			rows: [
				['character' => self::MIRELA, 'amount' => 5],
				['character' => self::BERTRAM, 'amount' => 5],
				['character' => self::MIRELA, 'amount' => 5],
			],
			actingUid: 'joris'
		);

		$this->assertCount(1, $result['created']);
		$this->assertSame(
			[
				['character' => self::BERTRAM, 'reason' => 'duplicate'],
				['character' => self::MIRELA, 'reason' => 'duplicate'],
			],
			$result['refused']
		);
	}//end testADuplicateIsRefusedAndTheRestIsCreated()

	/**
	 * An extra award needs a reason; with one it is made.
	 *
	 * @return void
	 */
	public function testAnExtraAwardNeedsAReason(): void {
		$this->objects['xpAward'][] = ['id' => 'x1', 'event' => self::EVENT, 'character' => self::BERTRAM, 'amount' => 5];

		$result = $this->service->award(
			eventId: self::EVENT,
			rows: [
				['character' => self::BERTRAM, 'amount' => 2, 'extra' => true],
				['character' => self::BERTRAM, 'amount' => 2, 'extra' => true, 'reason' => 'Plot bonus'],
			],
			actingUid: 'joris'
		);

		$this->assertSame([['character' => self::BERTRAM, 'reason' => 'extra-needs-reason']], $result['refused']);
		$this->assertCount(1, $result['created']);
		$this->assertSame('Plot bonus', $this->saved[0]['data']['reason']);
	}//end testAnExtraAwardNeedsAReason()

	/**
	 * A character off the roster, and an amount that is not above zero, are refused.
	 *
	 * @return void
	 */
	public function testOffRosterAndBadAmountsAreRefused(): void {
		$result = $this->service->award(
			eventId: self::EVENT,
			rows: [
				['character' => self::OUTSIDER, 'amount' => 5],
				['character' => self::MIRELA, 'amount' => 0],
				['character' => self::MIRELA, 'amount' => 'lots'],
				'not a row',
			],
			actingUid: 'joris'
		);

		$this->assertSame([], $result['created']);
		$this->assertSame(
			['not-on-roster', 'invalid-amount', 'invalid-amount', 'not-on-roster'],
			array_column($result['refused'], 'reason')
		);
		$this->assertSame([], $this->saved);
	}//end testOffRosterAndBadAmountsAreRefused()

	/**
	 * The award payload validates against the real xpAward schema, as
	 * OpenRegister's validator (Opis) reads it.
	 *
	 * @return void
	 */
	public function testThePayloadValidatesAgainstTheRealSchema(): void {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		$schema = $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath)['components']['schemas']['xpAward'];
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['visible']);
		}

		unset($schema['authorization'], $schema['configuration']);

		$validator = new Validator();
		foreach ([['amount' => 3, 'reason' => ''], ['amount' => 2.5, 'reason' => 'Saturday only']] as $row) {
			$payload = $this->service->payload(
				eventId: self::EVENT,
				row: ['character' => self::MIRELA, 'amount' => $row['amount'], 'reason' => $row['reason'], 'extra' => false],
				actingUid: 'joris'
			);
			$result = $validator->validate(json_decode((string)json_encode($payload)), json_decode((string)json_encode($schema)));
			$this->assertTrue($result->isValid(), (string)json_encode($payload));
		}
	}//end testThePayloadValidatesAgainstTheRealSchema()
	/**
	 * The register import binds the xpAward schema to the `xpaward_*` config
	 * keys the object fetcher reads (strtolower of the object type), so awards
	 * can be read and written on the server at all.
	 *
	 * @return void
	 */
	public function testTheImportConfiguresTheAwardSchema(): void {
		$slugs = (new ReflectionClass(SettingsLoadService::class))->getConstant('OBJECT_TYPE_SCHEMA_SLUGS');
		$keys = (new ReflectionClass(SettingsService::class))->getConstant('CONFIG_KEYS');

		$this->assertSame('xpAward', $slugs[strtolower(XpAwardBatchService::OBJECT_TYPE)] ?? null);
		$this->assertContains('xpaward_schema', $keys);
		$this->assertContains('xpaward_register', $keys);
		$this->assertContains('xpaward_source', $keys);
	}//end testTheImportConfiguresTheAwardSchema()
}//end class
