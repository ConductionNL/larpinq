<?php

/**
 * Golden test for the unique-holder veto.
 *
 * Every write scenario runs through UniqueHolderListener::handle() against a
 * fake register in three fetcher modes (the filter applied, the filter
 * ignored so the bounded scan pages through characters, every read failing).
 * The veto, the errors payload and every register read are compared, as JSON
 * text so key order and scalar types count, with
 * fixtures/unique-holder.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\UniqueHolderListener;
use OCA\Larpinq\Listener\UniqueHolderVeto;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\UniqueHolderService;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An OpenRegister entity with a uuid, schema and payload.
 */
class GoldenHeldEntity extends ObjectEntity {
	/**
	 * Build the entity.
	 *
	 * @param string $schema The schema id.
	 * @param string|null $uuid The uuid.
	 * @param array<string,mixed> $data The payload.
	 */
	public function __construct(string $schema, ?string $uuid, array $data) {
		$this->schema = $schema;
		$this->uuid = $uuid;
		$this->object = $data;
	}
}

/**
 * Pins the vetoes and reads of UniqueHolderListener, UniqueHolderVeto and UniqueHolderService.
 *
 * @covers \OCA\Larpinq\Listener\UniqueHolderListener
 * @covers \OCA\Larpinq\Listener\UniqueHolderVeto
 * @covers \OCA\Larpinq\Service\UniqueHolderService
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 * @uses   \OCA\Larpinq\Service\HolderQuery
 * @uses   \OCA\Larpinq\Listener\ObjectWrite
 */
class UniqueHolderGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/unique-holder.golden.json';

	/**
	 * The register reads of the current run.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $reads = [];

	/**
	 * The characters of the fake register: a few named holders between
	 * filler characters, so the bounded scan needs several pages.
	 *
	 * @return array<int,mixed>
	 */
	private function characters(): array {
		$rows = [];
		for ($i = 0; $i < 230; $i++) {
			$rows[] = ['id' => sprintf('filler-%03d', $i), 'name' => 'Filler ' . $i, 'items' => ['potion']];
		}

		$rows[3] = ['id' => 'queen', 'name' => 'Queen Isolde', 'items' => ['crown', 'potion', ['id' => 'ring']]];
		$rows[7] = 'not a row';
		$rows[9] = ['name' => 'No id', 'items' => ['crown']];
		$rows[120] = ['@self' => ['id' => 'mirela'], 'name' => '  ', 'conditions' => ['curse'], 'items' => 'ring'];
		$rows[150] = ['id' => 'tomas', 'name' => 'Tomas', 'items' => ['ring'], 'conditions' => ['curse', 'blessing']];
		$rows[229] = ['id' => 'last', 'name' => 'Last', 'items' => ['ring'], 'conditions' => 'blessing'];

		return $rows;
	}//end characters()

	/**
	 * Items, conditions and characters readable by id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function objects(): array {
		return [
			'crown' => ['id' => 'crown', 'name' => 'Crown of Aldmoor', 'unique' => true, 'characters' => ['queen']],
			'potion' => ['id' => 'potion', 'name' => 'Healing potion', 'unique' => false],
			'ring' => ['id' => 'ring', 'name' => '', 'unique' => '1', 'characters' => [['id' => 'queen'], 'ghost', '']],
			'curse' => ['id' => 'curse', 'name' => 'Curse', 'unique' => 'true', 'characters' => []],
			'blessing' => ['id' => 'blessing', 'name' => 'Blessing', 'characters' => ['tomas']],
			'mark' => ['id' => 'mark', 'name' => 'Mark', 'unique' => 1, 'characters' => ['ghost', 'ghost2']],
			'plain' => ['id' => 'plain', 'name' => 'Plain', 'unique' => null],
			'queen' => ['id' => 'queen', 'name' => 'Queen Isolde'],
			'tomas' => ['id' => 'tomas', 'name' => 'Tomas'],
			'ghost' => ['id' => 'ghost', 'name' => 'Ghost'],
			'nameless' => ['id' => 'nameless'],
		];
	}//end objects()

	/**
	 * The write scenarios: [schema, uuid or null, new data, old data or null for a create].
	 *
	 * @return array<string,array{0:string,1:string|null,2:array<string,mixed>,3:array<string,mixed>|null}>
	 */
	private function scenarios(): array {
		return [
			'character gains a held unique item' => ['schema-character', 'bertram', ['items' => ['crown']], ['items' => []]],
			'character create gains a held unique item' => ['schema-character', null, ['items' => ['crown']], null],
			'character create with an id in the data' => ['schema-character', null, ['id' => 'queen', 'items' => ['crown']], null],
			'holder re-adds its own item' => ['schema-character', 'queen', ['items' => ['crown']], ['items' => []]],
			'character gains a non-unique item' => ['schema-character', 'bertram', ['items' => ['potion', 'plain']], []],
			'character gains an item that does not exist' => ['schema-character', 'bertram', ['items' => ['nowhere']], []],
			'character gains a unique condition' => ['schema-character', 'bertram', ['conditions' => ['curse']], []],
			'character gains a ring held on both sides' => ['schema-character', 'bertram', ['items' => [['id' => 'ring']], 'conditions' => ['blessing']], []],
			'character gains a nameless object' => ['schema-character', 'bertram', ['items' => ['nameless']], []],
			'character gains an unheld unique condition' => [
				'schema-character',
				'tomas',
				['conditions' => ['curse', 'blessing']],
				['conditions' => ['blessing']],
			],
			'character keeps what it had' => ['schema-character', 'queen', ['items' => ['crown']], ['items' => ['crown']]],
			'item gains a second holder' => [
				'schema-item',
				'crown',
				['name' => 'Crown', 'unique' => true, 'characters' => ['queen', 'bertram']],
				['unique' => true, 'characters' => ['queen']],
			],
			'item switches unique on with two holders' => [
				'schema-item',
				'ring',
				['name' => 'Ring', 'unique' => true, 'characters' => ['queen']],
				['unique' => false, 'characters' => ['queen']],
			],
			'item switched on and gains' => [
				'schema-item',
				'ring',
				['unique' => true, 'characters' => ['queen', 'x']],
				['unique' => false, 'characters' => ['queen']],
			],
			'item create without an id, two holders' => ['schema-item', null, ['name' => 'New', 'unique' => true, 'characters' => ['a', 'b', 'a', '']], null],
			'item create without an id, one holder' => ['schema-item', null, ['unique' => true, 'characters' => ['a', 'a']], null],
			'item create with an id' => ['schema-item', 'crown', ['unique' => true, 'characters' => ['queen']], null],
			'item create with an id in @self' => ['schema-item', null, ['@self' => ['id' => 'ring'], 'characters' => []], null],
			'item not unique' => ['schema-item', 'potion', ['unique' => false, 'characters' => ['a', 'b']], ['characters' => []]],
			'item edit without a new holder' => [
				'schema-item',
				'ring',
				['unique' => true, 'characters' => ['queen'], 'name' => 'x'],
				['unique' => true, 'characters' => ['queen']],
			],
			'item with four holders' => [
				'schema-item',
				'mark',
				['unique' => 1, 'characters' => ['ghost', 'ghost2', 'ghost3', 'queen']],
				['unique' => 1, 'characters' => ['ghost']],
			],
			'condition gains a holder' => [
				'schema-condition',
				'curse',
				['name' => 'Curse', 'unique' => 'true', 'characters' => ['bertram']],
				['unique' => 'true', 'characters' => []],
			],
			'condition default not unique' => ['schema-condition', 'blessing', ['characters' => ['tomas', 'bertram']], ['characters' => ['tomas']]],
			'item default unique' => ['schema-item', 'plain', ['characters' => ['tomas']], ['characters' => []]],
			'other schema' => ['schema-other', 'crown', ['items' => ['crown']], []],
			'no schema' => ['', 'crown', ['items' => ['crown']], []],
		];
	}//end scenarios()

	/**
	 * A fake register fetcher that records every read.
	 *
	 * @param string $mode 'filtered', 'unfiltered' or 'throws'.
	 *
	 * @return RegisterObjectFetcher The fetcher.
	 */
	private function fetcher(string $mode): RegisterObjectFetcher {
		$characters = $this->characters();
		$objects = $this->objects();
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($mode, $characters): array {
				$this->reads[] = ['getObjects' => $objectType, 'limit' => $limit, 'offset' => $offset, 'filters' => $filters];
				if ($mode === 'throws') {
					throw new \RuntimeException('register down');
				}

				$rows = $characters;
				if ($objectType !== 'character') {
					$rows = [];
				}

				$field = array_key_first($filters ?? []);
				if ($mode === 'filtered' && $field !== null) {
					$rows = array_values(
						array_filter(
							$rows,
							fn ($c): bool => is_array($c) === true && in_array($filters[$field], (new IdListNormaliser())->normalise(value: $c[$field] ?? []), true)
						)
					);
				}

				return array_slice($rows, $offset ?? 0, $limit);
			}
		);
		$read = function (string $method, string $objectType, string $id) use ($mode, $objects): array {
			$this->reads[] = [$method => $objectType, 'id' => $id];
			if ($mode === 'throws') {
				throw new \RuntimeException('register down');
			}

			if (isset($objects[$id]) === false) {
				throw new DoesNotExistException('Object ' . $id . ' not found');
			}

			return $objects[$id];
		};
		$fetcher->method('getObject')->willReturnCallback(fn (string $objectType, string $id): array => $read('getObject', $objectType, $id));
		$fetcher->method('getObjectOfType')->willReturnCallback(fn (string $objectType, string $id): array => $read('getObjectOfType', $objectType, $id));

		return $fetcher;
	}//end fetcher()

	/**
	 * The listener over a fake register.
	 *
	 * @param string $mode The fetcher mode.
	 * @param array<int,string> $logged Receives every logged error.
	 *
	 * @return UniqueHolderListener The listener.
	 */
	private function listener(string $mode, array &$logged): UniqueHolderListener {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => [
				'character_schema' => 'schema-character',
				'item_schema' => 'schema-item',
				'condition_schema' => 'schema-condition',
			][$key] ?? $default
		);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, $params = []): string => vsprintf($text, (array)$params));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message, array $context = []) use (&$logged): void {
				$logged[] = $message . ' / ' . $context['exception']->getMessage();
			}
		);
		$normaliser = new IdListNormaliser();

		$holders = new UniqueHolderService(objectFetcher: $this->fetcher(mode: $mode), idNormaliser: $normaliser);

		return new UniqueHolderListener(
			veto: new UniqueHolderVeto(holders: $holders, idNormaliser: $normaliser, l10n: $l10n),
			holders: $holders,
			config: $config,
			logger: $logger
		);
	}//end listener()

	/**
	 * Run one scenario in one mode.
	 *
	 * @param array{0:string,1:string|null,2:array<string,mixed>,3:array<string,mixed>|null} $scenario The scenario.
	 * @param string $mode The fetcher mode.
	 *
	 * @return array<string,mixed> The outcome.
	 */
	private function runScenario(array $scenario, string $mode): array {
		[$schema, $uuid, $new, $old] = $scenario;
		$this->reads = [];
		$logged = [];
		$event = new ObjectCreatingEvent(new GoldenHeldEntity($schema, $uuid, $new));
		if ($old !== null) {
			$event = new ObjectUpdatingEvent(new GoldenHeldEntity($schema, $uuid, $new), new GoldenHeldEntity($schema, $uuid, $old));
		}

		$this->listener(mode: $mode, logged: $logged)->handle($event);

		return [
			'stopped' => $event->isPropagationStopped(),
			'errors' => $event->getErrors(),
			'logged' => $logged,
			'reads' => $this->reads,
		];
	}//end runScenario()

	/**
	 * Every scenario in every mode matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach ($this->scenarios() as $name => $scenario) {
			foreach (['filtered', 'unfiltered', 'throws'] as $mode) {
				$actual[$name . ' / ' . $mode] = json_encode($this->runScenario(scenario: $scenario, mode: $mode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			}
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()
}//end class
