<?php

/**
 * The casting and crew seed of events-casting-and-crew-roles: it validates
 * against the merged schemas, and the proposal over it is the one design.md
 * names (Anna The Heir, Pieter The Bard, Sanne The Spy).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/events-casting-and-crew-roles/tasks.md#task-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\CastingService;
use OCA\Larpinq\Service\CastingSolver;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * The Winter Court 2026 casting seed.
 *
 * @coversNothing
 */
class CastingSeedTest extends TestCase {

	/** Seed schema slug => merged schema key. */
	private const SCHEMAS = [
		'larping_casting_preference' => 'castingPreference',
		'larping_crew_role' => 'crewRole',
		'larping_crew_assignment' => 'crewAssignment',
		'character' => 'character',
		'larping_registration' => 'registration',
		'larping_event' => 'event',
	];

	/** Seed schema slug => the object type the casting service reads. */
	private const TYPES = [
		'character' => 'character',
		'larping_registration' => 'registration',
		'larping_casting_preference' => 'castingpreference',
	];

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
	}

	/**
	 * The demo objects of one schema slug, in file order.
	 *
	 * @param string $schema The schema slug.
	 *
	 * @return list<array<string, mixed>> The objects.
	 */
	private function seed(string $schema): array {
		$demo = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'), true);

		return array_values(array_filter($demo['components']['objects'], static fn (array $o): bool => $o['@self']['schema'] === $schema));
	}

	/**
	 * The placeholder uuid of the n-th seeded object of a schema.
	 *
	 * @param int $index The position.
	 *
	 * @return string The uuid.
	 */
	private static function placeholder(int $index): string {
		return sprintf('00000000-0000-4000-8000-%012d', $index);
	}

	/**
	 * A schema node as Opis reads it: no `$ref`, rules or calculations.
	 *
	 * @param array<string, mixed> $node A schema node.
	 *
	 * @return array<string, mixed> The node.
	 */
	private function forOpis(array $node): array {
		unset($node['$ref'], $node['authorization'], $node['calculation']);
		foreach ($node as $key => $value) {
			if (is_array($value) === true) {
				$node[$key] = $this->forOpis(node: $value);
			}
		}

		return $node;
	}

	public function testTheSeedValidatesAgainstTheMergedSchemas(): void {
		$schemas = $this->schemas();
		foreach (self::SCHEMAS as $slug => $key) {
			$schema = $schemas[$key];
			unset($schema['authorization'], $schema['configuration']);
			$opis = json_decode((string)json_encode($this->forOpis(node: $schema)));
			foreach ($this->seed(schema: $slug) as $object) {
				$name = $object['@self']['slug'];
				unset($object['@self']);
				$result = (new Validator())->validate(json_decode((string)json_encode($object)), $opis);
				$this->assertTrue(condition: $result->isValid(), message: "seed {$name} must validate");
			}
		}
	}

	public function testTheSeedHoldsTheDesignsCastAndCrew(): void {
		$open = array_values(array_filter($this->seed(schema: 'character'), static fn (array $c): bool => ($c['castingEvent'] ?? null) !== null));
		$this->assertSame(expected: ['The Chancellor', 'The Spy', 'The Heir', 'The Bard'], actual: array_column($open, 'name'));

		$this->assertSame(expected: '2026-11-01T00:00:00+00:00', actual: $this->seed(schema: 'larping_event')[0]['castingDeadline']);

		$roles = $this->seed(schema: 'larping_crew_role');
		$this->assertSame(
			expected: [['Bandits', 'npc', 6], ['Kitchen', 'crew', 3]],
			actual: array_map(static fn (array $r): array => [$r['name'], $r['kind'], $r['places']], $roles)
		);

		$assignments = $this->seed(schema: 'larping_crew_assignment');
		$this->assertCount(expectedCount: 1, haystack: $assignments);
		$this->assertSame(expected: self::placeholder(1), actual: $assignments[0]['crewRole'], message: 'Joris is in the Kitchen');
		$registrations = $this->seed(schema: 'larping_registration');
		$joris = $registrations[(int)substr($assignments[0]['registration'], -12)];
		$this->assertSame(expected: 'joris', actual: $joris['submitterUid']);
		$this->assertSame(expected: 'crew', actual: $this->seed(schema: 'larping_ticket_type')[(int)substr($joris['ticketType'], -12)]['role']);
	}

	public function testTheProposalOverTheSeedIsTheDesignsCasting(): void {
		$rows = [];
		foreach (self::TYPES as $slug => $type) {
			foreach ($this->seed(schema: $slug) as $index => $object) {
				$rows[$type][] = array_merge($object, ['id' => self::placeholder($index)]);
			}
		}

		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			static function (string $objectType, array $filters) use ($rows): array {
				return array_values(
					array_filter(
						$rows[$objectType],
						static function (array $row) use ($filters): bool {
							foreach ($filters as $field => $value) {
								if (($row[$field] ?? null) !== $value) {
									return false;
								}
							}

							return true;
						}
					)
				);
			}
		);

		$service = new CastingService(fetcher: $fetcher, solver: new CastingSolver(), logger: new NullLogger());
		$proposal = $service->propose(eventId: self::placeholder(0));

		$byPlayer = [];
		$registrations = $this->seed(schema: 'larping_registration');
		foreach ($proposal['pairs'] as $pair) {
			$byPlayer[$registrations[(int)substr($pair['registration'], -12)]['submitterUid']] = $pair['characterName'];
		}

		ksort($byPlayer);
		$this->assertSame(expected: ['anna' => 'The Heir', 'pieter' => 'The Bard', 'sanne' => 'The Spy'], actual: $byPlayer);
		$this->assertSame(expected: [], actual: $proposal['unmatched']);
	}
}
