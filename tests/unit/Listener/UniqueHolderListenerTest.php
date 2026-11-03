<?php

/**
 * Unit tests for UniqueHolderListener.
 *
 * The events are the REAL OpenRegister ObjectCreatingEvent and
 * ObjectUpdatingEvent (tests/stubs/openregister, copied verbatim), and the
 * entity carries the real ObjectEntity accessors, so a wrong accessor fails
 * here the way it would fail on an instance.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
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
use OCA\OpenRegister\Exception\NotAuthorizedException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An OpenRegister entity with a uuid, schema and payload.
 */
class HeldObjectEntity extends ObjectEntity {
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
 * Tests the unique-holder veto on character, item and condition writes.
 */
class UniqueHolderListenerTest extends TestCase {

	private const CROWN = '11111111-1111-4111-8111-111111111111';

	private const CURSE = '22222222-2222-4222-8222-222222222222';

	private const POTION = '33333333-3333-4333-8333-333333333333';

	private const QUEEN = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

	private const BERTRAM = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

	private const MIRELA = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

	private const TOMAS = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

	private const NOWHERE = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

	/**
	 * Every character in the fake register.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $characters = [];

	/**
	 * Items and conditions by id.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $objects = [];

	/**
	 * Ids the caller may not read: OpenRegister refuses them.
	 *
	 * @var array<int,string>
	 */
	private array $unreadable = [];

	/**
	 * Number of fetcher calls made.
	 *
	 * @var int
	 */
	private int $fetches = 0;

	/**
	 * Set up the fake register.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->characters = [
			['id' => self::QUEEN, 'name' => 'Queen Isolde', 'items' => [self::CROWN, self::POTION]],
			['id' => self::MIRELA, 'name' => 'Mirela', 'conditions' => [self::CURSE]],
		];
		$this->objects = [
			self::CROWN => [
				'id' => self::CROWN,
				'name' => 'Crown of Aldmoor',
				'unique' => true,
				'characters' => [self::QUEEN],
				'@self' => ['schema' => 'schema-item'],
			],
			self::POTION => [
				'id' => self::POTION,
				'name' => 'Healing potion',
				'unique' => false,
				'characters' => [self::QUEEN],
				'@self' => ['schema' => 'schema-item'],
			],
			self::CURSE => [
				'id' => self::CURSE,
				'name' => 'Curse of the Ashen King',
				'unique' => true,
				'characters' => [],
				'@self' => ['schema' => 'schema-condition'],
			],
			self::QUEEN => ['id' => self::QUEEN, 'name' => 'Queen Isolde'],
			self::BERTRAM => ['id' => self::BERTRAM, 'name' => 'Sir Bertram'],
		];
		$this->unreadable = [];
		$this->fetches = 0;
	}//end setUp()

	/**
	 * The listener over the fake register.
	 *
	 * @param LoggerInterface|null $logger An optional logger.
	 * @param bool $fetcherThrows Whether every fetch throws.
	 * @param bool $holdersThrow Whether only the holder lookup (getObjects) throws.
	 *
	 * @return UniqueHolderListener The listener.
	 */
	private function listener(?LoggerInterface $logger = null, bool $fetcherThrows = false, bool $holdersThrow = false): UniqueHolderListener {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($fetcherThrows, $holdersThrow): array {
				$this->fetches++;
				if ($fetcherThrows === true || $holdersThrow === true) {
					throw new \RuntimeException('register down');
				}

				if ($objectType !== 'character' || ($offset ?? 0) > 0) {
					return [];
				}

				$field = array_key_first($filters ?? []);
				if ($field === null) {
					return $this->characters;
				}

				return array_values(
					array_filter(
						$this->characters,
						fn (array $c): bool => in_array($filters[$field], $c[$field] ?? [], true)
					)
				);
			}
		);
		// Like OpenRegister's find(), which retries a UUID without the schema,
		// getObject answers with an object of any type.
		$read = function (string $objectType, string $id) use ($fetcherThrows): array {
			$this->fetches++;
			if ($fetcherThrows === true) {
				throw new \RuntimeException('register down');
			}

			if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) !== 1) {
				throw new \InvalidArgumentException('Invalid object ID: expected a UUID');
			}

			if (in_array($id, $this->unreadable, true) === true) {
				throw new NotAuthorizedException();
			}

