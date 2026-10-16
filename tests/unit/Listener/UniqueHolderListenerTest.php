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
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\UniqueHolderService;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
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
			self::CROWN => ['id' => self::CROWN, 'name' => 'Crown of Aldmoor', 'unique' => true, 'characters' => [self::QUEEN]],
			self::POTION => ['id' => self::POTION, 'name' => 'Healing potion', 'unique' => false, 'characters' => [self::QUEEN]],
			self::CURSE => ['id' => self::CURSE, 'name' => 'Curse of the Ashen King', 'unique' => true, 'characters' => []],
			self::QUEEN => ['id' => self::QUEEN, 'name' => 'Queen Isolde'],
			self::BERTRAM => ['id' => self::BERTRAM, 'name' => 'Sir Bertram'],
		];
		$this->fetches = 0;
	}//end setUp()

	/**
	 * The listener over the fake register.
	 *
	 * @param LoggerInterface|null $logger An optional logger.
	 * @param bool $fetcherThrows Whether every fetch throws.
	 *
	 * @return UniqueHolderListener The listener.
	 */
	private function listener(?LoggerInterface $logger = null, bool $fetcherThrows = false): UniqueHolderListener {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $objectType, ?int $limit = null, ?int $offset = null, ?array $filters = []) use ($fetcherThrows): array {
				$this->fetches++;
				if ($fetcherThrows === true) {
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
		$fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id) use ($fetcherThrows): array {
				$this->fetches++;
				if ($fetcherThrows === true || isset($this->objects[$id]) === false) {
					throw new \RuntimeException('not found');
				}

				return $this->objects[$id];
			}
		);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => [
				'character_schema' => 'schema-character',
				'item_schema' => 'schema-item',
				'condition_schema' => 'schema-condition',
			][$key] ?? $default
		);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, $params = []): string => vsprintf($text, (array)$params));

		$normaliser = new IdListNormaliser();

		return new UniqueHolderListener(
			new UniqueHolderService($fetcher, $normaliser),
			$normaliser,
			$config,
			$l10n,
			$logger ?? $this->createMock(LoggerInterface::class)
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
		$event = $this->update(self::BERTRAM, ['name' => 'Sir Bertram', 'items' => [self::CROWN]], ['name' => 'Sir Bertram', 'items' => []]);

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame('Crown of Aldmoor is already held by Queen Isolde. Remove it there first.', $errors['message']);
		self::assertSame('unique_item_held', $errors['items'][0]['code']);
		self::assertSame(self::CROWN, $errors['items'][0]['item']);
		self::assertSame('Queen Isolde', $errors['items'][0]['heldBy']);
	}//end testAHeldUniqueItemIsRefusedAndTheHolderNamed()

	/**
	 * REQ-UHE-001: the same refusal on a create.
	 *
	 * @return void
	 */
	public function testACreateWithAHeldUniqueItemIsRefused(): void {
		$event = new ObjectCreatingEvent(new HeldObjectEntity('schema-character', null, ['name' => 'Sir Bertram', 'items' => [self::CROWN]]));

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame('Queen Isolde', $event->getErrors()['items'][0]['heldBy']);
	}//end testACreateWithAHeldUniqueItemIsRefused()

	/**
	 * REQ-UHE-001: a non-unique item goes to many characters.
	 *
	 * @return void
	 */
	public function testANonUniqueItemIsAllowed(): void {
		$event = $this->update(self::BERTRAM, ['items' => [self::POTION]], ['items' => []]);

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
	}//end testANonUniqueItemIsAllowed()

	/**
	 * REQ-UHE-001: an unrelated edit is not blocked by an old conflict, and reads nothing.
	 *
	 * @return void
	 */
	public function testAnUnrelatedEditIsNotBlockedByAnOldConflict(): void {
		$event = $this->update(
			self::BERTRAM,
			['background' => 'A new past', 'items' => [self::CROWN]],
			['background' => 'Old', 'items' => [self::CROWN]]
		);

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(0, $this->fetches);
	}//end testAnUnrelatedEditIsNotBlockedByAnOldConflict()

	/**
	 * The holder is counted on the item side too, when the character side is empty.
	 *
	 * @return void
	 */
	public function testAHolderOnTheItemSideAloneIsCounted(): void {
		$this->characters = [];
		$event = $this->update(self::BERTRAM, ['items' => [self::CROWN]], ['items' => []]);

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame('Queen Isolde', $event->getErrors()['items'][0]['heldBy']);
	}//end testAHolderOnTheItemSideAloneIsCounted()

	/**
	 * REQ-UHE-002: a unique condition rests on one character.
	 *
	 * @return void
	 */
	public function testAUniqueConditionIsRefusedOnASecondCharacter(): void {
		$event = $this->update(self::TOMAS, ['conditions' => [self::CURSE]], ['conditions' => []]);

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertSame('unique_condition_held', $errors['conditions'][0]['code']);
		self::assertSame('Mirela', $errors['conditions'][0]['heldBy']);
		self::assertSame('Curse of the Ashen King is already on Mirela. Remove it there first.', $errors['message']);
	}//end testAUniqueConditionIsRefusedOnASecondCharacter()

	/**
	 * REQ-UHE-003: switching unique on while two characters hold the item is refused on `unique`.
	 *
	 * @return void
	 */
	public function testSwitchingUniqueOnWhileTwoHoldItIsRefused(): void {
		$old = ['name' => 'Silver key', 'unique' => false, 'characters' => [self::QUEEN, self::BERTRAM]];
		$event = $this->update(self::POTION, ['unique' => true] + $old, $old, 'schema-item');

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		$errors = $event->getErrors();
		self::assertArrayHasKey('unique', $errors);
		self::assertEqualsCanonicalizing(['Queen Isolde', 'Sir Bertram'], $errors['unique'][0]['heldBy']);
		self::assertStringContainsString('Queen Isolde', $errors['message']);
		self::assertStringContainsString('Sir Bertram', $errors['message']);
	}//end testSwitchingUniqueOnWhileTwoHoldItIsRefused()

	/**
	 * REQ-UHE-003: adding a second character on the item page is refused on `characters`.
	 *
	 * @return void
	 */
	public function testAddingASecondHolderOnTheItemIsRefused(): void {
		$old = $this->objects[self::CROWN];
		$event = $this->update(self::CROWN, ['characters' => [self::QUEEN, self::BERTRAM]] + $old, $old, 'schema-item');

		$this->listener()->handle($event);

		self::assertTrue($event->isPropagationStopped());
		self::assertSame('unique_item_several_holders', $event->getErrors()['characters'][0]['code']);
	}//end testAddingASecondHolderOnTheItemIsRefused()

	/**
	 * An item edit that does not add a holder nor switch the flag on is never blocked.
	 *
	 * @return void
	 */
	public function testAnItemEditWithoutANewHolderPasses(): void {
		$old = ['name' => 'Crown', 'unique' => true, 'characters' => [self::QUEEN, self::BERTRAM]];
		$event = $this->update(self::CROWN, ['name' => 'Crown of Aldmoor'] + $old, $old, 'schema-item');

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
	}//end testAnItemEditWithoutANewHolderPasses()

	/**
	 * Other schemas are ignored.
	 *
	 * @return void
	 */
	public function testOtherSchemasAreIgnored(): void {
		$event = $this->update(self::BERTRAM, ['items' => [self::CROWN]], [], 'schema-event');

		$this->listener()->handle($event);

		self::assertFalse($event->isPropagationStopped());
		self::assertSame(0, $this->fetches);
	}//end testOtherSchemasAreIgnored()

	/**
	 * A failing lookup is logged and the write goes through.
	 *
	 * @return void
	 */
	public function testALookupErrorIsLoggedAndTheWritePasses(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$event = new ObjectCreatingEvent(new HeldObjectEntity('schema-item', self::CROWN, ['unique' => true, 'characters' => [self::QUEEN]]));
		$logger->expects(self::once())->method('error');

		$this->listener(logger: $logger, fetcherThrows: true)->handle($event);

		self::assertFalse($event->isPropagationStopped());
	}//end testALookupErrorIsLoggedAndTheWritePasses()
}//end class