			if (isset($this->objects[$id]) === false) {
				throw new DoesNotExistException('Object ' . $id . ' not found');
			}

			return $this->objects[$id];
		};
		$fetcher->method('getObject')->willReturnCallback($read);
		$fetcher->method('getObjectOfType')->willReturnCallback(
			function (string $objectType, string $id) use ($read): array {
				$object = $read($objectType, $id);
				if (($object['@self']['schema'] ?? '') !== 'schema-' . $objectType) {
					throw new DoesNotExistException('Object ' . $id . ' is not in the ' . $objectType . ' schema');
				}

				return $object;
			}
		);

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

		$normaliser = new IdListNormaliser();

		$holders = new UniqueHolderService(objectFetcher: $fetcher, idNormaliser: $normaliser);

		return new UniqueHolderListener(
			veto: new UniqueHolderVeto(holders: $holders, idNormaliser: $normaliser, l10n: $l10n),
			holders: $holders,
			config: $config,
			logger: $logger ?? $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end listener()

	/**
	 * A character update event.
	 *
	 * @param string $uuid The character uuid.
	 * @param array<string,mixed> $new The new payload.
	 * @param array<string,mixed> $old The stored payload.
	 * @param string $schema The schema id.
	 *
	 * @return ObjectUpdatingEvent The event.
	 */
	private function update(string $uuid, array $new, array $old, string $schema = 'schema-character'): ObjectUpdatingEvent {
		return new ObjectUpdatingEvent(
			new HeldObjectEntity($schema, $uuid, $new),
			new HeldObjectEntity($schema, $uuid, $old)
		);
	}//end update()

	/**
	 * REQ-UHE-001: a held unique item cannot go to a second character, and the holder is named.
	 *
	 * @return void
	 */
	public function testAHeldUniqueItemIsRefusedAndTheHolderNamed(): void {
		$event = $this->update(
			uuid: self::BERTRAM,
			new: ['name' => 'Sir Bertram', 'items' => [self::CROWN]],
			old: ['name' => 'Sir Bertram', 'items' => []]
		);

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame(expected: 'Crown of Aldmoor is already held by Queen Isolde. Remove it there first.', actual: $errors['message']);
		self::assertSame(expected: 'unique_item_held', actual: $errors['items'][0]['code']);
		self::assertSame(expected: self::CROWN, actual: $errors['items'][0]['item']);
		self::assertSame(expected: 'Queen Isolde', actual: $errors['items'][0]['heldBy']);
	}//end testAHeldUniqueItemIsRefusedAndTheHolderNamed()

	/**
	 * REQ-UHE-001: the same refusal on a create.
	 *
	 * @return void
	 */
	public function testACreateWithAHeldUniqueItemIsRefused(): void {
		$event = new ObjectCreatingEvent(new HeldObjectEntity('schema-character', null, ['name' => 'Sir Bertram', 'items' => [self::CROWN]]));

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'Queen Isolde', actual: $event->getErrors()['items'][0]['heldBy']);
	}//end testACreateWithAHeldUniqueItemIsRefused()

	/**
	 * REQ-UHE-001: a non-unique item goes to many characters.
	 *
	 * @return void
	 */
	public function testANonUniqueItemIsAllowed(): void {
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => [self::POTION]], old: ['items' => []]);

		$this->listener()->handle($event);

		self::assertFalse(condition: $event->isPropagationStopped());
	}//end testANonUniqueItemIsAllowed()

	/**
	 * REQ-UHE-001: an unrelated edit is not blocked by an old conflict, and reads nothing.
	 *
	 * @return void
	 */
	public function testAnUnrelatedEditIsNotBlockedByAnOldConflict(): void {
		$event = $this->update(
			uuid: self::BERTRAM,
			new: ['background' => 'A new past', 'items' => [self::CROWN]],
			old: ['background' => 'Old', 'items' => [self::CROWN]]
		);

		$this->listener()->handle($event);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: 0, actual: $this->fetches);
	}//end testAnUnrelatedEditIsNotBlockedByAnOldConflict()

	/**
	 * The holder is counted on the item side too, when the character side is empty.
	 *
	 * @return void
	 */
	public function testAHolderOnTheItemSideAloneIsCounted(): void {
		$this->characters = [];
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => [self::CROWN]], old: ['items' => []]);

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'Queen Isolde', actual: $event->getErrors()['items'][0]['heldBy']);
	}//end testAHolderOnTheItemSideAloneIsCounted()

	/**
	 * REQ-UHE-002: a unique condition rests on one character.
	 *
	 * @return void
	 */
	public function testAUniqueConditionIsRefusedOnASecondCharacter(): void {
		$event = $this->update(uuid: self::TOMAS, new: ['conditions' => [self::CURSE]], old: ['conditions' => []]);

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame(expected: 'unique_condition_held', actual: $errors['conditions'][0]['code']);
		self::assertSame(expected: 'Mirela', actual: $errors['conditions'][0]['heldBy']);
		self::assertSame(expected: 'Curse of the Ashen King is already on Mirela. Remove it there first.', actual: $errors['message']);
	}//end testAUniqueConditionIsRefusedOnASecondCharacter()

	/**
	 * REQ-UHE-003: switching unique on while two characters hold the item is refused on `unique`.
	 *
	 * @return void
	 */
	public function testSwitchingUniqueOnWhileTwoHoldItIsRefused(): void {
		$old = ['name' => 'Silver key', 'unique' => false, 'characters' => [self::QUEEN, self::BERTRAM]];
		$event = $this->update(uuid: self::POTION, new: ['unique' => true] + $old, old: $old, schema: 'schema-item');

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertArrayHasKey(key: 'unique', array: $errors);
		self::assertEqualsCanonicalizing(expected: ['Queen Isolde', 'Sir Bertram'], actual: $errors['unique'][0]['heldBy']);
		self::assertStringContainsString(needle: 'Queen Isolde', haystack: $errors['message']);
		self::assertStringContainsString(needle: 'Sir Bertram', haystack: $errors['message']);
	}//end testSwitchingUniqueOnWhileTwoHoldItIsRefused()

	/**
	 * REQ-UHE-003: adding a second character on the item page is refused on `characters`.
	 *
	 * @return void
	 */
	public function testAddingASecondHolderOnTheItemIsRefused(): void {
		$old = $this->objects[self::CROWN];
		$event = $this->update(uuid: self::CROWN, new: ['characters' => [self::QUEEN, self::BERTRAM]] + $old, old: $old, schema: 'schema-item');

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'unique_item_several_holders', actual: $event->getErrors()['characters'][0]['code']);
	}//end testAddingASecondHolderOnTheItemIsRefused()

	/**
	 * An item edit that does not add a holder nor switch the flag on is never blocked.
	 *
	 * @return void
	 */
	public function testAnItemEditWithoutANewHolderPasses(): void {
		$old = ['name' => 'Crown', 'unique' => true, 'characters' => [self::QUEEN, self::BERTRAM]];
		$event = $this->update(uuid: self::CROWN, new: ['name' => 'Crown of Aldmoor'] + $old, old: $old, schema: 'schema-item');

		$this->listener()->handle($event);

		self::assertFalse(condition: $event->isPropagationStopped());
	}//end testAnItemEditWithoutANewHolderPasses()

	/**
	 * Other schemas are ignored.
	 *
	 * @return void
	 */
	public function testOtherSchemasAreIgnored(): void {
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => [self::CROWN]], old: [], schema: 'schema-event');

		$this->listener()->handle($event);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: 0, actual: $this->fetches);
	}//end testOtherSchemasAreIgnored()

	/**
	 * REQ-UHE-006: a create of a unique item is refused when the holders cannot be read.
	 *
	 * @return void
	 */
	public function testALookupErrorRefusesACreateOfAUniqueItem(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$event = new ObjectCreatingEvent(new HeldObjectEntity('schema-item', self::CROWN, ['unique' => true, 'characters' => [self::QUEEN]]));
		$logger->expects(self::once())->method('error');

		$this->listener(logger: $logger, fetcherThrows: true)->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame(expected: 'The unique-holder check could not run, so this change was not saved. Try again.', actual: $errors['message']);
		self::assertSame(expected: 'unique_item_unchecked', actual: $errors['characters'][0]['code']);
		self::assertSame(expected: self::CROWN, actual: $errors['characters'][0]['item']);
	}//end testALookupErrorRefusesACreateOfAUniqueItem()

	/**
	 * REQ-UHE-006: a character that gains an item is refused with a try-again
	 * message when reading the item fails, and the failure is logged.
	 *
	 * @return void
	 */
	public function testAFailingItemReadRefusesWithTryAgain(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())->method('error');
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => [self::CROWN]], old: ['items' => []]);

		$this->listener(logger: $logger, fetcherThrows: true)->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame(expected: 'The unique-holder check could not run, so this change was not saved. Try again.', actual: $errors['message']);
		self::assertSame(expected: 'unique_item_unchecked', actual: $errors['items'][0]['code']);
		self::assertSame(expected: self::CROWN, actual: $errors['items'][0]['item']);
	}//end testAFailingItemReadRefusesWithTryAgain()

	/**
	 * REQ-UHE-007: an added item that does not exist is refused as unknown,
	 * without a try-again message and without logging an error.
	 *
	 * @return void
	 */
	public function testAnItemThatDoesNotExistIsRefusedAsUnknown(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::never())->method('error');
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => [self::NOWHERE]], old: ['items' => []]);

		$this->listener(logger: $logger)->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(
			expected: [
				'message' => 'Unknown item ' . self::NOWHERE . '. This change was not saved.',
				'items' => [
					[
						'code' => 'unique_item_unknown',
						'item' => self::NOWHERE,
						'message' => 'Unknown item ' . self::NOWHERE . '. This change was not saved.',
					],
				],
			],
			actual: $event->getErrors()
		);
	}//end testAnItemThatDoesNotExistIsRefusedAsUnknown()

	/**
	 * REQ-UHE-007: an added condition that does not exist is refused as unknown on `conditions`.
	 *
	 * @return void
	 */
	public function testAConditionThatDoesNotExistIsRefusedAsUnknown(): void {
		$event = $this->update(uuid: self::TOMAS, new: ['conditions' => [self::NOWHERE]], old: ['conditions' => []]);

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame(expected: 'Unknown condition ' . self::NOWHERE . '. This change was not saved.', actual: $errors['message']);
		self::assertSame(expected: 'unique_condition_unknown', actual: $errors['conditions'][0]['code']);
		self::assertSame(expected: self::NOWHERE, actual: $errors['conditions'][0]['condition']);
	}//end testAConditionThatDoesNotExistIsRefusedAsUnknown()

	/**
	 * REQ-UHE-007: an item the caller may not read gets exactly the answer of
	 * an item that does not exist, so the refusal never shows that it exists.
	 *
	 * @return void
	 */
	public function testAnUnreadableItemGetsTheSameAnswerAsAMissingOne(): void {
		$this->unreadable = [self::CROWN];
		$unreadable = $this->update(uuid: self::BERTRAM, new: ['items' => [self::CROWN]], old: ['items' => []]);
		$this->listener()->handle($unreadable);

		$this->unreadable = [];
		unset($this->objects[self::CROWN]);
		$missing = $this->update(uuid: self::BERTRAM, new: ['items' => [self::CROWN]], old: ['items' => []]);
		$this->listener()->handle($missing);

		self::assertTrue(condition: $unreadable->isPropagationStopped());
		self::assertSame(expected: 'unique_item_unknown', actual: $missing->getErrors()['items'][0]['code']);
		self::assertSame(expected: $missing->getErrors(), actual: $unreadable->getErrors());
	}//end testAnUnreadableItemGetsTheSameAnswerAsAMissingOne()

	/**
	 * REQ-UHE-007: a condition's id put in `items` is refused as an unknown item,
	 * even though OpenRegister finds the condition when asked for an item.
	 *
	 * @return void
	 */
	public function testAConditionIdInItemsIsRefusedAsAnUnknownItem(): void {
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => [self::CURSE]], old: ['items' => []]);

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame(expected: 'unique_item_unknown', actual: $errors['items'][0]['code']);
		self::assertSame(expected: 'Unknown item ' . self::CURSE . '. This change was not saved.', actual: $errors['message']);
	}//end testAConditionIdInItemsIsRefusedAsAnUnknownItem()

	/**
	 * REQ-UHE-007: an item's id put in `conditions` is refused as an unknown condition,
	 * with the answer of a condition that does not exist.
	 *
	 * @return void
	 */
	public function testAnItemIdInConditionsGetsTheAnswerOfAMissingCondition(): void {
		$wrongType = $this->update(uuid: self::TOMAS, new: ['conditions' => [self::CROWN]], old: ['conditions' => []]);
		$this->listener()->handle($wrongType);

		unset($this->objects[self::CROWN]);
		$missing = $this->update(uuid: self::TOMAS, new: ['conditions' => [self::CROWN]], old: ['conditions' => []]);
		$this->listener()->handle($missing);

		self::assertSame(expected: 'unique_condition_unknown', actual: $missing->getErrors()['conditions'][0]['code']);
		self::assertSame(expected: $missing->getErrors(), actual: $wrongType->getErrors());
	}//end testAnItemIdInConditionsGetsTheAnswerOfAMissingCondition()

	/**
	 * REQ-UHE-007: an added id that is not a valid object id is refused as unknown.
	 *
	 * @return void
	 */
	public function testAMalformedItemIdIsRefusedAsUnknown(): void {
		$event = $this->update(uuid: self::BERTRAM, new: ['items' => ['crown-of-aldmoor']], old: ['items' => []]);

		$this->listener()->handle($event);

		self::assertSame(expected: 'unique_item_unknown', actual: $event->getErrors()['items'][0]['code']);
		self::assertSame(expected: 'Unknown item crown-of-aldmoor. This change was not saved.', actual: $event->getErrors()['message']);
	}//end testAMalformedItemIdIsRefusedAsUnknown()

	/**
	 * REQ-UHE-007: an import row that names an item created later in the same
	 * import is refused as unknown, like any other row.
	 *
	 * @return void
	 */
	public function testAnImportRowWithAForwardReferenceIsRefusedAsUnknown(): void {
		$event = new ObjectCreatingEvent(new HeldObjectEntity('schema-character', null, ['name' => 'Sir Bertram', 'items' => [self::NOWHERE]]));

		$this->listener()->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'unique_item_unknown', actual: $event->getErrors()['items'][0]['code']);
	}//end testAnImportRowWithAForwardReferenceIsRefusedAsUnknown()

	/**
	 * REQ-UHE-006: a held condition whose holder lookup fails is refused on `conditions`.
	 *
	 * @return void
	 */
	public function testAFailingHolderLookupRefusesAGainedCondition(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects(self::once())->method('error');
		$event = $this->update(uuid: self::TOMAS, new: ['conditions' => [self::CURSE]], old: ['conditions' => []]);

		$this->listener(logger: $logger, holdersThrow: true)->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'unique_condition_unchecked', actual: $event->getErrors()['conditions'][0]['code']);
	}//end testAFailingHolderLookupRefusesAGainedCondition()

	/**
	 * REQ-UHE-006: switching unique on is refused on `unique` when the holders cannot be read.
	 *
	 * @return void
	 */
	public function testSwitchingUniqueOnIsRefusedWhenTheLookupFails(): void {
		$event = $this->update(
			uuid: self::CROWN,
			new: ['unique' => true, 'characters' => [self::QUEEN]],
			old: ['unique' => false, 'characters' => [self::QUEEN]],
			schema: 'schema-item'
		);

		$this->listener(fetcherThrows: true)->handle($event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'unique_item_unchecked', actual: $event->getErrors()['unique'][0]['code']);
	}//end testSwitchingUniqueOnIsRefusedWhenTheLookupFails()

	/**
	 * REQ-UHE-006: writes that add no holder pass while the lookup fails.
	 *
	 * @return void
	 */
	public function testWritesThatAddNoHolderPassWhileTheLookupFails(): void {
		$events = [
			$this->update(uuid: self::BERTRAM, new: ['background' => 'New', 'items' => [self::CROWN]], old: ['background' => 'Old', 'items' => [self::CROWN]]),
			$this->update(
				uuid: self::CROWN,
				new: ['unique' => true, 'name' => 'x', 'characters' => [self::QUEEN]],
				old: ['unique' => true, 'characters' => [self::QUEEN]],
				schema: 'schema-item'
			),
			$this->update(
				uuid: self::POTION,
				new: ['unique' => false, 'characters' => [self::QUEEN, self::BERTRAM]],
				old: ['unique' => false, 'characters' => [self::QUEEN]],
				schema: 'schema-item'
			),
			$this->update(uuid: self::BERTRAM, new: ['items' => []], old: ['items' => [self::CROWN]]),
		];

		foreach ($events as $index => $event) {
			$this->listener(fetcherThrows: true)->handle($event);
			self::assertFalse(condition: $event->isPropagationStopped(), message: 'event ' . $index);
		}
	}//end testWritesThatAddNoHolderPassWhileTheLookupFails()
}//end class
